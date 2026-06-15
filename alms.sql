-- ============================================================
-- ALMS — Database Schema & Seed Data
-- Designed for InfinityFree / cPanel MySQL
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `attendance_records`;
DROP TABLE IF EXISTS `student_badges`;
DROP TABLE IF EXISTS `badges`;
DROP TABLE IF EXISTS `xp_transactions`;
DROP TABLE IF EXISTS `gamification_profiles`;
DROP TABLE IF EXISTS `learning_recommendations`;
DROP TABLE IF EXISTS `engagement_events`;
DROP TABLE IF EXISTS `portal_student_imports`;
DROP TABLE IF EXISTS `assignment_submissions`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `quiz_attempts`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `quizzes`;
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `lesson_progress`;
DROP TABLE IF EXISTS `lessons`;
DROP TABLE IF EXISTS `course_materials`;
DROP TABLE IF EXISTS `lecturer_course_assignments`;
DROP TABLE IF EXISTS `student_wellness_logs`;
DROP TABLE IF EXISTS `lecturer_profiles`;
DROP TABLE IF EXISTS `student_profiles`;
DROP TABLE IF EXISTS `authorized_matric_numbers`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `programmes`;
DROP TABLE IF EXISTS `faculties`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS TABLE
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'lecturer', 'student') NOT NULL DEFAULT 'student',
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `title` VARCHAR(50) DEFAULT 'Student', -- e.g. Student, Engineer, Dr., Prof.
  `status` ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'active',
  `login_attempts` INT NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `reset_token` VARCHAR(255) DEFAULT NULL,
  `reset_expiry` DATETIME DEFAULT NULL,
  `email_verified_at` DATETIME DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. DEPARTMENTS TABLE (The 11 specified by FCAH&PT Ibadan)
CREATE TABLE `faculties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_id` INT DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `level_offered` ENUM('ND & HND', 'ND ONLY') NOT NULL DEFAULT 'ND & HND',
  FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `programmes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `award` ENUM('ND', 'HND') NOT NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_programme` (`department_id`, `name`, `award`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PRE-AUTHORIZED MATRIC NUMBERS (Outsider Registration Prevention)
CREATE TABLE `authorized_matric_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `matric_number` VARCHAR(100) UNIQUE NOT NULL,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. STUDENT PROFILES TABLE
CREATE TABLE `student_profiles` (
  `user_id` INT PRIMARY KEY,
  `matric_number` VARCHAR(100) UNIQUE DEFAULT NULL,
  `level_id` INT NOT NULL DEFAULT 1, -- 1 = ND I, 2 = ND II, 3 = HND I, 4 = HND II
  `department_id` INT DEFAULT NULL,
  `programme_id` INT DEFAULT NULL,
  `who5_score` INT DEFAULT NULL,     -- raw well-being sum (0-25)
  `vark_style` VARCHAR(10) DEFAULT NULL, -- e.g. V, A, R, K, VARK
  `current_pace` ENUM('express', 'standard', 'deep') NOT NULL DEFAULT 'standard',
  `onboarded` TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. LECTURER PROFILES TABLE
CREATE TABLE `lecturer_profiles` (
  `user_id` INT PRIMARY KEY,
  `primary_department_id` INT NOT NULL,
  `bio` TEXT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`primary_department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. STUDENT WELLNESS LOGS (Mental Wellness Pacing Index - Non-Diagnostic)
CREATE TABLE `student_wellness_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `attention_span` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
  `stress_level` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
  `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. COURSES REGISTER
CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_code` VARCHAR(50) UNIQUE NOT NULL,
  `course_name` VARCHAR(255) NOT NULL,
  `department_id` INT NOT NULL,
  `programme_id` INT DEFAULT NULL,
  `level` ENUM('ND', 'HND') NOT NULL DEFAULT 'ND',
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. LECTURER COURSE ASSIGNMENTS (Multi-Department Lecturing Mapping)
CREATE TABLE `lecturer_course_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lecturer_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_assignment` (`lecturer_id`, `course_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. COURSE MATERIALS (RAG Database Context)
CREATE TABLE `course_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `content_text` LONGTEXT NOT NULL, -- Text contents extracted/loaded for dynamic keyword RAG scans
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. LESSONS (Pacing Content Splitter)
CREATE TABLE `lessons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content_standard` LONGTEXT NOT NULL,
  `content_express` LONGTEXT NOT NULL,  -- Compressed outline revision summary
  `content_deep` LONGTEXT NOT NULL,     -- Detailed step-by-step visual breakdowns
  `sequence_order` INT NOT NULL DEFAULT 1,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. LESSON PROGRESS TRACKER
CREATE TABLE `lesson_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `lesson_id` INT NOT NULL,
  `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_progress` (`student_id`, `lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. SECURITY ACTIVITY LOG
CREATE TABLE `activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(100) NOT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. QUIZZES
CREATE TABLE `quizzes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `max_points` INT NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. QUESTIONS
CREATE TABLE `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id` INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `option_a` VARCHAR(255) NOT NULL,
  `option_b` VARCHAR(255) NOT NULL,
  `option_c` VARCHAR(255) NOT NULL,
  `option_d` VARCHAR(255) NOT NULL,
  `correct_option` ENUM('A', 'B', 'C', 'D') NOT NULL,
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. QUIZ ATTEMPTS
CREATE TABLE `quiz_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `score` INT NOT NULL,
  `percentage` DECIMAL(5,2) NOT NULL,
  `passed` TINYINT(1) NOT NULL DEFAULT 0,
  `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. ASSIGNMENTS
CREATE TABLE `assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `max_points` INT NOT NULL DEFAULT 100,
  `due_date` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. ASSIGNMENT SUBMISSIONS
CREATE TABLE `assignment_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `file_path` VARCHAR(255) DEFAULT NULL,
  `submitted_text` TEXT DEFAULT NULL,
  `grade` INT DEFAULT NULL,
  `feedback` TEXT DEFAULT NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. NOTIFICATIONS
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. RULE-BASED RECOMMENDATIONS
CREATE TABLE `learning_recommendations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_id` INT DEFAULT NULL,
  `type` ENUM('revise', 'review', 'advance', 'wellness', 'engagement') NOT NULL DEFAULT 'review',
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
  `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. GAMIFICATION
CREATE TABLE `gamification_profiles` (
  `student_id` INT PRIMARY KEY,
  `total_xp` INT NOT NULL DEFAULT 0,
  `level` INT NOT NULL DEFAULT 1,
  `current_streak` INT NOT NULL DEFAULT 0,
  `longest_streak` INT NOT NULL DEFAULT 0,
  `last_activity_date` DATE DEFAULT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `xp_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `points` INT NOT NULL,
  `source_type` VARCHAR(60) NOT NULL,
  `source_id` INT NOT NULL DEFAULT 0,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_xp_source` (`student_id`, `source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `badges` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(80) UNIQUE NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_badges` (
  `student_id` INT NOT NULL,
  `badge_id` INT NOT NULL,
  `earned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`, `badge_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. ENGAGEMENT AND PORTAL PROVISIONING
CREATE TABLE `engagement_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `course_id` INT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `portal_student_imports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `matric_number` VARCHAR(100) UNIQUE NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `department_id` INT DEFAULT NULL,
  `programme_id` INT DEFAULT NULL,
  `level_id` INT NOT NULL DEFAULT 1,
  `provisioned_user_id` INT DEFAULT NULL,
  `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`provisioned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. ATTENDANCE RECORDS
CREATE TABLE `attendance_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_id` INT DEFAULT NULL,
  `recorded_on` DATE NOT NULL,
  `status` ENUM('present', 'excused', 'absent') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `idx_attendance_daily` (`student_id`, `course_id`, `recorded_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO `faculties` (`name`) VALUES
('School of Animal Health and Production Technology'),
('School of Science and Technology'),
('School of Agricultural Extension and Fisheries');

-- Seed 11 Departments
INSERT INTO `departments` (`faculty_id`, `name`, `level_offered`) VALUES
(2, 'Computer Science', 'ND & HND'),
(2, 'Science Laboratory Technology', 'ND & HND'),
(1, 'Animal Health', 'ND & HND'),
(1, 'Animal Production', 'ND & HND'),
(2, 'Statistics', 'ND & HND'),
(1, 'Veterinary', 'ND & HND'),
(2, 'Biology', 'ND & HND'),
(2, 'Microbiology', 'ND & HND'),
(2, 'Physics', 'ND & HND'),
(3, 'Agricultural Extension', 'ND ONLY'),
(3, 'Fishery', 'ND ONLY');

INSERT INTO `programmes` (`department_id`, `name`, `award`)
SELECT id, CONCAT(name, ' ND'), 'ND' FROM departments;

INSERT INTO `programmes` (`department_id`, `name`, `award`)
SELECT id, CONCAT(name, ' HND'), 'HND' FROM departments WHERE level_offered = 'ND & HND';

-- Seed authorized matric codes to test the registration shield
INSERT INTO `authorized_matric_numbers` (`matric_number`) VALUES
('FCAHPT/2026/0001'),
('FCAHPT/2026/0002'),
('FCAHPT/2026/0003'),
('FCAHPT/2026/0004'),
('FCAHPT/2026/0005'),
('FCAHPT/2026/0006'),
('FCAHPT/2026/0007'),
('FCAHPT/2026/0008'),
('FCAHPT/2026/0009'),
('FCAHPT/2026/0010');

-- Seed standard courses
INSERT INTO `courses` (`course_code`, `course_name`, `department_id`, `level`) VALUES
('CS 201', 'Data Structures & Algorithms', 1, 'ND'),
('SLT 301', 'Organic Chemistry II', 2, 'HND'),
('AHP 101', 'Animal Health & Disease Control', 3, 'ND'),
('STAT 101', 'Introduction to Statistics', 5, 'ND'),
('BIO 201', 'Cell Biology & Genetics', 7, 'ND');

-- Seed course materials (for AI RAG retrieval checks)
INSERT INTO `course_materials` (`course_id`, `title`, `file_path`, `content_text`) VALUES
(1, 'Introduction to Binary Search Trees', '/uploads/materials/cs201_bst.txt', 'A Binary Search Tree (BST) is a node-based binary tree data structure which has the following properties: The left subtree of a node contains only nodes with keys lesser than the node’s key. The right subtree of a node contains only nodes with keys greater than the node’s key. Both the left and right subtrees must also be binary search trees. Insertion takes O(log n) time complexity on average, but worst case is O(n) if the tree is skewed.'),
(2, 'Stereochemistry & Stereocenters', '/uploads/materials/slt301_stereo.txt', 'Stereocenters are carbon atoms with four different groups attached to them. Chirality determines optical activity. Enantiomers rotate plane polarized light in equal and opposite directions. R/S naming is governed by Cahn-Ingold-Prelog priority rules, prioritizing heavier atomic numbers.'),
(3, 'Viral Diseases in Livestock', '/uploads/materials/ahp101_diseases.txt', 'Viral livestock pathogens at FCAHPT farm include Newcastle Disease in poultry and Foot and Mouth Disease in ruminants. Symptoms of Newcastle include gasping, coughing, and green watery diarrhea. Prevention relies entirely on timely vaccination schedules.');

-- Seed standard lessons with Express, Standard, and Deep pacing text
INSERT INTO `lessons` (`course_id`, `title`, `content_standard`, `content_express`, `content_deep`, `sequence_order`) VALUES
(1, 'Binary Trees Basics', 
 'In this lesson, we study the fundamental structure of a Binary Tree where each node has at most two children, referred to as the left child and the right child. We will learn binary tree traversals: Pre-order, In-order, and Post-order.',
 'Binary Tree: Each node has <= 2 children (left/right). Traversals: In-order (left, root, right), Pre-order (root, left, right), Post-order (left, right, root). Core properties include leaf nodes and height parameters.',
 '### 1. Visualizing a Binary Tree\nImagine a family tree upside down. The root node sits at the top.\n```\n      [Root] (10)\n      /         \\\n  [Left] (5)   [Right] (15)\n```\n### 2. Standard Traversals Step-by-Step\n- **In-order (L -> Root -> R)**: Visits in ascending sorted order in BSTs. In the example above, visiting gives: 5, 10, 15.\n- **Pre-order (Root -> L -> R)**: Used to clone trees: 10, 5, 15.\n- **Post-order (L -> R -> Root)**: Used for garbage collection/deletes: 5, 15, 10.', 
 1);

-- Seed pre-installed Admin user (Password: Admin@Alms2026)
-- Hash generated using PASSWORD_BCRYPT (Cost: 12)
INSERT INTO `users` (`email`, `password_hash`, `role`, `first_name`, `last_name`, `title`, `status`) VALUES
('admin@fcahptib.edu.ng', '$2y$12$Z0D/Uf7W1sP.7d9T5p9d7OjWjT3400jPqT.qW51.t7V63Q9v3S40O', 'admin', 'System', 'Administrator', 'Admin', 'active');

-- ============================================================
-- SECTION 10: OPTIMIZED INDEXES
-- ============================================================
-- Redundant indexes on UNIQUE columns (like email or matric_number) are omitted
-- as MySQL automatically indexes them for maximum lookup efficiency.

CREATE INDEX `idx_lecturer_approved` ON `lecturer_profiles`(`approved_at`);
CREATE INDEX `idx_assignments_due`   ON `assignments`(`due_date`);
CREATE INDEX `idx_notif_unread`      ON `notifications`(`user_id`, `is_read`);
CREATE INDEX `idx_attendance_month`  ON `attendance_records`(`student_id`, `recorded_on`);
CREATE INDEX `idx_recommendations`   ON `learning_recommendations`(`student_id`, `is_resolved`, `priority`);
CREATE INDEX `idx_engagement_user`   ON `engagement_events`(`user_id`, `created_at`);

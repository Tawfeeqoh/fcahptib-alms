<?php
// ============================================================
// ALMS - Rule-based learning, gradebook, and gamification helpers
// ============================================================

function levelNameFromId(int $levelId): string {
    return match($levelId) {
        1 => 'ND I',
        2 => 'ND II',
        3 => 'HND I',
        4 => 'HND II',
        default => 'Level not set',
    };
}

function programFromLevelId(int $levelId): string {
    return $levelId <= 2 ? 'ND' : 'HND';
}

function studentProfile(int $userId): ?array {
    $stmt = db()->prepare("
        SELECT sp.*, d.name AS department_name
        FROM student_profiles sp
        LEFT JOIN departments d ON sp.department_id = d.id
        WHERE sp.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}

function upsertGamificationProfile(int $studentId): array {
    $db = db();
    $stmt = $db->prepare('SELECT * FROM gamification_profiles WHERE student_id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $profile = $stmt->fetch();
    if ($profile) return $profile;

    $db->prepare('INSERT INTO gamification_profiles (student_id) VALUES (?)')->execute([$studentId]);
    $stmt->execute([$studentId]);
    return $stmt->fetch();
}

function awardXp(int $studentId, int $points, string $sourceType, int $sourceId = 0, string $description = ''): void {
    if ($points <= 0) return;

    $db = db();
    upsertGamificationProfile($studentId);

    try {
        $db->prepare("
            INSERT INTO xp_transactions (student_id, points, source_type, source_id, description)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$studentId, $points, $sourceType, $sourceId, $description]);
    } catch (PDOException $e) {
        // Duplicate XP source is intentionally ignored.
        return;
    }

    $profile = upsertGamificationProfile($studentId);
    $lastActivity = $profile['last_activity_date'] ?? null;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $currentStreak = (int)($profile['current_streak'] ?? 0);
    $longestStreak = (int)($profile['longest_streak'] ?? 0);

    if ($lastActivity === $today) {
        $newStreak = max(1, $currentStreak);
    } elseif ($lastActivity === $yesterday) {
        $newStreak = $currentStreak + 1;
    } else {
        $newStreak = 1;
    }

    $newLongest = max($longestStreak, $newStreak);
    $db->prepare("
        UPDATE gamification_profiles
        SET total_xp = total_xp + ?, current_streak = ?, longest_streak = ?, last_activity_date = ?, level = FLOOR((total_xp + ?) / 500) + 1
        WHERE student_id = ?
    ")->execute([$points, $newStreak, $newLongest, $today, $points, $studentId]);

    evaluateBadges($studentId);
}

function evaluateBadges(int $studentId): void {
    $db = db();
    $profile = upsertGamificationProfile($studentId);
    $rules = [
        ['code' => 'first_steps', 'name' => 'First Steps', 'description' => 'Earned your first learning XP.', 'condition' => (int)$profile['total_xp'] >= 1],
        ['code' => 'steady_learner', 'name' => 'Steady Learner', 'description' => 'Built a 3-day learning streak.', 'condition' => (int)$profile['current_streak'] >= 3],
        ['code' => 'course_finisher', 'name' => 'Course Finisher', 'description' => 'Completed at least five lesson modules.', 'condition' => completedLessonCount($studentId) >= 5],
        ['code' => 'assessment_ready', 'name' => 'Assessment Ready', 'description' => 'Scored at least 70% on a quiz.', 'condition' => bestQuizPercentage($studentId) >= 70],
    ];

    foreach ($rules as $rule) {
        if (!$rule['condition']) continue;
        $db->prepare("
            INSERT IGNORE INTO badges (code, name, description)
            VALUES (?, ?, ?)
        ")->execute([$rule['code'], $rule['name'], $rule['description']]);

        $badgeId = (int)$db->lastInsertId();
        if ($badgeId === 0) {
            $stmt = $db->prepare('SELECT id FROM badges WHERE code = ? LIMIT 1');
            $stmt->execute([$rule['code']]);
            $badgeId = (int)($stmt->fetch()['id'] ?? 0);
        }
        if ($badgeId > 0) {
            $db->prepare('INSERT IGNORE INTO student_badges (student_id, badge_id) VALUES (?, ?)')->execute([$studentId, $badgeId]);
        }
    }
}

function completedLessonCount(int $studentId): int {
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM lesson_progress WHERE student_id = ?');
    $stmt->execute([$studentId]);
    return (int)($stmt->fetch()['total'] ?? 0);
}

function bestQuizPercentage(int $studentId): float {
    $stmt = db()->prepare('SELECT MAX(percentage) AS best FROM quiz_attempts WHERE student_id = ?');
    $stmt->execute([$studentId]);
    return (float)($stmt->fetch()['best'] ?? 0);
}

function createRecommendation(int $studentId, ?int $courseId, string $type, string $title, string $body, string $priority = 'medium'): void {
    db()->prepare("
        INSERT INTO learning_recommendations (student_id, course_id, type, title, body, priority)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$studentId, $courseId, $type, $title, $body, $priority]);
}

function createNotification(int $userId, string $title, string $message): void {
    db()->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)')->execute([$userId, $title, $message]);
}

function generateQuizRecommendation(int $studentId, int $quizId, float $percentage): void {
    $stmt = db()->prepare("
        SELECT q.title, c.id AS course_id, c.course_code, c.course_name
        FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        WHERE q.id = ?
        LIMIT 1
    ");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    if (!$quiz) return;

    if ($percentage < 50) {
        createRecommendation(
            $studentId,
            (int)$quiz['course_id'],
            'revise',
            'Revise before retrying ' . $quiz['title'],
            'Your latest score shows a knowledge gap in ' . $quiz['course_code'] . '. Review the current lesson modules, then retry the quiz when ready.',
            'high'
        );
        createNotification($studentId, 'Revision pathway created', 'A high-priority study recommendation was added for ' . $quiz['course_code'] . '.');
    } elseif ($percentage < 70) {
        createRecommendation(
            $studentId,
            (int)$quiz['course_id'],
            'review',
            'Strengthen ' . $quiz['course_code'],
            'You passed, but a focused review will help you stabilise your understanding before the next module.',
            'medium'
        );
    } else {
        createRecommendation(
            $studentId,
            (int)$quiz['course_id'],
            'advance',
            'Advance in ' . $quiz['course_code'],
            'Strong performance. Continue to the next module and keep your learning streak alive.',
            'low'
        );
    }
}

function gradebookSummary(int $studentId): array {
    $quizStmt = db()->prepare("
        SELECT c.course_code, c.course_name, AVG(qa.percentage) AS quiz_avg, MAX(qa.completed_at) AS last_attempt
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN courses c ON q.course_id = c.id
        WHERE qa.student_id = ?
        GROUP BY c.id, c.course_code, c.course_name
        ORDER BY c.course_code
    ");
    $quizStmt->execute([$studentId]);

    $assignmentStmt = db()->prepare("
        SELECT c.course_code, c.course_name, AVG(s.grade) AS assignment_avg, COUNT(s.id) AS submissions
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        WHERE s.student_id = ? AND s.grade IS NOT NULL
        GROUP BY c.id, c.course_code, c.course_name
        ORDER BY c.course_code
    ");
    $assignmentStmt->execute([$studentId]);

    return [
        'quizzes' => $quizStmt->fetchAll(),
        'assignments' => $assignmentStmt->fetchAll(),
    ];
}

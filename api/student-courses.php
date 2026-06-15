<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';

apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$profile = studentProfile($userId);
if (!$profile) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

if ((int)$profile['onboarded'] === 0) {
    apiJson(['success' => false, 'message' => 'Onboarding required.', 'onboarding_required' => true], 403);
}

$levelName = programFromLevelId((int)$profile['level_id']);
$db = db();

$coursesStmt = $db->prepare(
    'SELECT c.id, c.course_code, c.course_name,
            (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id WHERE lp.student_id = ? AND l.course_id = c.id) AS completed_lessons,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS total_lessons
     FROM courses c
     WHERE c.department_id = ? AND c.level = ?
     ORDER BY c.course_code ASC'
);
$coursesStmt->execute([$userId, $profile['department_id'], $levelName]);
$courses = $coursesStmt->fetchAll();

foreach ($courses as &$course) {
    $course['completed_lessons'] = (int)$course['completed_lessons'];
    $course['total_lessons'] = (int)$course['total_lessons'];
    $course['progress'] = $course['total_lessons'] > 0 ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
}

apiJson([
    'success' => true,
    'profile' => [
        'department_name' => $profile['department_name'],
        'level_name' => $levelName,
    ],
    'courses' => $courses,
]);

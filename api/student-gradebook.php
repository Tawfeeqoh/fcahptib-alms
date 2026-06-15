<?php
// ============================================================
// ALMS — Student Gradebook API
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$summary = gradebookSummary($userId);

apiJson([
    'success' => true,
    'quizzes' => array_map(function ($row) {
        return [
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'quiz_avg' => $row['quiz_avg'] !== null ? round((float)$row['quiz_avg']) : null,
            'last_attempt' => $row['last_attempt'],
        ];
    }, $summary['quizzes']),
    'assignments' => array_map(function ($row) {
        return [
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'assignment_avg' => $row['assignment_avg'] !== null ? round((float)$row['assignment_avg']) : null,
            'submissions' => (int)$row['submissions'],
        ];
    }, $summary['assignments']),
]);

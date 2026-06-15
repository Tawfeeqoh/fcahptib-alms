<?php
// ============================================================
// ALMS — Lecturer Analytics API
// ============================================================
require_once __DIR__ . '/../config.php';

apiCors();

if (!isAuthenticated() || ($_SESSION['role'] ?? '') !== 'lecturer') {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 403);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

try {
    $courseStmt = $db->prepare("
        SELECT c.id, c.course_code, c.course_name, d.name AS department_name,
               COUNT(DISTINCT sp.user_id) AS students,
               AVG(qa.percentage) AS quiz_average,
               COUNT(DISTINCT s.id) AS submissions
        FROM lecturer_course_assignments lca
        JOIN courses c ON lca.course_id = c.id
        JOIN departments d ON lca.department_id = d.id
        LEFT JOIN student_profiles sp ON sp.department_id = lca.department_id AND c.level = IF(sp.level_id <= 2, 'ND', 'HND')
        LEFT JOIN quizzes q ON q.course_id = c.id
        LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = sp.user_id
        LEFT JOIN assignments a ON a.course_id = c.id
        LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = sp.user_id
        WHERE lca.lecturer_id = ?
        GROUP BY c.id, c.course_code, c.course_name, d.name
        ORDER BY c.course_code
    ");
    $courseStmt->execute([$userId]);
    $courses = $courseStmt->fetchAll();

    apiJson([
        'success' => true,
        'courses' => $courses
    ]);
} catch (PDOException $e) {
    apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

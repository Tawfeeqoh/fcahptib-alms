<?php
// ============================================================
// ALMS — Lecturer Gradebook API
// ============================================================
require_once __DIR__ . '/../config.php';

apiCors();

if (!isAuthenticated() || ($_SESSION['role'] ?? '') !== 'lecturer') {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 403);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, sp.matric_number, d.name AS department_name,
               AVG(qa.percentage) AS quiz_average,
               AVG(s.grade) AS assignment_average
        FROM lecturer_course_assignments lca
        JOIN student_profiles sp ON sp.department_id = lca.department_id
        JOIN users u ON sp.user_id = u.id
        JOIN departments d ON sp.department_id = d.id
        LEFT JOIN quizzes q ON q.course_id = lca.course_id
        LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = u.id
        LEFT JOIN assignments a ON a.course_id = lca.course_id
        LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = u.id
        WHERE lca.lecturer_id = ?
        GROUP BY u.id, u.first_name, u.last_name, sp.matric_number, d.name
        ORDER BY d.name, u.last_name
    ");
    $stmt->execute([$userId]);
    $students = $stmt->fetchAll();

    apiJson([
        'success' => true,
        'students' => $students
    ]);
} catch (PDOException $e) {
    apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

<?php
// ============================================================
// ALMS — Lecturer Announcements API
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';

apiCors();

if (!isAuthenticated() || ($_SESSION['role'] ?? '') !== 'lecturer') {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 403);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfFromRequest();

    // Support both JSON body and standard Form POST
    $input = readJsonInput();
    $title = trim($input['title'] ?? ($_POST['title'] ?? ''));
    $message = trim($input['message'] ?? ($_POST['message'] ?? ''));
    $courseId = (int)($input['course_id'] ?? ($_POST['course_id'] ?? 0));

    if (!$title || !$message || $courseId <= 0) {
        apiJson(['success' => false, 'message' => 'Please fill in all required fields.'], 422);
    }

    try {
        // Verify this course assignment belongs to the lecturer
        $check = $db->prepare('SELECT id FROM lecturer_course_assignments WHERE lecturer_id = ? AND course_id = ? LIMIT 1');
        $check->execute([$userId, $courseId]);
        if (!$check->fetch()) {
            apiJson(['success' => false, 'message' => 'Course cohort not assigned to your portfolio.'], 403);
        }

        // Fetch student users in this cohort
        $students = $db->prepare("
            SELECT DISTINCT sp.user_id
            FROM lecturer_course_assignments lca
            JOIN courses c ON lca.course_id = c.id
            JOIN student_profiles sp ON sp.department_id = lca.department_id AND c.level = IF(sp.level_id <= 2, 'ND', 'HND')
            WHERE lca.lecturer_id = ? AND c.id = ?
        ");
        $students->execute([$userId, $courseId]);
        $rows = $students->fetchAll();

        foreach ($rows as $row) {
            createNotification((int)$row['user_id'], $title, $message);
        }

        apiJson([
            'success' => true,
            'message' => 'Announcement sent to the selected course cohort.'
        ]);
    } catch (PDOException $e) {
        apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    // GET request: fetch assigned courses
    try {
        $courseStmt = $db->prepare("
            SELECT c.id, c.course_code, c.course_name
            FROM lecturer_course_assignments lca
            JOIN courses c ON lca.course_id = c.id
            WHERE lca.lecturer_id = ?
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
}

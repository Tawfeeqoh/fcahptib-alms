<?php
// ============================================================
// ALMS — Lecturer Quiz Builder API
// ============================================================
require_once __DIR__ . '/../config.php';

apiCors();

if (!isAuthenticated() || ($_SESSION['role'] ?? '') !== 'lecturer') {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 403);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfFromRequest();

    $input = readJsonInput();
    $courseId = (int)($input['course_id'] ?? ($_POST['course_id'] ?? 0));
    $title = trim($input['title'] ?? ($_POST['title'] ?? ''));
    $question = trim($input['question_text'] ?? ($_POST['question_text'] ?? ''));
    $optionA = trim($input['option_a'] ?? ($_POST['option_a'] ?? ''));
    $optionB = trim($input['option_b'] ?? ($_POST['option_b'] ?? ''));
    $optionC = trim($input['option_c'] ?? ($_POST['option_c'] ?? ''));
    $optionD = trim($input['option_d'] ?? ($_POST['option_d'] ?? ''));
    $correct = trim($input['correct_option'] ?? ($_POST['correct_option'] ?? 'A'));

    if ($courseId <= 0 || !$title || !$question || !$optionA || !$optionB || !$optionC || !$optionD || !in_array($correct, ['A', 'B', 'C', 'D'], true)) {
        apiJson(['success' => false, 'message' => 'Please fill in all required fields and select a valid correct option.'], 422);
    }

    try {
        // Verify course assignment
        $check = $db->prepare('SELECT id FROM lecturer_course_assignments WHERE lecturer_id = ? AND course_id = ? LIMIT 1');
        $check->execute([$userId, $courseId]);
        if (!$check->fetch()) {
            apiJson(['success' => false, 'message' => 'Course not assigned to your portfolio.'], 403);
        }

        $db->beginTransaction();

        // Insert quiz
        $quizStmt = $db->prepare('INSERT INTO quizzes (course_id, title, description) VALUES (?, ?, ?)');
        $quizStmt->execute([$courseId, $title, 'Lecturer-created assessment']);
        $quizId = (int)$db->lastInsertId();

        // Insert question
        $qStmt = $db->prepare('INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $qStmt->execute([$quizId, $question, $optionA, $optionB, $optionC, $optionD, $correct]);

        $db->commit();

        apiJson([
            'success' => true,
            'message' => 'Quiz published successfully with one question.'
        ]);
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    // GET: fetch courses
    try {
        $courses = $db->prepare('
            SELECT c.id, c.course_code, c.course_name 
            FROM lecturer_course_assignments lca 
            JOIN courses c ON lca.course_id = c.id 
            WHERE lca.lecturer_id = ? 
            ORDER BY c.course_code
        ');
        $courses->execute([$userId]);
        $courseRows = $courses->fetchAll();

        apiJson([
            'success' => true,
            'courses' => $courseRows
        ]);
    } catch (PDOException $e) {
        apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
}

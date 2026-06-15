<?php
// ============================================================
// ALMS — Student Quiz API
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

$stmt = $db->prepare('SELECT level_id, department_id FROM student_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch();
if (!$profile) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

$levelName = ($profile['level_id'] <= 2) ? 'ND' : 'HND';
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if ($quizId > 0) {
    $quizStmt = $db->prepare(
        'SELECT q.id, q.title, q.description, q.max_points, c.course_code, c.course_name
         FROM quizzes q
         JOIN courses c ON q.course_id = c.id
         WHERE q.id = ? AND c.department_id = ? AND c.level = ?'
    );
    $quizStmt->execute([$quizId, $profile['department_id'], $levelName]);
    $quiz = $quizStmt->fetch();
    if (!$quiz) {
        apiJson(['success' => false, 'message' => 'Quiz not found.'], 404);
    }

    $questionStmt = $db->prepare('SELECT id, question_text, option_a, option_b, option_c, option_d FROM questions WHERE quiz_id = ?');
    $questionStmt->execute([$quizId]);
    $questions = $questionStmt->fetchAll();

    apiJson(['success' => true, 'quiz' => $quiz, 'questions' => $questions]);
}

$quizListStmt = $db->prepare(
    'SELECT q.id, q.title, q.description, q.max_points, c.course_code,
            (SELECT score FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? ORDER BY completed_at DESC LIMIT 1) AS attempt_score,
            (SELECT percentage FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? ORDER BY completed_at DESC LIMIT 1) AS attempt_pct
     FROM quizzes q
     JOIN courses c ON q.course_id = c.id
     WHERE c.department_id = ? AND c.level = ?
     ORDER BY q.created_at DESC'
);
$quizListStmt->execute([$userId, $userId, $profile['department_id'], $levelName]);
$quizzes = $quizListStmt->fetchAll();

apiJson(['success' => true, 'quizzes' => $quizzes]);

<?php
// ============================================================
// ALMS — Quiz Score Submission API
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
}
verifyCsrfFromRequest();

$input = readJsonInput();
$quiz_id = (int)($input['quiz_id'] ?? 0);
$answers = $input['answers'] ?? [];

if ($quiz_id <= 0) {
    apiJson(['success' => false, 'message' => 'Invalid quiz ID.'], 422);
}

$db = db();

try {
    // 1. Fetch all questions for this quiz to prevent tampering
    $qStmt = $db->prepare("SELECT id, correct_option FROM questions WHERE quiz_id = ?");
    $qStmt->execute([$quiz_id]);
    $questions = $qStmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC); // returns [id => [correct_option => 'A']]

    if (empty($questions)) {
        apiJson(['success' => false, 'message' => 'No questions found for this quiz.'], 404);
    }

    $total = count($questions);
    $score = 0;

    // 2. Score responses
    foreach ($answers as $ans) {
        $qid = (int)($ans['question_id'] ?? 0);
        $selected = strtoupper(trim($ans['selected_option'] ?? ''));

        if (isset($questions[$qid])) {
            $correct = $questions[$qid]['correct_option'];
            if ($selected === $correct) {
                $score++;
            }
        }
    }

    $percentage = ($score / $total) * 100;
    $passed = $percentage >= 50 ? 1 : 0;

    // 3. Save attempt
    $attempt = $db->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, score, percentage, passed) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $attempt->execute([$quiz_id, $_SESSION['user_id'], $score, $percentage, $passed]);
    $attemptId = (int)$db->lastInsertId();

    $xp = $passed ? 50 : 20;
    if ($percentage >= 80) $xp += 25;
    awardXp((int)$_SESSION['user_id'], $xp, 'quiz_attempt', $attemptId, 'Quiz attempt completed');
    generateQuizRecommendation((int)$_SESSION['user_id'], $quiz_id, $percentage);

    // Customize encouragement based on wellness
    $feedback = "You scored $score/$total ($percentage%). ";
    if ($passed) {
        $feedback .= "Excellent work, congratulations!";
    } else {
        $feedback .= "Take a deep breath and review the slides before retrying. You can do this!";
    }

    apiJson([
        'success' => true,
        'score' => $score,
        'total' => $total,
        'percentage' => $percentage,
        'passed' => (bool)$passed,
        'feedback' => $feedback
    ]);
} catch (PDOException $e) {
    error_log('Quiz submit error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Could not submit quiz responses.'], 500);
}

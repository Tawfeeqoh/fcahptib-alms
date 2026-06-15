<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';

apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    verifyCsrfFromRequest();
    $input = readJsonInput();
    $recommendationId = (int)($input['recommendation_id'] ?? 0);

    if ($recommendationId <= 0) {
        apiJson(['success' => false, 'message' => 'Invalid recommendation identifier.'], 422);
    }

    $stmt = db()->prepare('UPDATE learning_recommendations SET is_resolved = 1 WHERE id = ? AND student_id = ?');
    $stmt->execute([$recommendationId, $userId]);
    apiJson(['success' => true, 'message' => 'Recommendation marked complete.']);
}

$stmt = db()->prepare(
    'SELECT r.id, r.title, r.body, r.priority, r.created_at, c.course_code
     FROM learning_recommendations r
     LEFT JOIN courses c ON r.course_id = c.id
     WHERE r.student_id = ? AND r.is_resolved = 0
     ORDER BY FIELD(r.priority, "high", "medium", "low"), r.created_at DESC'
);
$stmt->execute([$userId]);
$recommendations = $stmt->fetchAll();

apiJson([
    'success' => true,
    'recommendations' => $recommendations,
]);

<?php
// ============================================================
// ALMS — Update Student Pacing Endpoint
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
}
verifyCsrfFromRequest();

// Read JSON input
$input = readJsonInput();
$pace = strtolower(trim($input['pace'] ?? 'standard'));

// Validate
if (!in_array($pace, ['express', 'standard', 'deep'])) {
    apiJson(['success' => false, 'message' => 'Invalid pace. Options: express, standard, deep.'], 422);
}

$db = db();

try {
    $stmt = $db->prepare("UPDATE student_profiles SET current_pace = ? WHERE user_id = ?");
    $stmt->execute([$pace, $_SESSION['user_id']]);

    $_SESSION['current_pace'] = $pace;

    apiJson(['success' => true, 'message' => 'Study pacing updated successfully.', 'pace' => $pace]);
} catch (PDOException $e) {
    error_log('Lesson pace error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Could not update study pacing.'], 500);
}

<?php
// ============================================================
// ALMS — Student Wellness Metrics Save Endpoint
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
}
verifyCsrfFromRequest();

// Read JSON input
$input = readJsonInput();
$attention = strtolower(trim($input['attention_span'] ?? 'medium'));
$stress = strtolower(trim($input['stress_level'] ?? 'low'));

// Validate
if (!in_array($attention, ['low', 'medium', 'high'])) {
    apiJson(['success' => false, 'message' => 'Invalid attention span level.'], 422);
}
if (!in_array($stress, ['low', 'medium', 'high'])) {
    apiJson(['success' => false, 'message' => 'Invalid stress level.'], 422);
}

$db = db();

try {
    $stmt = $db->prepare("
        INSERT INTO student_wellness_logs (user_id, attention_span, stress_level) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $attention, $stress]);

    // Automatically update the pacing selection on profile if stressed
    if ($stress === 'high') {
        $updatePace = $db->prepare("UPDATE student_profiles SET current_pace = 'express' WHERE user_id = ?");
        $updatePace->execute([$_SESSION['user_id']]);
        $_SESSION['current_pace'] = 'express';
    }

    apiJson(['success' => true, 'message' => 'Wellness metrics logged successfully.']);
} catch (PDOException $e) {
    error_log('Wellness save error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Could not save wellness metrics.'], 500);
}

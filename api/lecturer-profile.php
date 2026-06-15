<?php
// ============================================================
// ALMS — Lecturer Profile API
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
        SELECT u.first_name, u.last_name, u.email, u.title, d.name AS department_name, lp.bio
        FROM users u
        LEFT JOIN lecturer_profiles lp ON u.id = lp.user_id
        LEFT JOIN departments d ON lp.primary_department_id = d.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        apiJson(['success' => false, 'message' => 'Lecturer profile not found.'], 404);
    }

    apiJson([
        'success' => true,
        'user' => $user
    ]);
} catch (PDOException $e) {
    apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

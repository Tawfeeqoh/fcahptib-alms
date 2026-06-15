<?php
// ============================================================
// ALMS — Notifications Counter API
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'count' => 0], 401);
}

$db = db();

try {
    $stmt = $db->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $res = $stmt->fetch();

    apiJson([
        'success' => true,
        'count' => (int)($res['unread_count'] ?? 0)
    ]);
} catch (PDOException $e) {
    apiJson([
        'success' => false,
        'count' => 0,
    ], 500);
}

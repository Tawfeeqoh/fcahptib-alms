<?php
// ============================================================
// ALMS — Admin Announcements API
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';

apiCors();

if (!isAuthenticated() || ($_SESSION['role'] ?? '') !== 'admin') {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 403);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfFromRequest();

    $input = readJsonInput();
    $title = trim($input['title'] ?? ($_POST['title'] ?? ''));
    $message = trim($input['message'] ?? ($_POST['message'] ?? ''));
    $targetRole = trim($input['role'] ?? ($_POST['role'] ?? 'student'));

    if (!$title || !$message || !in_array($targetRole, ['student', 'lecturer', 'admin', 'all'], true)) {
        apiJson(['success' => false, 'message' => 'Please fill in all required fields and select a valid audience.'], 422);
    }

    try {
        $sql = $targetRole === 'all'
            ? 'SELECT id FROM users WHERE status = "active"'
            : 'SELECT id FROM users WHERE role = ? AND status = "active"';
        
        $stmt = $db->prepare($sql);
        $stmt->execute($targetRole === 'all' ? [] : [$targetRole]);
        $users = $stmt->fetchAll();

        foreach ($users as $user) {
            createNotification((int)$user['id'], $title, $message);
        }

        apiJson([
            'success' => true,
            'message' => 'Announcement broadcast successfully to ' . count($users) . ' active users.'
        ]);
    } catch (PDOException $e) {
        apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    apiJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

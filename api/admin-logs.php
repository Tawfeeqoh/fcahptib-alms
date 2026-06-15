<?php
// ============================================================
// ALMS — Admin Activity Logs API
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

if ($_SESSION['role'] !== 'admin') {
    apiJson(['success' => false, 'message' => 'Forbidden.'], 403);
}

$db = db();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 50;

$offset = ($page - 1) * $limit;

try {
    // Query limit + 1 to check if there are more items
    $stmt = $db->prepare("
        SELECT a.id, a.action, a.ip_address, a.timestamp, u.first_name, u.last_name, u.role
        FROM activity_log a
        JOIN users u ON u.id = a.user_id
        ORDER BY a.timestamp DESC
        LIMIT ? OFFSET ?
    ");
    
    // Bind parameters with integer types explicitly to prevent PDO binding them as strings
    $stmt->bindValue(1, $limit + 1, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll();
    
    $hasMore = false;
    if (count($logs) > $limit) {
        $hasMore = true;
        array_pop($logs); // remove the extra item
    }

    apiJson([
        'success' => true,
        'logs' => $logs,
        'has_more' => $hasMore
    ]);
} catch (PDOException $e) {
    error_log('Admin logs API error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Database error.'], 500);
}

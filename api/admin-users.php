<?php
// ============================================================
// ALMS — Admin Users Management API
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
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    verifyCsrfFromRequest();
    $input = readJsonInput();
    $action = $input['action'] ?? '';

    if ($action === 'update_status') {
        $userId = (int)($input['user_id'] ?? 0);
        $status = trim($input['status'] ?? '');

        if ($userId <= 0 || !in_array($status, ['pending', 'active', 'suspended'])) {
            apiJson(['success' => false, 'message' => 'Invalid parameters.'], 422);
        }

        $db->beginTransaction();
        try {
            // Get user's current role before updating
            $stmt = $db->prepare("SELECT role, first_name, last_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) {
                apiJson(['success' => false, 'message' => 'User not found.'], 404);
            }

            // Update status
            $update = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $update->execute([$status, $userId]);

            // Special handling if lecturer is approved (transition to active status)
            if ($user['role'] === 'lecturer' && $status === 'active') {
                $lpUpdate = $db->prepare("UPDATE lecturer_profiles SET approved_at = NOW() WHERE user_id = ? AND approved_at IS NULL");
                $lpUpdate->execute([$userId]);

                // Create a notification for the lecturer
                $notif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Account Approved', 'Your lecturer credentials have been verified by the administrator. Welcome aboard!')");
                $notif->execute([$userId]);
            }

            // Log activity
            $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)");
            $log->execute([$_SESSION['user_id'], "set user $fullName ($userId) status to: $status", $ip]);

            $db->commit();
            apiJson(['success' => true, 'message' => 'User status updated successfully.']);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Update user status error: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown action.'], 400);
}

// GET Handler - List all users with their departments
try {
    $users = $db->query("
        SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.status, u.created_at,
               COALESCE(d1.name, d2.name) AS department_name
        FROM users u
        LEFT JOIN student_profiles s ON s.user_id = u.id
        LEFT JOIN departments d1 ON d1.id = s.department_id
        LEFT JOIN lecturer_profiles l ON l.user_id = u.id
        LEFT JOIN departments d2 ON d2.id = l.primary_department_id
        ORDER BY u.created_at DESC
    ")->fetchAll();

    apiJson([
        'success' => true,
        'users' => $users
    ]);
} catch (PDOException $e) {
    error_log('List users API error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Database error.'], 500);
}

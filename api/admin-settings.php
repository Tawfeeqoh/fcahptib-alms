<?php
// ============================================================
// ALMS — Admin Settings API
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
$configFile = __DIR__ . '/../db/config_options.json';

if ($method === 'POST') {
    verifyCsrfFromRequest();
    $input = readJsonInput();
    $action = $input['action'] ?? '';

    if ($action === 'change_password') {
        $current = $input['current_password'] ?? '';
        $new = $input['new_password'] ?? '';
        $confirm = $input['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            apiJson(['success' => false, 'message' => 'Please fill in all fields.'], 422);
        }
        if ($new !== $confirm) {
            apiJson(['success' => false, 'message' => 'Passwords do not match.'], 422);
        }
        if (strlen($new) < 8) {
            apiJson(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
        }

        // Fetch user password hash
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            apiJson(['success' => false, 'message' => 'Current password is incorrect.'], 401);
        }

        // Update password hash
        $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $update = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update->execute([$newHash, $_SESSION['user_id']]);

        // Log activity
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, 'changed password', ?)");
        $log->execute([$_SESSION['user_id'], $ip]);

        apiJson(['success' => true, 'message' => 'Password updated successfully.']);
    }

    if ($action === 'save_config') {
        $instName = trim($input['institution_name'] ?? '');
        $supportEmail = trim($input['support_email'] ?? '');
        $maxSize = (int)($input['max_file_size_mb'] ?? 25);
        $regOpen = (bool)($input['registrations_open'] ?? true);

        $configData = [
            'institution_name' => $instName,
            'support_email' => $supportEmail,
            'max_file_size_mb' => $maxSize,
            'registrations_open' => $regOpen
        ];

        // Ensure directory exists
        $dir = dirname($configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT))) {
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, 'saved platform config', ?)");
            $log->execute([$_SESSION['user_id'], $ip]);

            apiJson(['success' => true, 'message' => 'Settings saved successfully.']);
        } else {
            apiJson(['success' => false, 'message' => 'Could not save configuration file.'], 500);
        }
    }

    if ($action === 'clear_old_logs') {
        try {
            $stmt = $db->prepare("DELETE FROM activity_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            
            // Log this deletion action
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, 'cleared old logs', ?)");
            $log->execute([$_SESSION['user_id'], $ip]);

            apiJson(['success' => true, 'message' => 'Old activity logs cleared.']);
        } catch (PDOException $e) {
            error_log('Clear logs error: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown action.'], 400);
}

// GET Handler - Load settings
$config = [
    'institution_name' => SITE_NAME,
    'support_email' => 'support@fcahptib.edu.ng',
    'max_file_size_mb' => 25,
    'registrations_open' => true
];

if (file_exists($configFile)) {
    $data = json_decode(file_get_contents($configFile), true);
    if (is_array($data)) {
        $config = array_merge($config, $data);
    }
}

apiJson([
    'success' => true,
    'config' => $config
]);

<?php
/**
 * set-admin-password.php
 * Run this ONCE on InfinityFree after importing alms.sql
 * to set the correct bcrypt hash for the admin account.
 *
 * DELETE THIS FILE after running it.
 *
 * Access: https://yourdomain.com/set-admin-password.php
 */

require_once __DIR__ . '/config.php';

// ── Configuration ─────────────────────────────────────────────────────────
define('ADMIN_EMAIL',    'tawfeeqohh@gmail.com');
define('ADMIN_PASSWORD', '@BigMummyTaw419');
// ──────────────────────────────────────────────────────────────────────────

// Only allow running from browser directly (basic protection)
if (php_sapi_name() === 'cli') {
    exit("Run this from a browser, not CLI.\n");
}

$hash = password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $db   = db();
    $stmt = $db->prepare(
        'UPDATE users SET email = ?, password_hash = ?, first_name = ? WHERE role = ? LIMIT 1'
    );
    $stmt->execute([ADMIN_EMAIL, $hash, 'Tawfeeqoh', 'admin']);

    if ($stmt->rowCount() > 0) {
        echo '<h2 style="color:green;">&#10003; Admin password updated successfully.</h2>';
        echo '<p>Email: <strong>' . ADMIN_EMAIL . '</strong></p>';
        echo '<p style="color:red;"><strong>IMPORTANT: Delete this file immediately from your server.</strong></p>';
    } else {
        // No admin exists yet — insert
        $ins = $db->prepare(
            'INSERT INTO users (email, password_hash, role, first_name, last_name, title, status)
             VALUES (?, ?, "admin", "Tawfeeqoh", "Administrator", "Admin", "active")'
        );
        $ins->execute([ADMIN_EMAIL, $hash]);
        echo '<h2 style="color:green;">&#10003; Admin user created successfully.</h2>';
        echo '<p>Email: <strong>' . ADMIN_EMAIL . '</strong></p>';
        echo '<p style="color:red;"><strong>IMPORTANT: Delete this file immediately from your server.</strong></p>';
    }
} catch (Throwable $e) {
    echo '<h2 style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</h2>';
}

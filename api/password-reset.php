<?php
require_once __DIR__ . '/../config.php';

apiCors();
verifyCsrfFromRequest();

$input = readJsonInput();
$action = strtolower(trim($input['action'] ?? ''));

if ($action === 'request') {
    $email = trim($input['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiJson(['success' => false, 'message' => 'Please provide a valid email address.'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiry = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');

        $pdo->prepare('UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?')
            ->execute([$token, $expiry, $user['id']]);

        require_once __DIR__ . '/../mailer.php';
        $resetLink = SITE_URL . '/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
        $subject = SITE_NAME . ' password reset request';
        $body = "Hello,\n\nWe received a request to reset your password for " . SITE_NAME . ".\n\nUse the link below to update your password:\n\n" . $resetLink . "\n\nThis link expires in one hour. If you did not request this, ignore this message.\n";
        sendMail($email, $subject, $body);
    }

    apiJson(['success' => true, 'message' => 'If this email is registered, a password reset link has been sent.']);
}

if ($action === 'reset') {
    $email = trim($input['email'] ?? '');
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';
    $confirm = $input['confirm_password'] ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$token) {
        apiJson(['success' => false, 'message' => 'Missing required reset information.'], 422);
    }
    if (strlen($password) < 8) {
        apiJson(['success' => false, 'message' => 'Password must contain at least 8 characters.'], 422);
    }
    if ($password !== $confirm) {
        apiJson(['success' => false, 'message' => 'Passwords do not match.'], 422);
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        apiJson(['success' => false, 'message' => 'Password must include an uppercase letter and a number.'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, reset_expiry FROM users WHERE email = ? AND reset_token = ? LIMIT 1');
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if (!$user) {
        apiJson(['success' => false, 'message' => 'The reset link is invalid or has already been used.'], 403);
    }
    if (new DateTime() > new DateTime($user['reset_expiry'])) {
        apiJson(['success' => false, 'message' => 'This reset link has expired.'], 403);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expiry = NULL, login_attempts = 0, locked_until = NULL WHERE id = ?')
        ->execute([$hash, $user['id']]);

    apiJson(['success' => true, 'message' => 'Password has been reset successfully.']);
}

apiJson(['success' => false, 'message' => 'Invalid password reset action.'], 400);

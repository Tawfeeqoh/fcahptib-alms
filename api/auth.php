<?php
require_once __DIR__ . '/../config.php';

apiCors();
$input = readJsonInput();
$action = $_GET['action'] ?? ($input['action'] ?? '');

if ($action === 'logout') {
    verifyCsrfFromRequest();
    $_SESSION = [];
    session_destroy();
    apiJson(['success' => true, 'message' => 'Signed out successfully.']);
}

if ($action === 'login') {
    verifyCsrfFromRequest();

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        apiJson(['success' => false, 'message' => 'Please fill in all required fields.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiJson(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, email, password_hash, role, first_name, last_name, title, status, login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        apiJson(['success' => false, 'message' => 'Email or password is incorrect.'], 401);
    }
    if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
        apiJson(['success' => false, 'message' => 'Account locked. Try again in 15 minutes.'], 423);
    }
    if ($user['status'] === 'pending') {
        apiJson(['success' => false, 'message' => 'Your account is awaiting admin approval.'], 403);
    }
    if ($user['status'] === 'suspended') {
        apiJson(['success' => false, 'message' => 'Your account has been suspended. Contact administrator.'], 403);
    }
    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int)$user['login_attempts'] + 1;
        if ($attempts >= 5) {
            $lockUntil = (new DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');
            $pdo->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?')->execute([$attempts, $lockUntil, $user['id']]);
        } else {
            $pdo->prepare('UPDATE users SET login_attempts = ? WHERE id = ?')->execute([$attempts, $user['id']]);
        }
        apiJson(['success' => false, 'message' => 'Email or password is incorrect.'], 401);
    }

    $pdo->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?')->execute([$user['id']]);
    $pdo->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)')->execute([$user['id'], 'login', $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['title'] = $user['title'];

    apiJson([
        'success' => true,
        'message' => 'Signed in successfully.',
        'redirect' => match($user['role']) {
            'admin' => '/admin/dashboard.html',
            'lecturer' => '/lecturer/dashboard.html',
            default => '/student/dashboard.html',
        },
        'user' => [
            'id' => (int)$user['id'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
        ],
    ]);
}

apiJson(['success' => false, 'message' => 'Unknown authentication action.'], 400);

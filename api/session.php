<?php
require_once __DIR__ . '/../config.php';

apiCors();

if (!isAuthenticated()) {
    apiJson([
        'success' => true,
        'authenticated' => false,
        'csrf_token' => csrfToken(),
    ]);
}

apiJson([
    'success' => true,
    'authenticated' => true,
    'csrf_token' => csrfToken(),
    'user' => [
        'id' => (int)$_SESSION['user_id'],
        'role' => $_SESSION['role'] ?? 'student',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'title' => $_SESSION['title'] ?? '',
    ],
]);

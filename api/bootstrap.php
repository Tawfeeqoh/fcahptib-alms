<?php
require_once __DIR__ . '/../config.php';

apiCors();

$departments = canonicalDepartments();
try {
    $stmt = db()->query("SELECT id, name, level_offered FROM departments ORDER BY name ASC");
    $departments = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Bootstrap departments fallback: ' . $e->getMessage());
}

apiJson([
    'success' => true,
    'csrf_token' => csrfToken(),
    'site' => [
        'name' => SITE_NAME,
        'backend_url' => SITE_URL,
        'frontend_url' => FRONTEND_URL,
    ],
    'departments' => $departments,
    'levels' => [
        ['id' => 1, 'name' => 'ND I', 'program' => 'ND'],
        ['id' => 2, 'name' => 'ND II', 'program' => 'ND'],
        ['id' => 3, 'name' => 'HND I', 'program' => 'HND'],
        ['id' => 4, 'name' => 'HND II', 'program' => 'HND'],
    ],
]);

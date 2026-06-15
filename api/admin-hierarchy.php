<?php
// ============================================================
// ALMS — Admin Hierarchy API
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

try {
    $rows = $db->query("
        SELECT f.name AS faculty_name, d.name AS department_name, d.level_offered, p.name AS programme_name, p.award
        FROM departments d
        LEFT JOIN faculties f ON d.faculty_id = f.id
        LEFT JOIN programmes p ON p.department_id = d.id
        ORDER BY f.name, d.name, p.award
    ")->fetchAll();

    apiJson([
        'success' => true,
        'hierarchy' => $rows
    ]);
} catch (PDOException $e) {
    error_log('Hierarchy API error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Database error.'], 500);
}

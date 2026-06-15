<?php
// ============================================================
// ALMS — Admin Department Management API
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

    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $level = trim($input['level_offered'] ?? 'ND & HND');

        if (empty($name)) {
            apiJson(['success' => false, 'message' => 'Department name is required.'], 422);
        }

        try {
            $stmt = $db->prepare("INSERT INTO departments (name, level_offered) VALUES (?, ?)");
            $stmt->execute([$name, $level]);

            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)");
            $log->execute([$_SESSION['user_id'], "created department: $name", $ip]);

            apiJson(['success' => true, 'message' => 'Department created successfully.']);
        } catch (PDOException $e) {
            error_log('Create department error: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'A department with this name already exists.'], 422);
        }
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $level = trim($input['level_offered'] ?? 'ND & HND');

        if ($id <= 0 || empty($name)) {
            apiJson(['success' => false, 'message' => 'Invalid parameters.'], 422);
        }

        try {
            $stmt = $db->prepare("UPDATE departments SET name = ?, level_offered = ? WHERE id = ?");
            $stmt->execute([$name, $level, $id]);

            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)");
            $log->execute([$_SESSION['user_id'], "updated department: $name", $ip]);

            apiJson(['success' => true, 'message' => 'Department updated successfully.']);
        } catch (PDOException $e) {
            error_log('Update department error: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Could not update department.'], 500);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown action.'], 400);
}

// GET Handler
$action = $_GET['action'] ?? '';

if ($action === 'list') {
    try {
        $departments = $db->query("
            SELECT d.id, d.name, d.level_offered,
                   (SELECT COUNT(*) FROM student_profiles WHERE department_id = d.id) AS student_count,
                   (SELECT COUNT(*) FROM lecturer_profiles WHERE primary_department_id = d.id) AS lecturer_count,
                   (SELECT COUNT(*) FROM courses WHERE department_id = d.id) AS course_count
            FROM departments d
            ORDER BY d.name ASC
        ")->fetchAll();

        apiJson(['success' => true, 'departments' => $departments]);
    } catch (PDOException $e) {
        error_log('List departments error: ' . $e->getMessage());
        apiJson(['success' => false, 'message' => 'Database error.'], 500);
    }
}

apiJson(['success' => false, 'message' => 'Invalid action.'], 400);

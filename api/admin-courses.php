<?php
// ============================================================
// ALMS — Admin Course Catalog API
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
        $code = strtoupper(trim($input['course_code'] ?? ''));
        $name = trim($input['course_name'] ?? '');
        $deptId = (int)($input['department_id'] ?? 0);
        $level = trim($input['level'] ?? 'ND');
        $lecturerId = !empty($input['lecturer_user_id']) ? (int)$input['lecturer_user_id'] : null;

        if (empty($code) || empty($name) || $deptId <= 0 || !in_array($level, ['ND', 'HND'])) {
            apiJson(['success' => false, 'message' => 'Please fill in all required course details.'], 422);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO courses (course_code, course_name, department_id, level) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $name, $deptId, $level]);
            $courseId = (int)$db->lastInsertId();

            if ($lecturerId) {
                $assign = $db->prepare("INSERT INTO lecturer_course_assignments (lecturer_id, course_id, department_id) VALUES (?, ?, ?)");
                $assign->execute([$lecturerId, $courseId, $deptId]);
            }

            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)");
            $log->execute([$_SESSION['user_id'], "created course: $code - $name", $ip]);

            $db->commit();
            apiJson(['success' => true, 'message' => 'Course created successfully.']);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Create course error: ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'Duplicate')) {
                apiJson(['success' => false, 'message' => 'A course with that code already exists.'], 422);
            }
            apiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $code = strtoupper(trim($input['course_code'] ?? ''));
        $name = trim($input['course_name'] ?? '');
        $deptId = (int)($input['department_id'] ?? 0);
        $level = trim($input['level'] ?? 'ND');
        $lecturerId = !empty($input['lecturer_user_id']) ? (int)$input['lecturer_user_id'] : null;

        if ($id <= 0 || empty($code) || empty($name) || $deptId <= 0 || !in_array($level, ['ND', 'HND'])) {
            apiJson(['success' => false, 'message' => 'Invalid parameters.'], 422);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE courses SET course_code = ?, course_name = ?, department_id = ?, level = ? WHERE id = ?");
            $stmt->execute([$code, $name, $deptId, $level, $id]);

            // Clear old assignments for this course
            $clear = $db->prepare("DELETE FROM lecturer_course_assignments WHERE course_id = ?");
            $clear->execute([$id]);

            if ($lecturerId) {
                $assign = $db->prepare("INSERT INTO lecturer_course_assignments (lecturer_id, course_id, department_id) VALUES (?, ?, ?)");
                $assign->execute([$lecturerId, $id, $deptId]);
            }

            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)");
            $log->execute([$_SESSION['user_id'], "updated course: $code - $name", $ip]);

            $db->commit();
            apiJson(['success' => true, 'message' => 'Course updated successfully.']);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Update course error: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown action.'], 400);
}

// GET Handler - List all courses with department and assigned lecturer details
try {
    $courses = $db->query("
        SELECT c.id, c.course_code, c.course_name, c.level, c.department_id, d.name AS department_name,
               lca.lecturer_id AS lecturer_user_id,
               CONCAT(u.title, ' ', u.first_name, ' ', u.last_name) AS lecturer_name
        FROM courses c
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN lecturer_course_assignments lca ON lca.course_id = c.id
        LEFT JOIN users u ON lca.lecturer_id = u.id
        ORDER BY c.course_code ASC
    ")->fetchAll();

    apiJson(['success' => true, 'courses' => $courses]);
} catch (PDOException $e) {
    error_log('List courses error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Database error.'], 500);
}

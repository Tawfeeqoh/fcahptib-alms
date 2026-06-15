<?php
// ============================================================
// ALMS — Admin Lecturer Management API
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

    if ($action === 'approve') {
        $lecId = (int)($input['lecturer_id'] ?? 0);
        if ($lecId > 0) {
            $db->beginTransaction();
            try {
                $stmt1 = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'lecturer'");
                $stmt1->execute([$lecId]);
                
                $stmt2 = $db->prepare("UPDATE lecturer_profiles SET approved_at = NOW() WHERE user_id = ?");
                $stmt2->execute([$lecId]);

                // Create notification
                $notif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Account Approved', 'Your lecturer credentials have been verified by the administrator. Welcome aboard!')");
                $notif->execute([$lecId]);

                $db->commit();
                apiJson(['success' => true, 'message' => 'Lecturer credentials approved and account activated.']);
            } catch (Exception $e) {
                $db->rollBack();
                apiJson(['success' => false, 'message' => 'Database error during approval.'], 500);
            }
        }
        apiJson(['success' => false, 'message' => 'Invalid lecturer ID.'], 422);
    }

    if ($action === 'suspend') {
        $lecId = (int)($input['lecturer_id'] ?? 0);
        if ($lecId > 0) {
            $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'lecturer'");
            $stmt->execute([$lecId]);
            apiJson(['success' => true, 'message' => 'Lecturer account suspended successfully.']);
        }
        apiJson(['success' => false, 'message' => 'Invalid lecturer ID.'], 422);
    }

    if ($action === 'unsuspend') {
        $lecId = (int)($input['lecturer_id'] ?? 0);
        if ($lecId > 0) {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'lecturer'");
            $stmt->execute([$lecId]);
            apiJson(['success' => true, 'message' => 'Lecturer account restored to active status.']);
        }
        apiJson(['success' => false, 'message' => 'Invalid lecturer ID.'], 422);
    }

    if ($action === 'assign_course') {
        $lecId = (int)($input['lecturer_id'] ?? 0);
        $courseId = (int)($input['course_id'] ?? 0);
        $deptId = (int)($input['department_id'] ?? 0);

        if ($lecId > 0 && $courseId > 0 && $deptId > 0) {
            try {
                $stmt = $db->prepare("INSERT INTO lecturer_course_assignments (lecturer_id, course_id, department_id) VALUES (?, ?, ?)");
                $stmt->execute([$lecId, $courseId, $deptId]);
                apiJson(['success' => true, 'message' => 'Course assigned successfully.']);
            } catch (PDOException $e) {
                apiJson(['success' => false, 'message' => 'Duplicate teaching portfolio assignment.'], 422);
            }
        }
        apiJson(['success' => false, 'message' => 'Invalid parameters.'], 422);
    }

    if ($action === 'remove_course') {
        $assignmentId = (int)($input['assignment_id'] ?? 0);
        if ($assignmentId > 0) {
            $stmt = $db->prepare("DELETE FROM lecturer_course_assignments WHERE id = ?");
            $stmt->execute([$assignmentId]);
            apiJson(['success' => true, 'message' => 'Course assignment deleted.']);
        }
        apiJson(['success' => false, 'message' => 'Invalid assignment ID.'], 422);
    }

    apiJson(['success' => false, 'message' => 'Unknown staff action.'], 400);
}

// GET handler
$pendingStaff = $db->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.title, u.created_at, d.name AS dept_name
    FROM users u
    JOIN lecturer_profiles lp ON u.id = lp.user_id
    JOIN departments d ON lp.primary_department_id = d.id
    WHERE u.role = 'lecturer' AND u.status = 'pending'
    ORDER BY u.created_at ASC
")->fetchAll();

$activeStaff = $db->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.title, u.status, lp.approved_at, d.name AS dept_name
    FROM users u
    JOIN lecturer_profiles lp ON u.id = lp.user_id
    JOIN departments d ON lp.primary_department_id = d.id
    WHERE u.role = 'lecturer' AND u.status IN ('active', 'suspended')
    ORDER BY u.first_name ASC, u.last_name ASC
")->fetchAll();

// Fetch course assignments for active staff
foreach ($activeStaff as &$lec) {
    $coursesAssigned = $db->prepare("
        SELECT lca.id AS assignment_id, c.course_code, d.name AS dept_name
        FROM lecturer_course_assignments lca
        JOIN courses c ON lca.course_id = c.id
        JOIN departments d ON lca.department_id = d.id
        WHERE lca.lecturer_id = ?
    ");
    $coursesAssigned->execute([$lec['id']]);
    $lec['assignments'] = $coursesAssigned->fetchAll();
}

$allCourses = $db->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll();
$allDepts = $db->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

apiJson([
    'success' => true,
    'pending_staff' => $pendingStaff,
    'active_staff' => $activeStaff,
    'courses' => $allCourses,
    'departments' => $allDepts
]);

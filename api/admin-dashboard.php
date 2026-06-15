<?php
// ============================================================
// ALMS — Admin Dashboard API
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

// Fetch System Stats
$stats = [];

// Total Students
$stdStmt = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
$stats['students'] = (int)$stdStmt->fetch()['total'];

// Total Lecturers
$lecStmt = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer'");
$stats['lecturers'] = (int)$lecStmt->fetch()['total'];

// Pending Approval Lecturers
$pendStmt = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer' AND status = 'pending'");
$stats['pending_lecturers'] = (int)$pendStmt->fetch()['total'];

// Total Courses
$crsStmt = $db->query("SELECT COUNT(*) AS total FROM courses");
$stats['courses'] = (int)$crsStmt->fetch()['total'];

// Total Departments
$deptStmt = $db->query("SELECT COUNT(*) AS total FROM departments");
$stats['departments'] = (int)$deptStmt->fetch()['total'];

// Fetch Recent Activities (Last 20)
$activities = [];
try {
    $actStmt = $db->query("
        SELECT a.action, a.ip_address, a.timestamp, u.first_name, u.last_name, u.role
        FROM activity_log a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.timestamp DESC LIMIT 20
    ");
    $activities = $actStmt->fetchAll();
} catch (PDOException $e) {
    // Activity log structure fallback
}

// Fetch Departments Overview (List all with Student & Lecturer Counts)
$deptsOverview = [];
try {
    $ovStmt = $db->query("
        SELECT d.id, d.name, d.level_offered,
               (SELECT COUNT(*) FROM student_profiles WHERE department_id = d.id) AS student_count,
               (SELECT COUNT(*) FROM lecturer_profiles WHERE primary_department_id = d.id) AS lecturer_count
        FROM departments d
        ORDER BY d.name ASC
    ");
    $deptsOverview = $ovStmt->fetchAll();
} catch (PDOException $e) {
    //
}

apiJson([
    'success' => true,
    'stats' => $stats,
    'activities' => $activities,
    'departments_overview' => $deptsOverview
]);

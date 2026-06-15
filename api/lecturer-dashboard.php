<?php
// ============================================================
// ALMS — Lecturer Dashboard API
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

if ($_SESSION['role'] !== 'lecturer') {
    apiJson(['success' => false, 'message' => 'Forbidden.'], 403);
}

$db = db();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Check verified status
$statusStmt = $db->prepare("SELECT status, title, first_name, last_name FROM users WHERE id = ?");
$statusStmt->execute([$userId]);
$userStatus = $statusStmt->fetch();

if ($userStatus && $userStatus['status'] === 'pending') {
    apiJson([
        'success' => true,
        'status' => 'pending',
        'message' => 'Account verification pending.',
        'user' => [
            'title' => $userStatus['title'],
            'first_name' => $userStatus['first_name'],
            'last_name' => $userStatus['last_name']
        ]
    ]);
}

// Fetch Assigned Courses
$assignedStmt = $db->prepare("
    SELECT lca.id AS assignment_id, c.id AS course_id, c.course_code, c.course_name, c.level, d.name AS dept_name, d.id AS dept_id
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN departments d ON lca.department_id = d.id
    WHERE lca.lecturer_id = ?
");
$assignedStmt->execute([$userId]);
$assignedCourses = $assignedStmt->fetchAll();

// Compute Aggregate Stats
$studentCount = 0;
$departmentsAssigned = [];
foreach ($assignedCourses as $ac) {
    $departmentsAssigned[] = $ac['dept_id'];
}
$departmentsAssigned = array_unique($departmentsAssigned);

if (!empty($departmentsAssigned)) {
    $inClause = implode(',', array_map('intval', $departmentsAssigned));
    $stdStmt = $db->query("SELECT COUNT(*) AS total FROM student_profiles WHERE department_id IN ($inClause)");
    $studentCount = (int)$stdStmt->fetch()['total'];
}

// Fetch Aggregate Anonymized Wellness Insights
$wellnessStats = ['express' => 0, 'standard' => 0, 'deep' => 0];
$stressStats = ['low' => 0, 'medium' => 0, 'high' => 0];
$attentionStats = ['low' => 0, 'medium' => 0, 'high' => 0];

if (!empty($departmentsAssigned)) {
    $inClause = implode(',', array_map('intval', $departmentsAssigned));
    
    // Pace distribution
    $paceStmt = $db->query("
        SELECT current_pace, COUNT(*) as count 
        FROM student_profiles 
        WHERE department_id IN ($inClause) 
        GROUP BY current_pace
    ");
    while ($r = $paceStmt->fetch()) {
        $wellnessStats[$r['current_pace']] = (int)$r['count'];
    }

    // Stress & Attention distribution (logs from last 7 days)
    $logStmt = $db->query("
        SELECT stress_level, attention_span, COUNT(*) as count 
        FROM student_wellness_logs wl
        JOIN student_profiles sp ON wl.user_id = sp.user_id
        WHERE sp.department_id IN ($inClause) AND wl.logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY stress_level, attention_span
    ");
    while ($r = $logStmt->fetch()) {
        $stressStats[$r['stress_level']] += (int)$r['count'];
        $attentionStats[$r['attention_span']] += (int)$r['count'];
    }
}

// Recent Submissions
$submissions = [];
if (!empty($assignedCourses)) {
    $courseIds = array_column($assignedCourses, 'course_id');
    $crsIn = implode(',', array_map('intval', $courseIds));
    $subStmt = $db->query("
        SELECT s.id, s.grade, s.submitted_at, a.title AS assignment_title, c.course_code, u.first_name, u.last_name
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        JOIN users u ON s.student_id = u.id
        WHERE c.id IN ($crsIn)
        ORDER BY s.submitted_at DESC LIMIT 5
    ");
    $submissions = $subStmt->fetchAll();
}

apiJson([
    'success' => true,
    'status' => 'active',
    'assigned_courses' => $assignedCourses,
    'student_count' => $studentCount,
    'wellness_stats' => $wellnessStats,
    'stress_stats' => $stressStats,
    'attention_stats' => $attentionStats,
    'submissions' => $submissions
]);

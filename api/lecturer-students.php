<?php
// ============================================================
// ALMS — Lecturer Students Roster API
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
$lecturerId = $_SESSION['user_id'];

try {
    // Fetch students mapped to the lecturer's departments
    // Calculates completion percentage across the lecturer's courses and fetches the latest stress level
    $stmt = $db->prepare("
        SELECT 
            u.id, u.first_name, u.last_name, u.email, 
            sp.matric_number, sp.level_id, sp.vark_style, sp.current_pace,
            d.name AS department_name,
            (SELECT wl.stress_level 
             FROM student_wellness_logs wl 
             WHERE wl.user_id = u.id 
             ORDER BY wl.logged_at DESC LIMIT 1) AS stress_level,
            (SELECT ROUND(COALESCE(
                (COUNT(DISTINCT lp.lesson_id) * 100.0) / NULLIF(COUNT(DISTINCT les.id), 0),
                0
             )) 
             FROM lessons les 
             JOIN lecturer_course_assignments lca2 ON lca2.course_id = les.course_id 
             LEFT JOIN lesson_progress lp ON lp.lesson_id = les.id AND lp.student_id = u.id
             WHERE lca2.lecturer_id = ? AND lca2.department_id = sp.department_id
            ) AS progress_pct
        FROM lecturer_course_assignments lca
        JOIN student_profiles sp ON sp.department_id = lca.department_id
        JOIN users u ON sp.user_id = u.id
        JOIN departments d ON sp.department_id = d.id
        WHERE lca.lecturer_id = ?
        GROUP BY u.id, sp.matric_number, sp.level_id, sp.vark_style, sp.current_pace, d.name
        ORDER BY d.name ASC, u.last_name ASC
    ");
    
    $stmt->execute([$lecturerId, $lecturerId]);
    $students = $stmt->fetchAll();

    // Standardize vark_style and current_pace for front-end rendering
    foreach ($students as &$s) {
        $s['vark_style'] = $s['vark_style'] ? strtoupper($s['vark_style']) : 'R';
        $s['current_pace'] = $s['current_pace'] ? strtolower($s['current_pace']) : 'standard';
        $s['stress_level'] = $s['stress_level'] ? strtolower($s['stress_level']) : 'low';
        $s['progress_pct'] = $s['progress_pct'] !== null ? (int)$s['progress_pct'] : 0;
    }

    apiJson([
        'success' => true,
        'students' => $students
    ]);
} catch (PDOException $e) {
    error_log('Lecturer students API error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Database error.'], 500);
}

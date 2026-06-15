<?php
// ============================================================
// ALMS — Admin Analytics API
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
    // Totals
    $totals = [];
    $totals['students'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totals['lessons_completed'] = (int)$db->query("SELECT COUNT(*) FROM lesson_progress WHERE completed = 1")->fetchColumn();
    $totals['quiz_attempts'] = (int)$db->query("SELECT COUNT(*) FROM quiz_attempts")->fetchColumn();
    $totals['submissions'] = (int)$db->query("SELECT COUNT(*) FROM assignment_submissions")->fetchColumn();

    // VARK Distribution
    $varkRaw = $db->query("
        SELECT vark_style, COUNT(*) as count 
        FROM student_profiles 
        WHERE onboarded = 1 AND vark_style IS NOT NULL AND vark_style != ''
        GROUP BY vark_style
    ")->fetchAll();
    
    $varkMap = ['v' => 'Visual', 'a' => 'Auditory', 'r' => 'Reading/Writing', 'k' => 'Kinesthetic', 'vark' => 'Multimodal'];
    $varkDist = [];
    foreach ($varkRaw as $v) {
        $key = strtolower(trim($v['vark_style']));
        $label = $varkMap[$key] ?? ucfirst($key);
        $varkDist[] = ['label' => $label, 'count' => (int)$v['count']];
    }

    // Pace Distribution
    $paceRaw = $db->query("
        SELECT current_pace, COUNT(*) as count 
        FROM student_profiles 
        WHERE onboarded = 1 AND current_pace IS NOT NULL AND current_pace != ''
        GROUP BY current_pace
    ")->fetchAll();
    
    $paceMap = ['express' => 'Express', 'standard' => 'Standard', 'deep' => 'Deep Dive'];
    $paceDist = [];
    foreach ($paceRaw as $p) {
        $key = strtolower(trim($p['current_pace']));
        $label = $paceMap[$key] ?? ucfirst($key);
        $paceDist[] = ['label' => $label, 'count' => (int)$p['count']];
    }

    // Wellness Distribution (WHO-5 Index classification)
    $wellRaw = $db->query("
        SELECT 
            SUM(CASE WHEN who5_score <= 12 THEN 1 ELSE 0 END) AS low,
            SUM(CASE WHEN who5_score > 12 AND who5_score <= 17 THEN 1 ELSE 0 END) AS med,
            SUM(CASE WHEN who5_score > 17 THEN 1 ELSE 0 END) AS high
        FROM student_profiles
        WHERE onboarded = 1
    ")->fetch();
    
    $wellnessDist = [
        ['label' => 'Thriving (High)', 'count' => (int)($wellRaw['high'] ?? 0)],
        ['label' => 'Moderate/Good', 'count' => (int)($wellRaw['med'] ?? 0)],
        ['label' => 'Needs Support (Low)', 'count' => (int)($wellRaw['low'] ?? 0)]
    ];

    // Top Courses
    $topCourses = $db->query("
        SELECT c.course_code, c.course_name, d.name AS department_name,
               (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id WHERE l.course_id = c.id AND lp.completed = 1) AS lesson_completions,
               (SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE q.course_id = c.id) AS quiz_attempts
        FROM courses c
        LEFT JOIN departments d ON c.department_id = d.id
        ORDER BY (lesson_completions + quiz_attempts) DESC
        LIMIT 10
    ")->fetchAll();

    apiJson([
        'success' => true,
        'totals' => $totals,
        'vark_distribution' => $varkDist,
        'pace_distribution' => $paceDist,
        'wellness_distribution' => $wellnessDist,
        'top_courses' => $topCourses
    ]);
} catch (PDOException $e) {
    error_log('Admin Analytics API error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Database error.'], 500);
}

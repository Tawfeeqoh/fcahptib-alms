<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';

apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$profile = studentProfile($userId);
if (!$profile) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

if ((int)$profile['onboarded'] === 0) {
    apiJson(['success' => false, 'message' => 'Onboarding required.', 'onboarding_required' => true], 403);
}

$db = db();
$levelName = programFromLevelId((int)$profile['level_id']);

$wellness = ['attention_span' => 'medium', 'stress_level' => 'low'];
$wellnessStmt = $db->prepare('SELECT attention_span, stress_level FROM student_wellness_logs WHERE user_id = ? ORDER BY logged_at DESC LIMIT 1');
$wellnessStmt->execute([$userId]);
if ($row = $wellnessStmt->fetch()) {
    $wellness = $row;
}

$gamification = upsertGamificationProfile($userId);

$recommendations = [];
$recStmt = $db->prepare('SELECT id, title, body, priority, created_at FROM learning_recommendations WHERE student_id = ? AND is_resolved = 0 ORDER BY FIELD(priority, "high", "medium", "low"), created_at DESC LIMIT 5');
$recStmt->execute([$userId]);
$recommendations = $recStmt->fetchAll();

$coursesStmt = $db->prepare(
    'SELECT c.id, c.course_code, c.course_name,
            (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id WHERE lp.student_id = ? AND l.course_id = c.id) AS completed_lessons,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS total_lessons
     FROM courses c
     WHERE c.department_id = ? AND c.level = ?'
);
$coursesStmt->execute([$userId, $profile['department_id'], $levelName]);
$courses = $coursesStmt->fetchAll();

$totalAllLessons = 0;
$completedAllLessons = 0;
foreach ($courses as &$course) {
    $course['completed_lessons'] = (int)$course['completed_lessons'];
    $course['total_lessons'] = (int)$course['total_lessons'];
    $course['progress'] = $course['total_lessons'] > 0 ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
    $totalAllLessons += $course['total_lessons'];
    $completedAllLessons += $course['completed_lessons'];
}
$overallProgress = $totalAllLessons > 0 ? round(($completedAllLessons / $totalAllLessons) * 100) : 0;

$assignmentsStmt = $db->prepare(
    'SELECT a.id, a.title, a.due_date, c.course_code,
            (SELECT id FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) AS submission_id
     FROM assignments a
     JOIN courses c ON a.course_id = c.id
     WHERE c.department_id = ? AND c.level = ? AND a.due_date >= NOW()
     ORDER BY a.due_date ASC LIMIT 6'
);
$assignmentsStmt->execute([$userId, $profile['department_id'], $levelName]);
$assignments = $assignmentsStmt->fetchAll();

$assessmentStmt = $db->prepare('SELECT AVG(percentage) AS avg_pct FROM quiz_attempts WHERE student_id = ?');
$assessmentStmt->execute([$userId]);
$assessmentAverage = $assessmentStmt->fetch()['avg_pct'] ?? null;
$assessmentAverage = $assessmentAverage !== null ? round((float)$assessmentAverage) : null;

$attendanceSummary = ['present' => 0, 'excused' => 0, 'absent' => 0];
$attendanceDays = [];
$attendanceConnected = false;
$today = (int)date('d');
$daysInMonth = (int)date('t');

try {
    $attendanceStmt = $db->prepare(
        'SELECT DAY(recorded_on) AS day_num, status
         FROM attendance_records
         WHERE student_id = ?
           AND recorded_on >= DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")
           AND recorded_on <= LAST_DAY(CURRENT_DATE())'
    );
    $attendanceStmt->execute([$userId]);
    $attendanceRows = $attendanceStmt->fetchAll();
    foreach ($attendanceRows as $row) {
        $status = $row['status'] ?: 'unrecorded';
        $day = (int)$row['day_num'];
        if (isset($attendanceSummary[$status])) {
            $attendanceSummary[$status]++;
        }
        $attendanceDays[$day] = $status;
        $attendanceConnected = true;
    }
} catch (PDOException $e) {
    $attendanceConnected = false;
}

for ($day = 1; $day <= $daysInMonth; $day++) {
    if (!isset($attendanceDays[$day])) {
        $attendanceDays[$day] = $day > $today ? 'future' : 'unrecorded';
    }
}

$attendancePercentage = ($attendanceSummary['present'] + $attendanceSummary['absent']) > 0
    ? round(($attendanceSummary['present'] / ($attendanceSummary['present'] + $attendanceSummary['absent'])) * 100)
    : null;

apiJson([
    'success' => true,
    'profile' => [
        'matric_number' => $profile['matric_number'],
        'level_id' => (int)$profile['level_id'],
        'level_name' => levelNameFromId((int)$profile['level_id']),
        'department_name' => $profile['department_name'],
        'vark_style' => $profile['vark_style'],
        'current_pace' => $profile['current_pace'],
        'who5_score' => $profile['who5_score'],
    ],
    'wellness' => $wellness,
    'gamification' => [
        'total_xp' => (int)$gamification['total_xp'],
        'level' => (int)$gamification['level'],
        'current_streak' => (int)$gamification['current_streak'],
        'longest_streak' => (int)$gamification['longest_streak'],
    ],
    'recommendations' => $recommendations,
    'courses' => $courses,
    'overall_progress' => $overallProgress,
    'assignments' => $assignments,
    'assessment_average' => $assessmentAverage,
    'attendance_summary' => $attendanceSummary,
    'attendance_days' => array_map(function ($day, $status) {
        return ['day' => $day, 'status' => $status];
    }, array_keys($attendanceDays), $attendanceDays),
    'attendance_percentage' => $attendancePercentage,
]);

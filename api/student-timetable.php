<?php
/**
 * api/student-timetable.php
 * Returns the timetable for the authenticated student's academic level.
 */
require_once __DIR__ . '/../config.php';

apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId  = (int)($_SESSION['user_id'] ?? 0);
$profile = studentProfile($userId);

if (!$profile) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

// Resolve the student's level label (ND I / ND II / HND I / HND II)
$level = programFromLevelId((int)($profile['level_id'] ?? 0));
if (!$level) {
    apiJson(['success' => false, 'message' => 'Student level not set.'], 422);
}

try {
    $db   = db();
    $stmt = $db->prepare(
        'SELECT id, day, start_hour, duration_hours, course_code, course_name, venue, lecturer_name
         FROM course_timetable
         WHERE level = ?
         ORDER BY FIELD(day, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_hour ASC'
    );
    $stmt->execute([$level]);
    $schedule = $stmt->fetchAll();

    apiJson([
        'success'  => true,
        'level'    => $level,
        'schedule' => $schedule,
    ]);
} catch (Throwable $e) {
    error_log('student-timetable: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Could not load timetable.'], 500);
}

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';

apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$courseId = (int)($_GET['course_id'] ?? 0);
$lessonId = (int)($_GET['lesson_id'] ?? 0);

if ($courseId <= 0) {
    apiJson(['success' => false, 'message' => 'Course ID is required.'], 422);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

$studentStmt = $db->prepare('SELECT department_id, current_pace FROM student_profiles WHERE user_id = ?');
$studentStmt->execute([$userId]);
$studentProfile = $studentStmt->fetch();
if (!$studentProfile) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

$courseStmt = $db->prepare('SELECT id, course_code, course_name FROM courses WHERE id = ? AND department_id = ?');
$courseStmt->execute([$courseId, $studentProfile['department_id']]);
$course = $courseStmt->fetch();
if (!$course) {
    apiJson(['success' => false, 'message' => 'Course not found or access denied.'], 404);
}

$lessonsStmt = $db->prepare(
    'SELECT l.id, l.title, l.sequence_order,
            (SELECT id FROM lesson_progress WHERE student_id = ? AND lesson_id = l.id) AS is_completed
     FROM lessons l
     WHERE l.course_id = ?
     ORDER BY l.sequence_order ASC'
);
$lessonsStmt->execute([$userId, $courseId]);
$lessons = $lessonsStmt->fetchAll();

$activeLesson = null;
if ($lessonId > 0) {
    foreach ($lessons as $lesson) {
        if ((int)$lesson['id'] === $lessonId) {
            $activeLesson = $lesson;
            break;
        }
    }
}

if (!$activeLesson && !empty($lessons)) {
    foreach ($lessons as $lesson) {
        if (empty($lesson['is_completed'])) {
            $activeLesson = $lesson;
            break;
        }
    }
}

if (!$activeLesson && !empty($lessons)) {
    $activeLesson = $lessons[0];
}

if (!$activeLesson) {
    apiJson(['success' => true, 'course' => $course, 'lessons' => [], 'active_lesson' => null, 'current_pace' => $studentProfile['current_pace'] ?? 'standard']);
}

$dataStmt = $db->prepare('SELECT id, title, content_standard, content_express, content_deep, sequence_order FROM lessons WHERE id = ?');
$dataStmt->execute([(int)$activeLesson['id']]);
$lessonData = $dataStmt->fetch();

if (!$lessonData) {
    apiJson(['success' => false, 'message' => 'Lesson could not be loaded.'], 404);
}

apiJson([
    'success' => true,
    'course' => $course,
    'lessons' => $lessons,
    'active_lesson' => $lessonData,
    'current_pace' => $studentProfile['current_pace'] ?? 'standard',
]);

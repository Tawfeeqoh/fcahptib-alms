<?php
// ============================================================
// ALMS — Student Assignments API
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db = db();

// 1. Fetch student profile and level
$stmt = $db->prepare('SELECT level_id, department_id FROM student_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch();
if (!$profile) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

$levelName = ($profile['level_id'] <= 2) ? 'ND' : 'HND';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfFromRequest();

    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $submittedText = trim($_POST['submitted_text'] ?? '');
    $filePath = '';

    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['pdf', 'doc', 'docx', 'zip', 'txt', 'png', 'jpg', 'jpeg'];
        $originalName = basename($_FILES['assignment_file']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $maxBytes = 8 * 1024 * 1024;

        if (!in_array($ext, $allowedExt, true) || $_FILES['assignment_file']['size'] > $maxBytes) {
            apiJson(['success' => false, 'message' => 'Invalid file upload.'], 422);
        }

        $uploadDir = realpath(__DIR__ . '/../uploads/assignments');
        if (!$uploadDir) {
            apiJson(['success' => false, 'message' => 'Upload path unavailable.'], 500);
        }

        $safeName = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($_FILES['assignment_file']['tmp_name'], $target)) {
            apiJson(['success' => false, 'message' => 'Could not save file upload.'], 500);
        }

        $filePath = '/uploads/assignments/' . $safeName;
    }

    if ($assignmentId <= 0 || ($submittedText === '' && $filePath === '')) {
        apiJson(['success' => false, 'message' => 'Provide text or upload a file.'], 422);
    }

    try {
        $check = $db->prepare('SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?');
        $check->execute([$assignmentId, $userId]);
        if ($check->fetch()) {
            apiJson(['success' => false, 'message' => 'Assignment already submitted.'], 409);
        }

        $insert = $db->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, file_path, submitted_text) VALUES (?, ?, ?, ?)');
        $insert->execute([$assignmentId, $userId, $filePath, $submittedText]);

        awardXp($userId, 35, 'assignment_submit', (int)$db->lastInsertId(), 'Assignment submitted');
        apiJson(['success' => true, 'message' => 'Assignment submitted successfully.']);
    } catch (PDOException $e) {
        apiJson(['success' => false, 'message' => 'Could not submit assignment.'], 500);
    }
}

$assignmentsStmt = $db->prepare(
    'SELECT a.id, a.title, a.description, a.due_date, c.course_code, c.course_name,
            s.file_path, s.submitted_text, s.grade, s.feedback, s.submitted_at
     FROM assignments a
     JOIN courses c ON a.course_id = c.id
     LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
     WHERE c.department_id = ? AND c.level = ?
     ORDER BY a.due_date ASC'
);
$assignmentsStmt->execute([$userId, $profile['department_id'], $levelName]);
$assignments = $assignmentsStmt->fetchAll();

apiJson(['success' => true, 'assignments' => $assignments]);

<?php
// ============================================================
// ALMS — Student Assignment Upload Submission API
// ============================================================
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/learning-engine.php';

apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

verifyCsrfFromRequest();

$userId = (int)($_SESSION['user_id'] ?? 0);
$assignmentId = (int)($_POST['assignment_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$filePath = '';

if ($assignmentId <= 0) {
    apiJson(['success' => false, 'message' => 'Invalid assignment ID.'], 422);
}

$db = db();

// Check if student profile exists
$stmt = $db->prepare('SELECT user_id FROM student_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

// Check if already submitted
$check = $db->prepare('SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?');
$check->execute([$assignmentId, $userId]);
if ($check->fetch()) {
    apiJson(['success' => false, 'message' => 'You have already submitted this assignment.'], 409);
}

// Handle file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    apiJson(['success' => false, 'message' => 'Please select a file to upload.'], 422);
}

$allowedExt = ['pdf', 'doc', 'docx', 'zip'];
$originalName = basename($_FILES['file']['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$maxBytes = 25 * 1024 * 1024; // 25 MB limit matching assignments.html

if (!in_array($ext, $allowedExt, true)) {
    apiJson(['success' => false, 'message' => 'Invalid file extension. Only PDF, DOC, DOCX, and ZIP files are allowed.'], 422);
}

if ($_FILES['file']['size'] > $maxBytes) {
    apiJson(['success' => false, 'message' => 'File size exceeds the 25 MB limit.'], 422);
}

$uploadDir = realpath(__DIR__ . '/../../uploads/assignments');
if (!$uploadDir) {
    // Attempt to create it if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/assignments';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $uploadDir = realpath($uploadDir);
}

if (!$uploadDir) {
    apiJson(['success' => false, 'message' => 'Upload directory is not accessible.'], 500);
}

$safeName = bin2hex(random_bytes(12)) . '.' . $ext;
$target = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    apiJson(['success' => false, 'message' => 'Failed to save the uploaded file.'], 500);
}

$filePath = $safeName;

try {
    $insert = $db->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, file_path, submitted_text) VALUES (?, ?, ?, ?)');
    $insert->execute([$assignmentId, $userId, $filePath, $notes]);
    $submissionId = (int)$db->lastInsertId();

    // Award XP (35 XP for assignment submission)
    awardXp($userId, 35, 'assignment_submit', $submissionId, 'Submitted assignment ' . $assignmentId);

    apiJson([
        'success' => true,
        'message' => 'Assignment submitted successfully.',
        'submission_id' => $submissionId
    ]);
} catch (PDOException $e) {
    apiJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

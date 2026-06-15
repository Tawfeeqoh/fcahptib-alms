<?php
// ============================================================
// ALMS — Lecturer Course Builder API
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

// Fetch all courses assigned to this lecturer
$assignedCourses = $db->prepare("
    SELECT c.id, c.course_code, c.course_name
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    WHERE lca.lecturer_id = ?
    ORDER BY c.course_code ASC
");
$assignedCourses->execute([$userId]);
$courses = $assignedCourses->fetchAll();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($course_id === 0 && !empty($courses)) {
    $course_id = (int)$courses[0]['id'];
}

$ownsCourse = false;
foreach ($courses as $c) {
    if ((int)$c['id'] === $course_id) {
        $ownsCourse = true;
        break;
    }
}

if ($method === 'POST') {
    verifyCsrfFromRequest();
    
    // Check if the user is uploading files (multipart/form-data)
    $input = readJsonInput();
    if (empty($input)) {
        // If content is not JSON, it could be standard POST data from form submit or file upload
        $input = $_POST;
    }

    $course_id = (int)($input['course_id'] ?? 0);
    $ownsCourse = false;
    foreach ($courses as $c) {
        if ((int)$c['id'] === $course_id) {
            $ownsCourse = true;
            break;
        }
    }

    if (!$ownsCourse) {
        apiJson(['success' => false, 'message' => 'Unauthorized course access.'], 403);
    }

    $action = $input['action'] ?? '';

    if ($action === 'upload_material') {
        $title = trim($input['title'] ?? '');
        $text = trim($input['content_text'] ?? '');
        $file_path = '/uploads/materials/generic.txt';

        if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            // Ensure directory exists
            $uploadDir = __DIR__ . '/../uploads/materials/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = basename($_FILES['material_file']['name']);
            $file_path = '/uploads/materials/' . time() . '_' . $fileName;
            move_uploaded_file($_FILES['material_file']['tmp_name'], __DIR__ . '/..' . $file_path);
        }

        if (!empty($title) && (!empty($text) || $file_path !== '/uploads/materials/generic.txt')) {
            $stmt = $db->prepare("INSERT INTO course_materials (course_id, title, file_path, content_text) VALUES (?, ?, ?, ?)");
            $stmt->execute([$course_id, $title, $file_path, $text]);
            apiJson(['success' => true, 'message' => 'Material uploaded successfully.']);
        } else {
            apiJson(['success' => false, 'message' => 'Please fill in all required fields.'], 422);
        }
    }

    if ($action === 'add_lesson') {
        $title = trim($input['title'] ?? '');
        $std = trim($input['content_standard'] ?? '');
        $exp = trim($input['content_express'] ?? '');
        $deep = trim($input['content_deep'] ?? '');
        $seq = (int)($input['sequence_order'] ?? 1);

        if (!empty($title) && !empty($std) && !empty($exp) && !empty($deep)) {
            $stmt = $db->prepare("
                INSERT INTO lessons (course_id, title, content_standard, content_express, content_deep, sequence_order) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$course_id, $title, $std, $exp, $deep, $seq]);
            apiJson(['success' => true, 'message' => 'Lesson module added successfully.']);
        } else {
            apiJson(['success' => false, 'message' => 'Please fill in all required fields.'], 422);
        }
    }

    if ($action === 'delete_material') {
        $matId = (int)($input['material_id'] ?? 0);
        if ($matId > 0) {
            $stmt = $db->prepare("DELETE FROM course_materials WHERE id = ? AND course_id = ?");
            $stmt->execute([$matId, $course_id]);
            apiJson(['success' => true, 'message' => 'Material removed from index.']);
        } else {
            apiJson(['success' => false, 'message' => 'Invalid material identifier.'], 422);
        }
    }

    if ($action === 'delete_lesson') {
        $lesId = (int)($input['lesson_id'] ?? 0);
        if ($lesId > 0) {
            $stmt = $db->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
            $stmt->execute([$lesId, $course_id]);
            apiJson(['success' => true, 'message' => 'Lesson module deleted.']);
        } else {
            apiJson(['success' => false, 'message' => 'Invalid lesson identifier.'], 422);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown course builder action.'], 400);
}

// GET handler
$materials = [];
$lessons = [];
if ($ownsCourse) {
    $matStmt = $db->prepare("SELECT id, title, file_path, uploaded_at FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC");
    $matStmt->execute([$course_id]);
    $materials = $matStmt->fetchAll();

    $lesStmt = $db->prepare("SELECT id, title, sequence_order FROM lessons WHERE course_id = ? ORDER BY sequence_order ASC");
    $lesStmt->execute([$course_id]);
    $lessons = $lesStmt->fetchAll();
}

apiJson([
    'success' => true,
    'courses' => $courses,
    'active_course_id' => $course_id,
    'owns_course' => $ownsCourse,
    'materials' => $materials,
    'lessons' => $lessons
]);

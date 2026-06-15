<?php
// ============================================================
// ALMS — Lecturer Assignments API
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
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    verifyCsrfFromRequest();
    $input = readJsonInput();
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        $courseId = (int)($input['course_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $desc = trim($input['description'] ?? '');
        $dueDate = trim($input['due_date'] ?? '');
        $maxPoints = isset($input['max_points']) ? (int)$input['max_points'] : 100;

        if ($courseId <= 0 || empty($title) || empty($dueDate)) {
            apiJson(['success' => false, 'message' => 'Please fill in all required fields.'], 422);
        }

        // Verify that this lecturer is assigned to the course
        $chk = $db->prepare("SELECT id FROM lecturer_course_assignments WHERE lecturer_id = ? AND course_id = ? LIMIT 1");
        $chk->execute([$lecturerId, $courseId]);
        if (!$chk->fetch()) {
            apiJson(['success' => false, 'message' => 'You are not assigned to this course.'], 403);
        }

        try {
            $stmt = $db->prepare("INSERT INTO assignments (course_id, title, description, max_points, due_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$courseId, $title, $desc, $maxPoints, $dueDate]);

            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)");
            $log->execute([$lecturerId, "created assignment: $title", $ip]);

            apiJson(['success' => true, 'message' => 'Assignment created successfully.']);
        } catch (PDOException $e) {
            error_log('Create assignment error: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    if ($action === 'grade') {
        $subId = (int)($input['submission_id'] ?? 0);
        $grade = trim($input['grade'] ?? '');
        $feedback = trim($input['feedback'] ?? '');

        if ($subId <= 0 || $grade === '') {
            apiJson(['success' => false, 'message' => 'Please enter a grade.'], 422);
        }

        // Verify that the submission belongs to a course taught by this lecturer
        $chk = $db->prepare("
            SELECT s.student_id, s.assignment_id, c.course_name, a.title AS assignment_title
            FROM assignment_submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN courses c ON a.course_id = c.id
            JOIN lecturer_course_assignments lca ON lca.course_id = a.course_id
            WHERE s.id = ? AND lca.lecturer_id = ?
            LIMIT 1
        ");
        $chk->execute([$subId, $lecturerId]);
        $sub = $chk->fetch();

        if (!$sub) {
            apiJson(['success' => false, 'message' => 'Submission not found or unauthorized.'], 403);
        }

        $db->beginTransaction();
        try {
            // Update grade and feedback
            $stmt = $db->prepare("UPDATE assignment_submissions SET grade = ?, feedback = ? WHERE id = ?");
            $stmt->execute([$grade, $feedback, $subId]);

            // Send notification to student
            $notifMsg = "Your submission for assignment \"" . $sub['assignment_title'] . "\" in " . $sub['course_name'] . " has been graded: " . $grade;
            $notif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Assignment Graded', ?)");
            $notif->execute([$sub['student_id'], $notifMsg]);

            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)");
            $log->execute([$lecturerId, "graded submission id $subId with grade: $grade", $ip]);

            $db->commit();
            apiJson(['success' => true, 'message' => 'Grade saved successfully.']);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Grade submission error: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown action.'], 400);
}

// GET Handler - List all assignments for lecturer's courses
try {
    $stmt = $db->prepare("
        SELECT a.id, a.course_id, a.title, a.description, a.due_date, a.max_points,
               c.course_code, c.course_name,
               (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN lecturer_course_assignments lca ON lca.course_id = c.id
        WHERE lca.lecturer_id = ?
        ORDER BY a.due_date DESC
    ");
    $stmt->execute([$lecturerId]);
    $assignments = $stmt->fetchAll();

    // Fetch submissions for each assignment
    foreach ($assignments as &$a) {
        $subStmt = $db->prepare("
            SELECT s.id, s.file_path, s.submitted_text, s.grade, s.feedback, s.submitted_at,
                   u.first_name, u.last_name, sp.matric_number
            FROM assignment_submissions s
            JOIN users u ON s.student_id = u.id
            JOIN student_profiles sp ON sp.user_id = u.id
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $subStmt->execute([$a['id']]);
        $a['submissions'] = $subStmt->fetchAll();
    }

    apiJson([
        'success' => true,
        'assignments' => $assignments
    ]);
} catch (PDOException $e) {
    error_log('List lecturer assignments error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Database error.'], 500);
}

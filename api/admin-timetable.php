<?php
/**
 * api/admin-timetable.php
 * Admin CRUD for the course_timetable table.
 *
 * GET  ?level=ND+I          → list all slots for a level
 * POST action=add            → insert a new slot
 * POST action=update         → update an existing slot
 * POST action=delete         → delete a slot by id
 * POST action=clear_level    → wipe all slots for a level
 */
require_once __DIR__ . '/../config.php';

apiCors();

if (!isAuthenticated() || ($_SESSION['role'] ?? '') !== 'admin') {
    apiJson(['success' => false, 'message' => 'Admin access required.'], 403);
}

$adminId = (int)$_SESSION['user_id'];
$db      = db();

// ── GET: list slots for a level ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $level = trim($_GET['level'] ?? '');
    $validLevels = ['ND I', 'ND II', 'HND I', 'HND II'];

    if (!in_array($level, $validLevels, true)) {
        apiJson(['success' => false, 'message' => 'Invalid level.'], 422);
    }

    try {
        $stmt = $db->prepare(
            'SELECT id, level, day, start_hour, duration_hours, course_code, course_name, venue, lecturer_name, updated_at
             FROM course_timetable
             WHERE level = ?
             ORDER BY FIELD(day,"Monday","Tuesday","Wednesday","Thursday","Friday"), start_hour ASC'
        );
        $stmt->execute([$level]);
        apiJson(['success' => true, 'level' => $level, 'slots' => $stmt->fetchAll()]);
    } catch (Throwable $e) {
        error_log('admin-timetable GET: ' . $e->getMessage());
        apiJson(['success' => false, 'message' => 'Database error.'], 500);
    }
}

// ── POST: mutations ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    verifyCsrf($body['csrf_token'] ?? '');

    $validDays   = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $validLevels = ['ND I', 'ND II', 'HND I', 'HND II'];

    // ── ADD ─────────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $level   = trim($body['level']          ?? '');
        $day     = trim($body['day']            ?? '');
        $sh      = (int)($body['start_hour']    ?? 0);
        $dur     = max(1, (int)($body['duration_hours'] ?? 1));
        $code    = strtoupper(trim($body['course_code'] ?? ''));
        $name    = trim($body['course_name']    ?? '');
        $venue   = trim($body['venue']          ?? '') ?: null;
        $lec     = trim($body['lecturer_name']  ?? '') ?: null;

        if (!in_array($level, $validLevels, true) || !in_array($day, $validDays, true)
            || $sh < 8 || $sh > 17 || empty($code) || empty($name)) {
            apiJson(['success' => false, 'message' => 'Missing or invalid fields.'], 422);
        }

        // Clash check: does any existing slot on the same level+day overlap?
        $clashStmt = $db->prepare(
            'SELECT id FROM course_timetable
             WHERE level = ? AND day = ?
             AND start_hour < ? AND (start_hour + duration_hours) > ?'
        );
        $clashStmt->execute([$level, $day, $sh + $dur, $sh]);
        if ($clashStmt->fetch()) {
            apiJson(['success' => false, 'message' => 'Time slot conflicts with an existing entry for this level.'], 409);
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO course_timetable
                 (level, day, start_hour, duration_hours, course_code, course_name, venue, lecturer_name, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$level, $day, $sh, $dur, $code, $name, $venue, $lec, $adminId]);
            logActivity($adminId, 'timetable_add', "Added slot: $code on $day $sh:00 for $level");
            apiJson(['success' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'Slot added successfully.']);
        } catch (Throwable $e) {
            error_log('admin-timetable add: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Failed to add slot.'], 500);
        }
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $id    = (int)($body['id']              ?? 0);
        $level = trim($body['level']            ?? '');
        $day   = trim($body['day']              ?? '');
        $sh    = (int)($body['start_hour']      ?? 0);
        $dur   = max(1, (int)($body['duration_hours'] ?? 1));
        $code  = strtoupper(trim($body['course_code'] ?? ''));
        $name  = trim($body['course_name']      ?? '');
        $venue = trim($body['venue']            ?? '') ?: null;
        $lec   = trim($body['lecturer_name']    ?? '') ?: null;

        if ($id <= 0 || !in_array($level, $validLevels, true) || !in_array($day, $validDays, true)
            || $sh < 8 || $sh > 17 || empty($code) || empty($name)) {
            apiJson(['success' => false, 'message' => 'Missing or invalid fields.'], 422);
        }

        // Clash check (exclude self)
        $clashStmt = $db->prepare(
            'SELECT id FROM course_timetable
             WHERE level = ? AND day = ? AND id != ?
             AND start_hour < ? AND (start_hour + duration_hours) > ?'
        );
        $clashStmt->execute([$level, $day, $id, $sh + $dur, $sh]);
        if ($clashStmt->fetch()) {
            apiJson(['success' => false, 'message' => 'Updated slot conflicts with an existing entry.'], 409);
        }

        try {
            $stmt = $db->prepare(
                'UPDATE course_timetable
                 SET level=?, day=?, start_hour=?, duration_hours=?, course_code=?, course_name=?, venue=?, lecturer_name=?
                 WHERE id=?'
            );
            $stmt->execute([$level, $day, $sh, $dur, $code, $name, $venue, $lec, $id]);
            logActivity($adminId, 'timetable_update', "Updated slot ID $id");
            apiJson(['success' => true, 'message' => 'Slot updated successfully.']);
        } catch (Throwable $e) {
            error_log('admin-timetable update: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Failed to update slot.'], 500);
        }
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) { apiJson(['success' => false, 'message' => 'Invalid ID.'], 422); }

        try {
            $stmt = $db->prepare('DELETE FROM course_timetable WHERE id = ?');
            $stmt->execute([$id]);
            logActivity($adminId, 'timetable_delete', "Deleted slot ID $id");
            apiJson(['success' => true, 'message' => 'Slot deleted.']);
        } catch (Throwable $e) {
            error_log('admin-timetable delete: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Failed to delete slot.'], 500);
        }
    }

    // ── CLEAR LEVEL ──────────────────────────────────────────────────────────
    if ($action === 'clear_level') {
        $level = trim($body['level'] ?? '');
        if (!in_array($level, $validLevels, true)) {
            apiJson(['success' => false, 'message' => 'Invalid level.'], 422);
        }
        try {
            $stmt = $db->prepare('DELETE FROM course_timetable WHERE level = ?');
            $stmt->execute([$level]);
            logActivity($adminId, 'timetable_clear', "Cleared all slots for $level");
            apiJson(['success' => true, 'message' => "All $level slots cleared."]);
        } catch (Throwable $e) {
            error_log('admin-timetable clear: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Failed to clear timetable.'], 500);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown action.'], 400);
}

apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);

<?php
/**
 * api/admin-matric.php
 * Admin management of authorized matric numbers.
 *
 * GET  ?level=ND+I&page=1     → paginated list
 * GET  ?search=NDAH            → search by matric prefix
 * POST action=add_single       → add one matric number
 * POST action=upload_csv       → bulk upload (CSV text body)
 * POST action=delete           → remove by id
 * POST action=delete_level     → remove all for a level
 */
require_once __DIR__ . '/../config.php';

apiCors();

if (!isAuthenticated() || ($_SESSION['role'] ?? '') !== 'admin') {
    apiJson(['success' => false, 'message' => 'Admin access required.'], 403);
}

$adminId = (int)$_SESSION['user_id'];
$db      = db();
$VALID_LEVELS = ['ND I', 'ND II', 'HND I', 'HND II'];

// ── GET ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $level  = trim($_GET['level']  ?? '');
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if ($level && in_array($level, $VALID_LEVELS, true)) {
        $where[]  = 'level = ?';
        $params[] = $level;
    }
    if ($search !== '') {
        $where[]  = 'matric_number LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM authorized_matric_numbers $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT id, matric_number, level, department, created_at
             FROM authorized_matric_numbers
             $whereClause
             ORDER BY level, matric_number
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Summary counts per level
        $summaryStmt = $db->query(
            "SELECT level, COUNT(*) AS cnt FROM authorized_matric_numbers GROUP BY level ORDER BY FIELD(level,'ND I','ND II','HND I','HND II')"
        );
        $summary = $summaryStmt->fetchAll();

        apiJson([
            'success' => true,
            'data'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int)ceil($total / $limit),
            'summary' => $summary,
        ]);
    } catch (Throwable $e) {
        error_log('admin-matric GET: ' . $e->getMessage());
        apiJson(['success' => false, 'message' => 'Database error.'], 500);
    }
}

// ── POST ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a multipart file upload (CSV upload)
    if (!empty($_FILES['csv_file'])) {
        // CSV file upload branch
        verifyCsrfFromRequest();
        handleCsvUpload($db, $adminId, $VALID_LEVELS);
    }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    verifyCsrfFromRequest();

    // ── ADD SINGLE ───────────────────────────────────────────────────────
    if ($action === 'add_single') {
        $matric = strtoupper(trim($body['matric_number'] ?? ''));
        $level  = trim($body['level']  ?? '');
        $dept   = trim($body['department'] ?? '') ?: null;

        if (empty($matric) || !in_array($level, $VALID_LEVELS, true)) {
            apiJson(['success' => false, 'message' => 'Matric number and valid level are required.'], 422);
        }
        if (!preg_match('/^[A-Z0-9\/\-\.]+$/', $matric)) {
            apiJson(['success' => false, 'message' => 'Invalid matric number format.'], 422);
        }

        try {
            $stmt = $db->prepare(
                'INSERT IGNORE INTO authorized_matric_numbers (matric_number, level, department) VALUES (?, ?, ?)'
            );
            $stmt->execute([$matric, $level, $dept]);
            if ($stmt->rowCount() > 0) {
                logActivity($adminId, 'matric_add', "Added matric: $matric ($level)");
                apiJson(['success' => true, 'message' => "Matric number $matric added.", 'id' => (int)$db->lastInsertId()]);
            } else {
                apiJson(['success' => false, 'message' => 'That matric number already exists.'], 409);
            }
        } catch (Throwable $e) {
            error_log('admin-matric add_single: ' . $e->getMessage());
            apiJson(['success' => false, 'message' => 'Failed to add matric number.'], 500);
        }
    }

    // ── DELETE SINGLE ────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) { apiJson(['success' => false, 'message' => 'Invalid ID.'], 422); }
        try {
            $stmt = $db->prepare('DELETE FROM authorized_matric_numbers WHERE id = ?');
            $stmt->execute([$id]);
            logActivity($adminId, 'matric_delete', "Deleted matric ID $id");
            apiJson(['success' => true, 'message' => 'Matric number removed.']);
        } catch (Throwable $e) {
            apiJson(['success' => false, 'message' => 'Failed to delete.'], 500);
        }
    }

    // ── DELETE LEVEL ─────────────────────────────────────────────────────
    if ($action === 'delete_level') {
        $level = trim($body['level'] ?? '');
        if (!in_array($level, $VALID_LEVELS, true)) {
            apiJson(['success' => false, 'message' => 'Invalid level.'], 422);
        }
        try {
            $stmt = $db->prepare('DELETE FROM authorized_matric_numbers WHERE level = ?');
            $stmt->execute([$level]);
            logActivity($adminId, 'matric_clear_level', "Cleared all matric numbers for $level");
            apiJson(['success' => true, 'message' => "All $level matric numbers removed.", 'deleted' => $stmt->rowCount()]);
        } catch (Throwable $e) {
            apiJson(['success' => false, 'message' => 'Failed to clear level.'], 500);
        }
    }

    apiJson(['success' => false, 'message' => 'Unknown action.'], 400);
}

function handleCsvUpload(PDO $db, int $adminId, array $validLevels): void {
    $level = trim($_POST['level'] ?? '');
    $dept  = trim($_POST['department'] ?? '') ?: null;

    if (!in_array($level, $validLevels, true)) {
        apiJson(['success' => false, 'message' => 'Select a valid level for this CSV upload.'], 422);
    }

    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        apiJson(['success' => false, 'message' => 'File upload error.'], 400);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'], true)) {
        apiJson(['success' => false, 'message' => 'Only .csv or .txt files accepted.'], 415);
    }
    if ($file['size'] > 2 * 1024 * 1024) { // 2MB max
        apiJson(['success' => false, 'message' => 'File too large (max 2MB).'], 413);
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        apiJson(['success' => false, 'message' => 'Could not read uploaded file.'], 500);
    }

    $inserted = 0; $skipped = 0; $invalid = 0;
    $stmt = $db->prepare('INSERT IGNORE INTO authorized_matric_numbers (matric_number, level, department) VALUES (?, ?, ?)');

    // Skip header row if it contains non-matric text
    $firstLine = true;
    while (($row = fgetcsv($handle, 256, ',')) !== false) {
        if ($firstLine) {
            $firstLine = false;
            // If first cell looks like a header (contains letters that don't match matric pattern) skip it
            $first = strtoupper(trim($row[0] ?? ''));
            if (empty($first) || preg_match('/^(matric|number|no|sn|s\/n)/i', $first)) continue;
        }

        // Accept col 0 as matric number (trim whitespace, normalize)
        $matric = strtoupper(trim($row[0] ?? ''));
        if (empty($matric)) { $skipped++; continue; }
        if (!preg_match('/^[A-Z0-9\/\-\.]+$/', $matric)) { $invalid++; continue; }

        try {
            $stmt->execute([$matric, $level, $dept]);
            if ($stmt->rowCount() > 0) $inserted++;
            else $skipped++;
        } catch (Throwable $e) {
            $skipped++;
        }
    }
    fclose($handle);

    logActivity($adminId, 'matric_csv_upload', "CSV upload: $inserted inserted, $skipped skipped for $level");
    apiJson([
        'success'  => true,
        'message'  => "Upload complete: $inserted added, $skipped duplicates/skipped, $invalid invalid.",
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'invalid'  => $invalid,
    ]);
}

apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);

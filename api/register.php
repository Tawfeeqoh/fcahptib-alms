<?php
require_once __DIR__ . '/../config.php';

apiCors();
verifyCsrfFromRequest();

$input = readJsonInput();
$firstName = trim($input['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$confirm = $input['confirm_password'] ?? '';
$role = $input['role'] ?? 'student';
$departmentId = (int)($input['department_id'] ?? 0);
$levelId = (int)($input['level_id'] ?? 0);
$matricNumber = strtoupper(trim($input['matric_number'] ?? ''));
$title = trim($input['title'] ?? 'Student');

$errors = [];
if (!$firstName || !$lastName) $errors[] = 'Name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) $errors[] = 'Password must be at least 8 characters and include an uppercase letter and a number.';
if ($password !== $confirm) $errors[] = 'Passwords do not match.';
if (!in_array($role, ['student', 'lecturer'], true)) $errors[] = 'Invalid account role.';
if ($departmentId <= 0) $errors[] = 'Department is required.';

if ($role === 'student') {
    if (!$levelId) $errors[] = 'Study level is required.';
    if (!$matricNumber) $errors[] = 'Matriculation number is required.';
}

$pdo = db();
$deptStmt = $pdo->prepare('SELECT id, name, level_offered FROM departments WHERE id = ? LIMIT 1');
$deptStmt->execute([$departmentId]);
$department = $deptStmt->fetch();
if (!$department) {
    $errors[] = 'Selected department does not exist.';
} elseif ($role === 'student' && $department['level_offered'] === 'ND ONLY' && $levelId > 2) {
    $errors[] = $department['name'] . ' currently supports ND levels only.';
}

if ($errors) {
    apiJson(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 422);
}

$check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$check->execute([$email]);
if ($check->fetch()) {
    apiJson(['success' => false, 'message' => 'An account with this email address already exists.'], 409);
}

if ($role === 'student') {
    $matricCheck = $pdo->prepare('SELECT id, is_used FROM authorized_matric_numbers WHERE matric_number = ? LIMIT 1');
    $matricCheck->execute([$matricNumber]);
    $authorized = $matricCheck->fetch();
    if (!$authorized) {
        apiJson(['success' => false, 'message' => 'Your matriculation number is not on the authorized student list.'], 403);
    }
    if ($authorized['is_used']) {
        apiJson(['success' => false, 'message' => 'This matriculation number has already been used.'], 409);
    }
}

$status = $role === 'student' ? 'active' : 'pending';
$userTitle = $role === 'student' ? 'Student' : $title;
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO users (email, password_hash, role, first_name, last_name, title, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$email, $hash, $role, $firstName, $lastName, $userTitle, $status]);
    $userId = (int)$pdo->lastInsertId();

    if ($role === 'student') {
        $pdo->prepare('UPDATE authorized_matric_numbers SET is_used = 1 WHERE matric_number = ?')->execute([$matricNumber]);
        $pdo->prepare('INSERT INTO student_profiles (user_id, matric_number, level_id, department_id) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $matricNumber, $levelId, $departmentId]);
    } else {
        $pdo->prepare('INSERT INTO lecturer_profiles (user_id, primary_department_id) VALUES (?, ?)')->execute([$userId, $departmentId]);
    }

    $pdo->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)')
        ->execute([$userId, 'register', $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('API registration error: ' . $e->getMessage());
    apiJson(['success' => false, 'message' => 'Registration could not be completed. Please try again.'], 500);
}

apiJson([
    'success' => true,
    'message' => $role === 'lecturer'
        ? 'Registration complete. An administrator will review and approve your lecturer account.'
        : 'Registration complete. You can now continue onboarding.',
]);

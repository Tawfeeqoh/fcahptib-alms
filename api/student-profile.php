<?php
// ============================================================
// ALMS — Student Profile API
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$db = db();
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfFromRequest();
    $input = readJsonInput();

    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $varkStyle = strtolower(trim($input['vark_style'] ?? 'r'));
    $currentPace = strtolower(trim($input['current_pace'] ?? 'standard'));

    if ($firstName === '' || $lastName === '') {
        apiJson(['success' => false, 'message' => 'First name and last name are required.'], 422);
    }

    try {
        $db->beginTransaction();
        $updateUser = $db->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?');
        $updateUser->execute([$firstName, $lastName, $userId]);

        $updateProfile = $db->prepare('UPDATE student_profiles SET vark_style = ?, current_pace = ? WHERE user_id = ?');
        $updateProfile->execute([$varkStyle, $currentPace, $userId]);

        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['vark_style'] = $varkStyle;
        $_SESSION['current_pace'] = $currentPace;

        $db->commit();

        apiJson(['success' => true, 'message' => 'Profile updated successfully.']);
    } catch (Exception $e) {
        $db->rollBack();
        apiJson(['success' => false, 'message' => 'Unable to update profile.'], 500);
    }
}

$profileStmt = $db->prepare(
    'SELECT u.first_name, u.last_name, u.email, s.matric_number, s.level_id, s.vark_style, s.current_pace, d.name AS dept_name
     FROM users u
     JOIN student_profiles s ON u.id = s.user_id
     LEFT JOIN departments d ON s.department_id = d.id
     WHERE u.id = ?'
);
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch();

if (!$profile) {
    apiJson(['success' => false, 'message' => 'Student profile not found.'], 404);
}

$levelFull = match ((int)$profile['level_id']) {
    1 => 'ND I',
    2 => 'ND II',
    3 => 'HND I',
    4 => 'HND II',
    default => 'Roster',
};

apiJson([
    'success' => true,
    'first_name' => $profile['first_name'],
    'last_name' => $profile['last_name'],
    'email' => $profile['email'],
    'matric_number' => $profile['matric_number'],
    'level_full' => $levelFull,
    'department_name' => $profile['dept_name'] ?? 'General Studies',
    'vark_style' => $profile['vark_style'] ?? 'r',
    'current_pace' => $profile['current_pace'] ?? 'standard',
]);

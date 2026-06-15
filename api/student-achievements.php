<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/learning-engine.php';

apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$profile = upsertGamificationProfile($userId);

$badgeStmt = db()->prepare(
    'SELECT b.name, b.description, sb.earned_at
     FROM student_badges sb
     JOIN badges b ON sb.badge_id = b.id
     WHERE sb.student_id = ?
     ORDER BY sb.earned_at DESC'
);
$badgeStmt->execute([$userId]);
$badges = $badgeStmt->fetchAll();

$xpStmt = db()->prepare(
    'SELECT points, source_type, description, created_at
     FROM xp_transactions
     WHERE student_id = ?
     ORDER BY created_at DESC
     LIMIT 20'
);
$xpStmt->execute([$userId]);
$xpRows = $xpStmt->fetchAll();

apiJson([
    'success' => true,
    'profile' => [
        'total_xp' => (int)$profile['total_xp'],
        'level' => (int)$profile['level'],
        'current_streak' => (int)$profile['current_streak'],
        'longest_streak' => (int)$profile['longest_streak'],
    ],
    'badges' => $badges,
    'xp_history' => $xpRows,
]);

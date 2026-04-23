<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/public/account.php');
    exit;
}

$displayName = trim((string) ($_POST['display_name'] ?? ''));

$updateStmt = $pdo->prepare(
    "UPDATE users
     SET display_name = :display_name
     WHERE id = :id"
);
$updateStmt->execute([
    'display_name' => $displayName !== '' ? $displayName : null,
    'id' => getCurrentUserId()
]);

logAction(
    $pdo,
    getCurrentUserId(),
    'profile_update',
    'user',
    getCurrentUserId(),
    'Mise à jour du nom affiché utilisateur.'
);

$_SESSION['username'] = $_SESSION['username'] ?? '';

setFlashMessage('success', 'Ton nom affiché a été mis à jour.');
header('Location: /mangasan/public/account.php');
exit;
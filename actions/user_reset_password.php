<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

function normalizeBooleanFlag(string $value): int
{
    return $value === '1' ? 1 : 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/users.php');
    exit;
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$newPassword = (string) ($_POST['new_password'] ?? '');
$mustChangePassword = normalizeBooleanFlag((string) ($_POST['must_change_password'] ?? '1'));

if (!$userId) {
    setFlashMessage('error', 'Utilisateur invalide.');
    header('Location: /mangasan/admin/users.php');
    exit;
}

if (mb_strlen($newPassword) < 6) {
    setFlashMessage('error', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
    header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, username
     FROM users
     WHERE id = :id
     LIMIT 1"
);
$stmt->execute([
    'id' => $userId
]);

$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('error', 'Utilisateur introuvable.');
    header('Location: /mangasan/admin/users.php');
    exit;
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$updateStmt = $pdo->prepare(
    "UPDATE users
     SET password_hash = :password_hash,
         must_change_password = :must_change_password
     WHERE id = :id"
);
$updateStmt->execute([
    'password_hash' => $passwordHash,
    'must_change_password' => $mustChangePassword,
    'id' => $userId
]);

logAction(
    $pdo,
    getCurrentUserId(),
    'user_reset_password',
    'user',
    $userId,
    'Réinitialisation du mot de passe pour l’utilisateur "' . (string) $user['username'] . '".'
);

setFlashMessage('success', 'Le mot de passe a été réinitialisé.');
header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
exit;
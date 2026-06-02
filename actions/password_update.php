<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireLogin();

function redirectToPasswordForm(): void
{
    header('Location: /mangasan/public/account.php?tab=profil#password');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToPasswordForm();
}

$userId = getCurrentUserId();
$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

if (!$userId) {
    setFlashMessage('error', 'Session utilisateur invalide. Reconnecte-toi.');
    header('Location: /mangasan/public/index.php');
    exit;
}

if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '') {
    setFlashMessage('error', 'Tous les champs du changement de mot de passe sont obligatoires.');
    redirectToPasswordForm();
}

if ($newPassword !== $newPasswordConfirm) {
    setFlashMessage('error', 'Le nouveau mot de passe et sa confirmation ne correspondent pas.');
    redirectToPasswordForm();
}

if (mb_strlen($newPassword) < 8) {
    setFlashMessage('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
    redirectToPasswordForm();
}

$stmt = $pdo->prepare(
    "SELECT id, username, password_hash
     FROM users
     WHERE id = :id
     LIMIT 1"
);
$stmt->execute([
    'id' => $userId
]);

$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('error', 'Utilisateur introuvable. Reconnecte-toi.');
    header('Location: /mangasan/actions/logout.php');
    exit;
}

if (!password_verify($currentPassword, (string) $user['password_hash'])) {
    setFlashMessage('error', 'Le mot de passe actuel est incorrect.');
    redirectToPasswordForm();
}

if (password_verify($newPassword, (string) $user['password_hash'])) {
    setFlashMessage('error', 'Le nouveau mot de passe doit être différent de l’ancien.');
    redirectToPasswordForm();
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$updateStmt = $pdo->prepare(
    "UPDATE users
     SET password_hash = :password_hash,
         must_change_password = 0
     WHERE id = :id"
);
$updateStmt->execute([
    'password_hash' => $passwordHash,
    'id' => $userId
]);

$_SESSION['must_change_password'] = 0;

logAction(
    $pdo,
    $userId,
    'password_update',
    'user',
    $userId,
    'Changement de mot de passe utilisateur.'
);

setFlashMessage('success', 'Ton mot de passe a été changé.');
redirectToPasswordForm();

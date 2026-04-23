<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

function normalizeUserStatus(string $status): string
{
    return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
}

function normalizeBooleanFlag(string $value): int
{
    return $value === '1' ? 1 : 0;
}

function getAssignableRoleById(PDO $pdo, int $roleId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, label
         FROM roles
         WHERE id = :id
           AND name IN ('member', 'admin')
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $roleId
    ]);

    $role = $stmt->fetch();

    return $role ?: null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/users.php');
    exit;
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
$username = trim((string) ($_POST['username'] ?? ''));
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$displayName = trim((string) ($_POST['display_name'] ?? ''));
$className = trim((string) ($_POST['class_name'] ?? ''));
$status = normalizeUserStatus((string) ($_POST['status'] ?? 'active'));
$mustChangePassword = normalizeBooleanFlag((string) ($_POST['must_change_password'] ?? '0'));

if (!$userId || !$roleId) {
    setFlashMessage('error', 'Utilisateur ou rôle invalide.');
    header('Location: /mangasan/admin/users.php');
    exit;
}

$role = getAssignableRoleById($pdo, $roleId);

if (!$role) {
    setFlashMessage('error', 'Rôle non autorisé.');
    header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
    exit;
}

if ($username === '' || $firstName === '' || $lastName === '') {
    setFlashMessage('error', 'Nom d’utilisateur, prénom et nom sont obligatoires.');
    header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
    exit;
}

if ((string) $role['name'] === 'member' && $className === '') {
    setFlashMessage('error', 'La classe / le groupe est obligatoire pour un membre.');
    header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
    exit;
}

if ((string) $role['name'] === 'admin') {
    $className = '';
}

$currentStmt = $pdo->prepare(
    "SELECT
        users.id,
        users.username,
        users.status,
        roles.name AS role_name
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id = :id
     LIMIT 1"
);
$currentStmt->execute([
    'id' => $userId
]);

$currentUser = $currentStmt->fetch();

if (!$currentUser) {
    setFlashMessage('error', 'Utilisateur introuvable.');
    header('Location: /mangasan/admin/users.php');
    exit;
}

$currentUserId = getCurrentUserId();

if ($currentUserId === (int) $userId) {
    if ($status !== 'active') {
        setFlashMessage('error', 'Tu ne peux pas te désactiver toi-même.');
        header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
        exit;
    }

    if ((string) $role['name'] !== 'admin') {
        setFlashMessage('error', 'Tu ne peux pas retirer ton propre rôle administrateur.');
        header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
        exit;
    }
}

$existingStmt = $pdo->prepare(
    "SELECT id
     FROM users
     WHERE username = :username
       AND id <> :id
     LIMIT 1"
);
$existingStmt->execute([
    'username' => $username,
    'id' => $userId
]);

if ($existingStmt->fetch()) {
    setFlashMessage('error', 'Ce nom d’utilisateur existe déjà.');
    header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
    exit;
}

$updateStmt = $pdo->prepare(
    "UPDATE users
     SET role_id = :role_id,
         username = :username,
         first_name = :first_name,
         last_name = :last_name,
         display_name = :display_name,
         class_name = :class_name,
         status = :status,
         must_change_password = :must_change_password
     WHERE id = :id"
);

$updateStmt->execute([
    'role_id' => $roleId,
    'username' => $username,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'display_name' => $displayName !== '' ? $displayName : null,
    'class_name' => $className !== '' ? $className : null,
    'status' => $status,
    'must_change_password' => $mustChangePassword,
    'id' => $userId
]);

logAction(
    $pdo,
    getCurrentUserId(),
    'user_update',
    'user',
    $userId,
    'Mise à jour de l’utilisateur "' . $username . '".'
);

setFlashMessage('success', 'L’utilisateur a été mis à jour.');
header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
exit;
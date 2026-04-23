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

$roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
$username = trim((string) ($_POST['username'] ?? ''));
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$displayName = trim((string) ($_POST['display_name'] ?? ''));
$className = trim((string) ($_POST['class_name'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$status = normalizeUserStatus((string) ($_POST['status'] ?? 'active'));
$mustChangePassword = normalizeBooleanFlag((string) ($_POST['must_change_password'] ?? '1'));

if (!$roleId) {
    setFlashMessage('error', 'Rôle invalide.');
    header('Location: /mangasan/admin/user_edit.php');
    exit;
}

$role = getAssignableRoleById($pdo, $roleId);

if (!$role) {
    setFlashMessage('error', 'Rôle non autorisé.');
    header('Location: /mangasan/admin/user_edit.php');
    exit;
}

if ($username === '' || $firstName === '' || $lastName === '') {
    setFlashMessage('error', 'Nom d’utilisateur, prénom et nom sont obligatoires.');
    header('Location: /mangasan/admin/user_edit.php');
    exit;
}

if (mb_strlen($password) < 6) {
    setFlashMessage('error', 'Le mot de passe doit contenir au moins 6 caractères.');
    header('Location: /mangasan/admin/user_edit.php');
    exit;
}

if ((string) $role['name'] === 'member' && $className === '') {
    setFlashMessage('error', 'La classe / le groupe est obligatoire pour un membre.');
    header('Location: /mangasan/admin/user_edit.php');
    exit;
}

if ((string) $role['name'] === 'admin') {
    $className = '';
}

$existingStmt = $pdo->prepare(
    "SELECT id
     FROM users
     WHERE username = :username
     LIMIT 1"
);
$existingStmt->execute([
    'username' => $username
]);

if ($existingStmt->fetch()) {
    setFlashMessage('error', 'Ce nom d’utilisateur existe déjà.');
    header('Location: /mangasan/admin/user_edit.php');
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $insertStmt = $pdo->prepare(
        "INSERT INTO users (
            role_id,
            username,
            first_name,
            last_name,
            display_name,
            class_name,
            password_hash,
            status,
            must_change_password
         ) VALUES (
            :role_id,
            :username,
            :first_name,
            :last_name,
            :display_name,
            :class_name,
            :password_hash,
            :status,
            :must_change_password
         )"
    );

    $insertStmt->execute([
        'role_id' => $roleId,
        'username' => $username,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'display_name' => $displayName !== '' ? $displayName : null,
        'class_name' => $className !== '' ? $className : null,
        'password_hash' => $passwordHash,
        'status' => $status,
        'must_change_password' => $mustChangePassword
    ]);

    $userId = (int) $pdo->lastInsertId();

    try {
        logAction(
            $pdo,
            getCurrentUserId(),
            'user_create',
            'user',
            $userId,
            'Création de l’utilisateur "' . $username . '".'
        );
    } catch (Throwable $e) {
    }

    setFlashMessage('success', 'L’utilisateur a été créé.');
    header('Location: /mangasan/admin/user_edit.php?id=' . $userId);
    exit;
} catch (Throwable $e) {
    setFlashMessage('error', 'Création impossible : ' . $e->getMessage());
    header('Location: /mangasan/admin/user_edit.php');
    exit;
}
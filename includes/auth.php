<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fonctions d'authentification et de contrôle d'accès
|--------------------------------------------------------------------------
| Dépend de :
| - includes/session.php
| - includes/db.php
*/


function findUserByUsername(PDO $pdo, string $username): ?array
{
    $sql = "SELECT 
                users.id,
                users.username,
                users.password_hash,
                users.status,
                users.role_id,
                roles.name AS role_name,
                users.must_change_password
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            WHERE users.username = :username
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}

function loginUser(PDO $pdo, string $username, string $password): bool
{
    $user = findUserByUsername($pdo, $username);

    if ($user === null) {
        return false;
    }

    if ($user['status'] !== 'active') {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);

    session_regenerate_id(true);

    return true;
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['logged_in']);
}

function isAdmin(): bool
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /mangasan/public/index.php');
        exit;
    }
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /mangasan/public/index.php');
        exit;
    }
}

function getCurrentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function getCurrentUsername(): ?string
{
    return isset($_SESSION['username']) ? (string)$_SESSION['username'] : null;
}

function getCurrentUserRole(): ?string
{
    return isset($_SESSION['role']) ? (string)$_SESSION['role'] : null;
}

function getCurrentUserRoleId(): ?int
{
    return isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;
}
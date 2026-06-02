<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/public/index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Veuillez renseigner le nom d’utilisateur et le mot de passe.';
    header('Location: /mangasan/public/index.php');
    exit;
}

if (!loginUser($pdo, $username, $password)) {
    $_SESSION['login_error'] = 'Identifiants invalides ou compte inactif.';
    header('Location: /mangasan/public/index.php');
    exit;
}

if (!empty($_SESSION['must_change_password'])) {
    header('Location: /mangasan/public/account.php?password_required=1#password');
    exit;
}

if (getCurrentUserRole() === 'admin') {
    header('Location: /mangasan/admin/index.php');
    exit;
}

header('Location: /mangasan/public/index.php');
exit;
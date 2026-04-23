<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Connexion PDO à la base de données
|--------------------------------------------------------------------------
| Adapter uniquement les variables ci-dessous selon l'environnement local.
*/

$dbHost = 'localhost';
$dbName = 'mangasan';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}
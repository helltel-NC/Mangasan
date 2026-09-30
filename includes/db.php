<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Connexion PDO à la base de données
|--------------------------------------------------------------------------
| Adapter uniquement les variables ci-dessous selon l'environnement local.
*/

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'mangasan';
$dbUser = 'Mangasan';
$dbPass = 'Mangasan2026';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";

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
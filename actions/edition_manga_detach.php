<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

$relationId = filter_input(INPUT_POST, 'relation_id', FILTER_VALIDATE_INT);

if (!$relationId) {
    setFlashMessage('error', 'Rattachement invalide.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        edition_mangas.id,
        edition_mangas.manga_id,
        mangas.title AS manga_title,
        editions.title AS edition_title,
        editions.year AS edition_year
     FROM edition_mangas
     INNER JOIN mangas ON mangas.id = edition_mangas.manga_id
     INNER JOIN editions ON editions.id = edition_mangas.edition_id
     WHERE edition_mangas.id = :id
     LIMIT 1"
);
$stmt->execute([
    'id' => $relationId
]);
$relation = $stmt->fetch();

if (!$relation) {
    setFlashMessage('error', 'Rattachement introuvable.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

$deleteStmt = $pdo->prepare("DELETE FROM edition_mangas WHERE id = :id");
$deleteStmt->execute([
    'id' => $relationId
]);

logAction(
    $pdo,
    getCurrentUserId(),
    'edition_manga_detach',
    'edition_manga',
    (int) $relationId,
    'Détachement du manga "' . (string) $relation['manga_title'] . '" de l’édition "' . (string) $relation['edition_title'] . ' ' . (string) $relation['edition_year'] . '".'
);

setFlashMessage('success', 'Le manga a été détaché de l’édition.');
header('Location: /mangasan/admin/manga_edit.php?id=' . (int) $relation['manga_id']);
exit;
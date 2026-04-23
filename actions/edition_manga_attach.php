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

$mangaId = filter_input(INPUT_POST, 'manga_id', FILTER_VALIDATE_INT);
$editionId = filter_input(INPUT_POST, 'edition_id', FILTER_VALIDATE_INT);
$displayOrder = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT);

if (!$mangaId || !$editionId) {
    setFlashMessage('error', 'Rattachement invalide.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

if ($displayOrder === false || $displayOrder === null || $displayOrder < 0) {
    $displayOrderStmt = $pdo->prepare(
        "SELECT COALESCE(MAX(display_order), 0) + 1
         FROM edition_mangas
         WHERE edition_id = :edition_id"
    );
    $displayOrderStmt->execute([
        'edition_id' => $editionId
    ]);
    $displayOrder = (int) $displayOrderStmt->fetchColumn();
}

$mangaStmt = $pdo->prepare("SELECT id, title FROM mangas WHERE id = :id LIMIT 1");
$mangaStmt->execute([
    'id' => $mangaId
]);
$manga = $mangaStmt->fetch();

$editionStmt = $pdo->prepare("SELECT id, title, year FROM editions WHERE id = :id LIMIT 1");
$editionStmt->execute([
    'id' => $editionId
]);
$edition = $editionStmt->fetch();

if (!$manga || !$edition) {
    setFlashMessage('error', 'Manga ou édition introuvable.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
}

$existsStmt = $pdo->prepare(
    "SELECT id
     FROM edition_mangas
     WHERE edition_id = :edition_id
       AND manga_id = :manga_id
     LIMIT 1"
);
$existsStmt->execute([
    'edition_id' => $editionId,
    'manga_id' => $mangaId
]);

if ($existsStmt->fetch()) {
    setFlashMessage('error', 'Ce manga est déjà rattaché à cette édition.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
}

try {
    $insertStmt = $pdo->prepare(
        "INSERT INTO edition_mangas (
            edition_id,
            manga_id,
            display_order,
            is_visible
         ) VALUES (
            :edition_id,
            :manga_id,
            :display_order,
            1
         )"
    );

    $insertStmt->execute([
        'edition_id' => $editionId,
        'manga_id' => $mangaId,
        'display_order' => $displayOrder
    ]);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM edition_mangas
         WHERE manga_id = :manga_id"
    );
    $countStmt->execute([
        'manga_id' => $mangaId
    ]);
    $editionsCount = (int) $countStmt->fetchColumn();

    logAction(
        $pdo,
        getCurrentUserId(),
        'edition_manga_attach',
        'edition_manga',
        (int) $pdo->lastInsertId(),
        'Rattachement du manga "' . (string) $manga['title'] . '" à l’édition "' . (string) $edition['title'] . ' ' . (string) $edition['year'] . '".'
    );

    setFlashMessage('success', 'Le manga a été rattaché à l’édition. Il est maintenant présent dans ' . $editionsCount . ' édition(s).');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
} catch (Throwable $e) {
    setFlashMessage('error', 'Impossible de rattacher ce manga à l’édition.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
}
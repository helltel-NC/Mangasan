<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/editions.php');
    exit;
}

$editionId = filter_input(INPUT_POST, 'edition_id', FILTER_VALIDATE_INT);

if (!$editionId) {
    setFlashMessage('error', 'Édition invalide.');
    header('Location: /mangasan/admin/editions.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title FROM editions WHERE id = :id LIMIT 1");
    $stmt->execute([
        'id' => $editionId
    ]);

    $edition = $stmt->fetch();

    if (!$edition) {
        setFlashMessage('error', 'Édition introuvable.');
        header('Location: /mangasan/admin/editions.php');
        exit;
    }

    $pdo->beginTransaction();

    $stmtDeleteReviews = $pdo->prepare("DELETE FROM reviews WHERE edition_id = :edition_id");
    $stmtDeleteReviews->execute([
        'edition_id' => $editionId
    ]);

    $stmtDeleteEditionMangas = $pdo->prepare("DELETE FROM edition_mangas WHERE edition_id = :edition_id");
    $stmtDeleteEditionMangas->execute([
        'edition_id' => $editionId
    ]);

    $stmtDeleteEdition = $pdo->prepare("DELETE FROM editions WHERE id = :id");
    $stmtDeleteEdition->execute([
        'id' => $editionId
    ]);

    logAction(
        $pdo,
        getCurrentUserId(),
        'edition_delete',
        'edition',
        (int) $editionId,
        'Suppression de l’édition "' . (string) $edition['title'] . '".'
    );

    $pdo->commit();

    setFlashMessage('success', 'L’édition a été supprimée.');
    header('Location: /mangasan/admin/editions.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible de supprimer l’édition.');
    header('Location: /mangasan/admin/editions.php');
    exit;
}
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

$reviewId = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$redirectTo = trim((string) ($_POST['redirect_to'] ?? '/mangasan/admin/reviews.php'));

if (!str_starts_with($redirectTo, '/mangasan/')) {
    $redirectTo = '/mangasan/admin/reviews.php';
}

if (!$reviewId) {
    setFlashMessage('error', 'Fiche de lecture invalide.');
    header('Location: ' . $redirectTo);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        reviews.id,
        mangas.title AS manga_title
     FROM reviews
     INNER JOIN mangas ON mangas.id = reviews.manga_id
     WHERE reviews.id = :id
     LIMIT 1"
);
$stmt->execute([
    'id' => $reviewId
]);
$readingSheet = $stmt->fetch();

if (!$readingSheet) {
    setFlashMessage('error', 'Fiche de lecture introuvable.');
    header('Location: ' . $redirectTo);
    exit;
}

$deleteStmt = $pdo->prepare("DELETE FROM reviews WHERE id = :id");
$deleteStmt->execute([
    'id' => $reviewId
]);

logAction(
    $pdo,
    getCurrentUserId(),
    'review_delete',
    'review',
    (int) $reviewId,
    'Suppression de la fiche de lecture du manga "' . (string) $readingSheet['manga_title'] . '".'
);

setFlashMessage('success', 'La fiche de lecture a été supprimée.');
header('Location: ' . $redirectTo);
exit;
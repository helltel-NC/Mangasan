<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

function deleteManagedUploadIfUnused(?string $path, array $keepPaths): void
{
    if ($path === null || $path === '') {
        return;
    }

    foreach ($keepPaths as $keepPath) {
        if ($keepPath !== null && $keepPath !== '' && $keepPath === $path) {
            return;
        }
    }

    deleteManagedUpload($path);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

$mangaId = filter_input(INPUT_POST, 'manga_id', FILTER_VALIDATE_INT);

if (!$mangaId) {
    setFlashMessage('error', 'Manga invalide.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM mangas WHERE id = :id LIMIT 1");
$stmt->execute([
    'id' => $mangaId
]);

$manga = $stmt->fetch();

if (!$manga) {
    setFlashMessage('error', 'Manga introuvable.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtDeleteReviews = $pdo->prepare("DELETE FROM reviews WHERE manga_id = :manga_id");
    $stmtDeleteReviews->execute([
        'manga_id' => $mangaId
    ]);

    $stmtDeleteLinks = $pdo->prepare("DELETE FROM edition_mangas WHERE manga_id = :manga_id");
    $stmtDeleteLinks->execute([
        'manga_id' => $mangaId
    ]);

    $stmtDeleteManga = $pdo->prepare("DELETE FROM mangas WHERE id = :id");
    $stmtDeleteManga->execute([
        'id' => $mangaId
    ]);

    logAction(
        $pdo,
        getCurrentUserId(),
        'manga_delete',
        'manga',
        (int) $mangaId,
        'Suppression du manga "' . (string) $manga['title'] . '".'
    );

    $pdo->commit();

    deleteManagedUploadIfUnused($manga['card_image'] ?? null, [$manga['cover_image'] ?? null]);
    deleteManagedUploadIfUnused($manga['cover_image'] ?? null, []);

    setFlashMessage('success', 'Le manga a été supprimé avec ses liaisons et ses reviews.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible de supprimer le manga.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

function normalizeMangaStatus(string $status): string
{
    $allowed = ['active', 'inactive'];

    return in_array($status, $allowed, true) ? $status : 'active';
}

function findExistingMangaByIdentity(PDO $pdo, string $title, string $subtitle, ?int $excludeId = null): ?array
{
    $sql = "SELECT id, title
            FROM mangas
            WHERE title = :title
              AND COALESCE(subtitle, '') = :subtitle";

    $params = [
        'title' => $title,
        'subtitle' => $subtitle
    ];

    if ($excludeId !== null) {
        $sql .= " AND id <> :exclude_id";
        $params['exclude_id'] = $excludeId;
    }

    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch();

    return $row ?: null;
}

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
$title = trim((string) ($_POST['title'] ?? ''));
$subtitle = trim((string) ($_POST['subtitle'] ?? ''));
$author = trim((string) ($_POST['author'] ?? ''));
$illustrator = trim((string) ($_POST['illustrator'] ?? ''));
$publisher = trim((string) ($_POST['publisher'] ?? ''));
$summary = trim((string) ($_POST['summary'] ?? ''));
$cardImagePath = trim((string) ($_POST['card_image_path'] ?? ''));
$coverImagePath = trim((string) ($_POST['cover_image_path'] ?? ''));
$videoUrl = trim((string) ($_POST['video_url'] ?? ''));
$status = normalizeMangaStatus((string) ($_POST['status'] ?? 'active'));

if (!$mangaId) {
    setFlashMessage('error', 'Manga invalide.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

if ($title === '') {
    setFlashMessage('error', 'Le titre du manga est obligatoire.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
}

$existingManga = findExistingMangaByIdentity($pdo, $title, $subtitle, $mangaId);

if ($existingManga) {
    setFlashMessage('error', 'Un manga portant déjà ce titre et ce sous-titre existe. Le doublon a été bloqué.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . (int) $existingManga['id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM mangas WHERE id = :id LIMIT 1");
$stmt->execute([
    'id' => $mangaId
]);

$currentManga = $stmt->fetch();

if (!$currentManga) {
    setFlashMessage('error', 'Manga introuvable.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

$newCardImage = $cardImagePath !== '' ? $cardImagePath : null;
$newCoverImage = $coverImagePath !== '' ? $coverImagePath : null;
$oldCardImage = $currentManga['card_image'] ?? null;
$oldCoverImage = $currentManga['cover_image'] ?? null;

try {
    $hasCardUpload = isset($_FILES['card_image_file']) && (int) ($_FILES['card_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasCoverUpload = isset($_FILES['cover_image_file']) && (int) ($_FILES['cover_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasCardUpload) {
        $uploadedCardImage = saveUploadedImageAsWebp($_FILES['card_image_file'], 'mangas');
        $newCardImage = $uploadedCardImage;
    }

    if ($hasCoverUpload) {
        $uploadedCoverImage = saveUploadedImageAsWebp($_FILES['cover_image_file'], 'mangas');
        $newCoverImage = $uploadedCoverImage;
    }

    $stmtUpdate = $pdo->prepare(
        "UPDATE mangas
         SET title = :title,
             subtitle = :subtitle,
             author = :author,
             illustrator = :illustrator,
             publisher = :publisher,
             summary = :summary,
             card_image = :card_image,
             cover_image = :cover_image,
             video_url = :video_url,
             status = :status
         WHERE id = :id"
    );

    $stmtUpdate->execute([
        'title' => $title,
        'subtitle' => $subtitle !== '' ? $subtitle : null,
        'author' => $author !== '' ? $author : null,
        'illustrator' => $illustrator !== '' ? $illustrator : null,
        'publisher' => $publisher !== '' ? $publisher : null,
        'summary' => $summary !== '' ? $summary : null,
        'card_image' => $newCardImage,
        'cover_image' => $newCoverImage,
        'video_url' => $videoUrl !== '' ? $videoUrl : null,
        'status' => $status,
        'id' => $mangaId
    ]);

    if ($oldCardImage !== null && $oldCardImage !== '' && $oldCardImage !== $newCardImage) {
        deleteManagedUploadIfUnused($oldCardImage, [$newCardImage, $newCoverImage, $oldCoverImage]);
    }

    if ($oldCoverImage !== null && $oldCoverImage !== '' && $oldCoverImage !== $newCoverImage) {
        deleteManagedUploadIfUnused($oldCoverImage, [$newCardImage, $newCoverImage, $oldCardImage]);
    }

    logAction(
        $pdo,
        getCurrentUserId(),
        'manga_update',
        'manga',
        (int) $mangaId,
        'Mise à jour du manga "' . $title . '".'
    );

    setFlashMessage('success', 'Le manga a été mis à jour.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
} catch (Throwable $e) {
    setFlashMessage('error', 'Impossible de mettre à jour le manga.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
}
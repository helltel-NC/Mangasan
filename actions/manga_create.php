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

if ($title === '') {
    setFlashMessage('error', 'Le titre du manga est obligatoire.');
    header('Location: /mangasan/admin/manga_edit.php');
    exit;
}

$existingManga = findExistingMangaByIdentity($pdo, $title, $subtitle);

if ($existingManga) {
    setFlashMessage('error', 'Un manga portant déjà ce titre et ce sous-titre existe. Le doublon a été bloqué.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . (int) $existingManga['id']);
    exit;
}

$newCardImage = $cardImagePath !== '' ? $cardImagePath : null;
$newCoverImage = $coverImagePath !== '' ? $coverImagePath : null;

try {
    $hasCardUpload = isset($_FILES['card_image_file']) && (int) ($_FILES['card_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasCoverUpload = isset($_FILES['cover_image_file']) && (int) ($_FILES['cover_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasCardUpload) {
        $newCardImage = saveUploadedImageAsWebp($_FILES['card_image_file'], 'mangas');
    }

    if ($hasCoverUpload) {
        $newCoverImage = saveUploadedImageAsWebp($_FILES['cover_image_file'], 'mangas');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO mangas (
            title,
            subtitle,
            author,
            illustrator,
            publisher,
            summary,
            card_image,
            cover_image,
            video_url,
            status
         ) VALUES (
            :title,
            :subtitle,
            :author,
            :illustrator,
            :publisher,
            :summary,
            :card_image,
            :cover_image,
            :video_url,
            :status
         )"
    );

    $stmt->execute([
        'title' => $title,
        'subtitle' => $subtitle !== '' ? $subtitle : null,
        'author' => $author !== '' ? $author : null,
        'illustrator' => $illustrator !== '' ? $illustrator : null,
        'publisher' => $publisher !== '' ? $publisher : null,
        'summary' => $summary !== '' ? $summary : null,
        'card_image' => $newCardImage,
        'cover_image' => $newCoverImage,
        'video_url' => $videoUrl !== '' ? $videoUrl : null,
        'status' => $status
    ]);

    $mangaId = (int) $pdo->lastInsertId();

    logAction(
        $pdo,
        getCurrentUserId(),
        'manga_create',
        'manga',
        $mangaId,
        'Création du manga "' . $title . '".'
    );

    setFlashMessage('success', 'Le manga a été créé.');
    header('Location: /mangasan/admin/manga_edit.php?id=' . $mangaId);
    exit;
} catch (Throwable $e) {
    deleteManagedUploadIfUnused($newCardImage, [$newCoverImage]);
    deleteManagedUploadIfUnused($newCoverImage, [$newCardImage]);

    setFlashMessage('error', 'Impossible de créer le manga.');
    header('Location: /mangasan/admin/manga_edit.php');
    exit;
}
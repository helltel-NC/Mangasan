<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);

if (!$sectionId) {
    setFlashMessage('error', 'Section invalide.');
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM site_sections WHERE id = :id LIMIT 1');
$stmt->execute([
    'id' => $sectionId
]);

$currentSection = $stmt->fetch();

if (!$currentSection) {
    setFlashMessage('error', 'Section introuvable.');
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$title = trim((string) ($_POST['title'] ?? ''));
$subtitle = trim((string) ($_POST['subtilte'] ?? ''));
$content = trim((string) ($_POST['content'] ?? ''));
$mediaType = trim((string) ($_POST['media_type'] ?? 'none'));
$mediaValue = trim((string) ($_POST['media_value'] ?? ''));
$displayOrder = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT);
$isVisible = (string) ($_POST['is_visible'] ?? '1') === '1' ? 1 : 0;

$allowedMediaTypes = ['none', 'image', 'video'];

if ($title === '') {
    setFlashMessage('error', 'Le titre de la section est obligatoire.');
    header('Location: /mangasan/admin/section_edit.php?id=' . $sectionId);
    exit;
}

if (!in_array($mediaType, $allowedMediaTypes, true)) {
    setFlashMessage('error', 'Type de média invalide.');
    header('Location: /mangasan/admin/section_edit.php?id=' . $sectionId);
    exit;
}

if ($displayOrder === false || $displayOrder < 0) {
    setFlashMessage('error', 'Ordre d’affichage invalide.');
    header('Location: /mangasan/admin/section_edit.php?id=' . $sectionId);
    exit;
}

$newMediaValue = $mediaValue !== '' ? $mediaValue : null;
$oldMediaType = (string) $currentSection['media_type'];
$oldMediaValue = $currentSection['media_value'] ?? null;

try {
    if ($mediaType === 'image') {
        $hasUploadedFile = isset($_FILES['media_file']) && (int) ($_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasUploadedFile) {
            $uploadedPath = saveUploadedImageAsWebp($_FILES['media_file'], 'sections');
            $newMediaValue = $uploadedPath;

            if ($oldMediaType === 'image' && $oldMediaValue !== null && $oldMediaValue !== $uploadedPath) {
                deleteManagedUpload($oldMediaValue);
            }
        }
    }

    if ($mediaType === 'none') {
        $newMediaValue = null;

        if ($oldMediaType === 'image') {
            deleteManagedUpload($oldMediaValue);
        }
    }

    if ($mediaType === 'video' && $oldMediaType === 'image' && $oldMediaValue !== $newMediaValue) {
        deleteManagedUpload($oldMediaValue);
    }

    $updateStmt = $pdo->prepare(
        'UPDATE site_sections
         SET title = :title,
             subtilte = :subtilte,
             content = :content,
             media_type = :media_type,
             media_value = :media_value,
             display_order = :display_order,
             is_visible = :is_visible,
             updated_by = :updated_by
         WHERE id = :id'
    );

    $updateStmt->execute([
        'title' => $title,
        'subtilte' => $subtitle !== '' ? $subtitle : null,
        'content' => $content !== '' ? $content : null,
        'media_type' => $mediaType,
        'media_value' => $newMediaValue,
        'display_order' => $displayOrder,
        'is_visible' => $isVisible,
        'updated_by' => getCurrentUserId(),
        'id' => $sectionId
    ]);

    setFlashMessage('success', 'La section a été mise à jour.');

    logAction(
        $pdo,
        getCurrentUserId(),
        'section_update',
        'site_section',
        (int) $sectionId,
        'Mise à jour de la section "' . $title . '".'
    );
} catch (Throwable $e) {
    setFlashMessage('error', $e->getMessage());
}

header('Location: /mangasan/admin/section_edit.php?id=' . $sectionId);
exit;
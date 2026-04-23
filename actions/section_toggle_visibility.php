<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

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

$stmt = $pdo->prepare('SELECT id, title, is_visible FROM site_sections WHERE id = :id LIMIT 1');
$stmt->execute([
    'id' => $sectionId
]);

$section = $stmt->fetch();

if (!$section) {
    setFlashMessage('error', 'Section introuvable.');
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$newVisibility = (int) $section['is_visible'] === 1 ? 0 : 1;

$updateStmt = $pdo->prepare(
    'UPDATE site_sections
     SET is_visible = :is_visible, updated_by = :updated_by
     WHERE id = :id'
);

$updateStmt->execute([
    'is_visible' => $newVisibility,
    'updated_by' => getCurrentUserId(),
    'id' => $sectionId
]);

$visibilityText = $newVisibility === 1 ? 'visible' : 'masquée';

setFlashMessage('success', 'La visibilité de la section a été mise à jour.');

logAction(
    $pdo,
    getCurrentUserId(),
    'section_toggle_visibility',
    'site_section',
    (int) $sectionId,
    'Section "' . (string) $section['title'] . '" désormais ' . $visibilityText . '.'
);

header('Location: /mangasan/admin/sections.php');
exit;
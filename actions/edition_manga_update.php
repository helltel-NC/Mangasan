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

/**
 * Accepte plusieurs noms possibles pour rester compatible avec les formulaires existants.
 */
function postInt(array $names): ?int
{
    foreach ($names as $name) {
        if (!array_key_exists($name, $_POST)) {
            continue;
        }

        $value = filter_input(INPUT_POST, $name, FILTER_VALIDATE_INT);

        if ($value !== false && $value !== null) {
            return (int) $value;
        }
    }

    return null;
}

$relationId = postInt(['relation_id', 'edition_manga_id', 'id']);
$editionId = postInt(['edition_id']);
$displayOrder = postInt(['display_order', 'order', 'position']);
$isVisible = postInt(['is_visible']);

if (!$relationId || $relationId <= 0) {
    setFlashMessage('error', 'Impossible de modifier le manga de l’édition : rattachement invalide.');
    header('Location: /mangasan/admin/editions.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        edition_mangas.id,
        edition_mangas.edition_id,
        edition_mangas.manga_id,
        edition_mangas.display_order,
        edition_mangas.is_visible,
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
    setFlashMessage('error', 'Impossible de modifier le manga de l’édition : rattachement introuvable.');
    header('Location: /mangasan/admin/editions.php');
    exit;
}

$editionId = $editionId ?: (int) $relation['edition_id'];

if ($displayOrder === null || $displayOrder < 0) {
    $displayOrder = (int) $relation['display_order'];
}

if ($isVisible === null) {
    $isVisible = (int) $relation['is_visible'];
}

$isVisible = $isVisible === 1 ? 1 : 0;

try {
    $updateStmt = $pdo->prepare(
        "UPDATE edition_mangas
         SET display_order = :display_order,
             is_visible = :is_visible
         WHERE id = :id
           AND edition_id = :edition_id"
    );

    $updateStmt->execute([
        'display_order' => $displayOrder,
        'is_visible' => $isVisible,
        'id' => $relationId,
        'edition_id' => $editionId
    ]);

    logAction(
        $pdo,
        getCurrentUserId(),
        'edition_manga_update',
        'edition_manga',
        (int) $relationId,
        'Mise à jour du manga "' . (string) $relation['manga_title'] . '" dans l’édition "' . (string) $relation['edition_title'] . ' ' . (string) $relation['edition_year'] . '". Ordre : ' . $displayOrder . ', visible : ' . ($isVisible === 1 ? 'oui' : 'non') . '.'
    );

    setFlashMessage('success', 'Le manga de l’édition a été mis à jour.');
    header('Location: /mangasan/admin/mangas.php');
    exit;;
} catch (Throwable $e) {
    setFlashMessage('error', 'Impossible de mettre à jour le manga de l’édition.');
    header('Location: /mangasan/admin/mangas.php');
    exit;
}

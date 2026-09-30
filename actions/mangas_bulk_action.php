<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

function redirectToMangasList(string $query = ''): never
{
    $location = '/mangasan/admin/mangas.php';
    if ($query !== '') {
        $location .= '?' . $query;
    }

    header('Location: ' . $location);
    exit;
}

function normalizeSelectedMangaIds(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $ids = [];
    foreach ($value as $rawId) {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return array_values($ids);
}

function buildMangaInPlaceholders(array $ids, string $prefix): array
{
    $placeholders = [];
    $params = [];

    foreach (array_values($ids) as $index => $id) {
        $name = $prefix . $index;
        $placeholders[] = ':' . $name;
        $params[$name] = (int) $id;
    }

    return [$placeholders, $params];
}

function editionDisplayName(array $edition): string
{
    return trim((string) $edition['title'] . ' ' . (string) $edition['year']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToMangasList();
}

$returnQuery = trim((string) ($_POST['return_query'] ?? ''));
$action = trim((string) ($_POST['bulk_action'] ?? ''));
$mangaIds = normalizeSelectedMangaIds($_POST['manga_ids'] ?? []);
$allowedActions = ['activate', 'deactivate', 'attach_edition', 'detach_edition', 'delete'];

if (!in_array($action, $allowedActions, true)) {
    setFlashMessage('error', 'Action groupée invalide.');
    redirectToMangasList($returnQuery);
}

if (!$mangaIds) {
    setFlashMessage('error', 'Sélectionne au moins un manga.');
    redirectToMangasList($returnQuery);
}

if (count($mangaIds) > 500) {
    setFlashMessage('error', 'La sélection est trop importante. Procède par groupes de 500 mangas maximum.');
    redirectToMangasList($returnQuery);
}

[$mangaPlaceholders, $mangaParams] = buildMangaInPlaceholders($mangaIds, 'manga_');
$selectedStmt = $pdo->prepare(
    "SELECT
        id,
        title,
        status,
        card_image,
        cover_image
     FROM mangas
     WHERE id IN (" . implode(', ', $mangaPlaceholders) . ")
     ORDER BY title ASC, id ASC"
);
$selectedStmt->execute($mangaParams);
$selectedMangas = $selectedStmt->fetchAll();

if (!$selectedMangas) {
    setFlashMessage('error', 'Aucun manga valide trouvé dans la sélection.');
    redirectToMangasList($returnQuery);
}

$actorId = getCurrentUserId();
$successCount = 0;
$skippedCount = 0;
$messageDetails = [];
$pathsToCheckAfterDelete = [];

try {
    $pdo->beginTransaction();

    if ($action === 'activate' || $action === 'deactivate') {
        $newStatus = $action === 'activate' ? 'active' : 'inactive';
        $updateStmt = $pdo->prepare(
            "UPDATE mangas
             SET status = :status
             WHERE id = :id"
        );

        foreach ($selectedMangas as $manga) {
            if ((string) $manga['status'] === $newStatus) {
                $skippedCount++;
                continue;
            }

            $updateStmt->execute([
                'status' => $newStatus,
                'id' => (int) $manga['id'],
            ]);

            $successCount++;
            logAction(
                $pdo,
                $actorId,
                $newStatus === 'active' ? 'manga_bulk_activate' : 'manga_bulk_deactivate',
                'manga',
                (int) $manga['id'],
                ($newStatus === 'active' ? 'Activation' : 'Désactivation') . ' groupée du manga "' . (string) $manga['title'] . '".'
            );
        }
    }

    if ($action === 'attach_edition' || $action === 'detach_edition') {
        $editionId = filter_var($_POST['bulk_edition_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (!$editionId) {
            throw new RuntimeException('Choisis une édition pour cette action.');
        }

        $editionStmt = $pdo->prepare(
            "SELECT id, title, year
             FROM editions
             WHERE id = :id
             LIMIT 1"
        );
        $editionStmt->execute(['id' => (int) $editionId]);
        $edition = $editionStmt->fetch();

        if (!$edition) {
            throw new RuntimeException('Édition introuvable.');
        }

        [$relationPlaceholders, $relationParams] = buildMangaInPlaceholders(
            array_map(static fn (array $manga): int => (int) $manga['id'], $selectedMangas),
            'relation_manga_'
        );
        $relationParams['edition_id'] = (int) $editionId;

        $relationsStmt = $pdo->prepare(
            "SELECT id, manga_id
             FROM edition_mangas
             WHERE edition_id = :edition_id
               AND manga_id IN (" . implode(', ', $relationPlaceholders) . ")"
        );
        $relationsStmt->execute($relationParams);

        $existingRelations = [];
        foreach ($relationsStmt->fetchAll() as $relation) {
            $existingRelations[(int) $relation['manga_id']] = (int) $relation['id'];
        }

        if ($action === 'attach_edition') {
            $orderStmt = $pdo->prepare(
                "SELECT COALESCE(MAX(display_order), 0)
                 FROM edition_mangas
                 WHERE edition_id = :edition_id"
            );
            $orderStmt->execute(['edition_id' => (int) $editionId]);
            $nextOrder = (int) $orderStmt->fetchColumn();

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

            foreach ($selectedMangas as $manga) {
                $mangaId = (int) $manga['id'];
                if (isset($existingRelations[$mangaId])) {
                    $skippedCount++;
                    continue;
                }

                $nextOrder++;
                $insertStmt->execute([
                    'edition_id' => (int) $editionId,
                    'manga_id' => $mangaId,
                    'display_order' => $nextOrder,
                ]);

                $relationId = (int) $pdo->lastInsertId();
                $successCount++;

                logAction(
                    $pdo,
                    $actorId,
                    'edition_manga_bulk_attach',
                    'edition_manga',
                    $relationId,
                    'Rattachement groupé du manga "' . (string) $manga['title'] . '" à l’édition "' . editionDisplayName($edition) . '".'
                );
            }
        } else {
            $deleteRelationStmt = $pdo->prepare('DELETE FROM edition_mangas WHERE id = :id');

            foreach ($selectedMangas as $manga) {
                $mangaId = (int) $manga['id'];
                if (!isset($existingRelations[$mangaId])) {
                    $skippedCount++;
                    continue;
                }

                $relationId = $existingRelations[$mangaId];
                $deleteRelationStmt->execute(['id' => $relationId]);
                $successCount++;

                logAction(
                    $pdo,
                    $actorId,
                    'edition_manga_bulk_detach',
                    'edition_manga',
                    $relationId,
                    'Détachement groupé du manga "' . (string) $manga['title'] . '" de l’édition "' . editionDisplayName($edition) . '".'
                );
            }

            $messageDetails[] = 'Les fiches de lecture déjà enregistrées pour cette édition sont conservées.';
        }
    }

    if ($action === 'delete') {
        if ((string) ($_POST['bulk_confirm_delete'] ?? '') !== '1') {
            throw new RuntimeException('Confirme la suppression définitive avant de continuer.');
        }

        $deleteReviewsStmt = $pdo->prepare('DELETE FROM reviews WHERE manga_id = :manga_id');
        $deleteLinksStmt = $pdo->prepare('DELETE FROM edition_mangas WHERE manga_id = :manga_id');
        $deleteMangaStmt = $pdo->prepare('DELETE FROM mangas WHERE id = :id');

        foreach ($selectedMangas as $manga) {
            $mangaId = (int) $manga['id'];

            $deleteReviewsStmt->execute(['manga_id' => $mangaId]);
            $deleteLinksStmt->execute(['manga_id' => $mangaId]);
            $deleteMangaStmt->execute(['id' => $mangaId]);

            if ($deleteMangaStmt->rowCount() !== 1) {
                throw new RuntimeException('Impossible de supprimer le manga "' . (string) $manga['title'] . '".');
            }

            foreach ([$manga['card_image'] ?? null, $manga['cover_image'] ?? null] as $path) {
                $path = trim((string) $path);
                if ($path !== '') {
                    $pathsToCheckAfterDelete[$path] = $path;
                }
            }

            $successCount++;
            logAction(
                $pdo,
                $actorId,
                'manga_bulk_delete',
                'manga',
                $mangaId,
                'Suppression groupée du manga "' . (string) $manga['title'] . '" avec ses rattachements et ses fiches de lecture.'
            );
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', $e->getMessage());
    redirectToMangasList($returnQuery);
}

// Les fichiers ne sont supprimés qu'après validation de la transaction et uniquement
// s'ils ne sont plus référencés par un autre manga.
if ($action === 'delete' && $pathsToCheckAfterDelete) {
    $usageStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM mangas
         WHERE card_image = :card_path
            OR cover_image = :cover_path"
    );

    foreach ($pathsToCheckAfterDelete as $path) {
        $usageStmt->execute([
            'card_path' => $path,
            'cover_path' => $path,
        ]);

        if ((int) $usageStmt->fetchColumn() === 0) {
            deleteManagedUpload($path);
        }
    }
}

if ($successCount > 0) {
    $message = $successCount . ' manga(s) traité(s) avec succès.';

    if ($skippedCount > 0) {
        $message .= ' ' . $skippedCount . ' manga(s) ne nécessitaient aucune modification ou ne correspondaient pas à l’action demandée.';
    }

    if ($messageDetails) {
        $message .= ' ' . implode(' ', $messageDetails);
    }

    setFlashMessage('success', $message);
} else {
    $message = 'Aucun manga n’a été modifié.';
    if ($skippedCount > 0) {
        $message .= ' ' . $skippedCount . ' manga(s) étaient déjà dans l’état demandé.';
    }
    if ($messageDetails) {
        $message .= ' ' . implode(' ', $messageDetails);
    }

    setFlashMessage('error', $message);
}

redirectToMangasList($returnQuery);

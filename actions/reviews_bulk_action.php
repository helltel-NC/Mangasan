<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

function buildInPlaceholders(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

$bulkAction = trim((string) ($_POST['bulk_action'] ?? ''));
$redirectTo = trim((string) ($_POST['redirect_to'] ?? '/mangasan/admin/reviews.php'));
$reviewIds = $_POST['review_ids'] ?? [];

if (!str_starts_with($redirectTo, '/mangasan/')) {
    $redirectTo = '/mangasan/admin/reviews.php';
}

$allowedActions = ['lock', 'unlock', 'delete'];
if (!in_array($bulkAction, $allowedActions, true)) {
    setFlashMessage('error', 'Action groupée invalide.');
    header('Location: ' . $redirectTo);
    exit;
}

if ($bulkAction === 'delete' && (string) ($_POST['confirm_delete'] ?? '') !== '1') {
    setFlashMessage('error', 'La suppression groupée doit être confirmée.');
    header('Location: ' . $redirectTo);
    exit;
}

if (!is_array($reviewIds) || !$reviewIds) {
    setFlashMessage('error', 'Aucune fiche de lecture sélectionnée.');
    header('Location: ' . $redirectTo);
    exit;
}

$reviewIds = array_values(array_unique(array_filter(
    array_map('intval', $reviewIds),
    static fn (int $id): bool => $id > 0
)));

if (!$reviewIds) {
    setFlashMessage('error', 'Aucune fiche de lecture valide sélectionnée.');
    header('Location: ' . $redirectTo);
    exit;
}

$placeholders = buildInPlaceholders(count($reviewIds));

$selectSql = "
    SELECT
        reviews.id,
        mangas.title AS manga_title
    FROM reviews
    INNER JOIN mangas ON mangas.id = reviews.manga_id
    WHERE reviews.id IN ($placeholders)
";

$selectStmt = $pdo->prepare($selectSql);
$selectStmt->execute($reviewIds);
$readingSheets = $selectStmt->fetchAll();

if (!$readingSheets) {
    setFlashMessage('error', 'Aucune fiche de lecture trouvée pour l’action groupée.');
    header('Location: ' . $redirectTo);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($bulkAction === 'lock') {
        $updateSql = "
            UPDATE reviews
            SET status = 'locked',
                is_locked = 1,
                locked_at = NOW(),
                locked_by = ?
            WHERE id IN ($placeholders)
        ";
        $params = array_merge([getCurrentUserId()], $reviewIds);
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($params);

        foreach ($readingSheets as $readingSheet) {
            try {
                logAction(
                    $pdo,
                    getCurrentUserId(),
                    'review_lock',
                    'review',
                    (int) $readingSheet['id'],
                    'Verrouillage groupé de la fiche de lecture du manga "' . (string) $readingSheet['manga_title'] . '".'
                );
            } catch (Throwable $e) {
            }
        }

        $pdo->commit();

        setFlashMessage('success', count($readingSheets) . ' fiche(s) de lecture ont été verrouillées.');
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($bulkAction === 'unlock') {
        $updateSql = "
            UPDATE reviews
            SET status = 'editable',
                is_locked = 0,
                locked_at = NULL,
                locked_by = NULL
            WHERE id IN ($placeholders)
        ";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($reviewIds);

        foreach ($readingSheets as $readingSheet) {
            try {
                logAction(
                    $pdo,
                    getCurrentUserId(),
                    'review_unlock',
                    'review',
                    (int) $readingSheet['id'],
                    'Déverrouillage groupé de la fiche de lecture du manga "' . (string) $readingSheet['manga_title'] . '".'
                );
            } catch (Throwable $e) {
            }
        }

        $pdo->commit();

        setFlashMessage('success', count($readingSheets) . ' fiche(s) de lecture ont été déverrouillées.');
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($bulkAction === 'delete') {
        $deleteSql = "
            DELETE FROM reviews
            WHERE id IN ($placeholders)
        ";
        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute($reviewIds);

        foreach ($readingSheets as $readingSheet) {
            try {
                logAction(
                    $pdo,
                    getCurrentUserId(),
                    'review_delete',
                    'review',
                    (int) $readingSheet['id'],
                    'Suppression groupée de la fiche de lecture du manga "' . (string) $readingSheet['manga_title'] . '".'
                );
            } catch (Throwable $e) {
            }
        }

        $pdo->commit();

        setFlashMessage('success', count($readingSheets) . ' fiche(s) de lecture ont été supprimées.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $pdo->rollBack();
    setFlashMessage('error', 'Action groupée non gérée.');
    header('Location: ' . $redirectTo);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible d’exécuter l’action groupée sur les fiches de lecture.');
    header('Location: ' . $redirectTo);
    exit;
}
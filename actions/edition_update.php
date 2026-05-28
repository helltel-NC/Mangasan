<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

function normalizeEditionStatus(string $status): string
{
    $allowed = ['draft', 'active', 'closed', 'archived'];

    return in_array($status, $allowed, true) ? $status : 'draft';
}

function normalizeRankingMethod(string $method): string
{
    if ($method === 'average') {
        return 'average_score';
    }

    return in_array($method, ['average_score', 'rank_points'], true) ? $method : 'average_score';
}

function normalizeReviewFormType(string $formType): string
{
    return in_array($formType, ['classic_score', 'mangasan_reading_sheet_v1'], true) ? $formType : 'classic_score';
}

function normalizeRankingVisibility(string $visibility): string
{
    return in_array($visibility, ['visible', 'hidden'], true) ? $visibility : 'hidden';
}

function normalizeScoreMax(int|false|null $scoreMax): int
{
    return in_array($scoreMax, [5, 10, 20], true) ? $scoreMax : 20;
}

function normalizeRankingAccess(string $access): string
{
    return in_array($access, ['public', 'members'], true) ? $access : 'members';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/editions.php');
    exit;
}

$editionId = filter_input(INPUT_POST, 'edition_id', FILTER_VALIDATE_INT);
$title = trim((string) ($_POST['title'] ?? ''));
$year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
$description = trim((string) ($_POST['description'] ?? ''));
$status = normalizeEditionStatus((string) ($_POST['status'] ?? 'draft'));
$generalRankingVisibility = normalizeRankingVisibility((string) ($_POST['general_ranking_visibility'] ?? 'hidden'));
$generalRankingAccess = normalizeRankingAccess((string) ($_POST['general_ranking_access'] ?? 'members'));
$rankingCalculationMethod = normalizeRankingMethod((string) ($_POST['ranking_calculation_method'] ?? 'average_score'));
$reviewFormType = normalizeReviewFormType((string) ($_POST['review_form_type'] ?? 'classic_score'));
$scoreMax = normalizeScoreMax(filter_input(INPUT_POST, 'score_max', FILTER_VALIDATE_INT));
$startDate = trim((string) ($_POST['start_date'] ?? ''));
$endDate = trim((string) ($_POST['end_date'] ?? ''));

if (!$editionId) {
    setFlashMessage('error', 'Édition invalide.');
    header('Location: /mangasan/admin/editions.php');
    exit;
}

if ($title === '') {
    setFlashMessage('error', 'Le titre de l’édition est obligatoire.');
    header('Location: /mangasan/admin/edition_edit.php?id=' . $editionId);
    exit;
}

if ($year === false || $year < 2000 || $year > 2100) {
    setFlashMessage('error', 'Année invalide.');
    header('Location: /mangasan/admin/edition_edit.php?id=' . $editionId);
    exit;
}

if ($startDate === '' || $endDate === '') {
    setFlashMessage('error', 'Les dates de début et de fin sont obligatoires.');
    header('Location: /mangasan/admin/edition_edit.php?id=' . $editionId);
    exit;
}

if ($endDate < $startDate) {
    setFlashMessage('error', 'La date de fin doit être supérieure ou égale à la date de début.');
    header('Location: /mangasan/admin/edition_edit.php?id=' . $editionId);
    exit;
}

$isActive = $status === 'active' ? 1 : 0;

try {
    $stmt = $pdo->prepare("SELECT id, title FROM editions WHERE id = :id LIMIT 1");
    $stmt->execute([
        'id' => $editionId
    ]);

    $currentEdition = $stmt->fetch();

    if (!$currentEdition) {
        setFlashMessage('error', 'Édition introuvable.');
        header('Location: /mangasan/admin/editions.php');
        exit;
    }

    $pdo->beginTransaction();

    if ($isActive === 1) {
        $stmtDeactivate = $pdo->prepare(
            "UPDATE editions
             SET is_active = 0,
                 status = CASE
                     WHEN status = 'active' THEN 'closed'
                     ELSE status
                 END
             WHERE id <> :id"
        );
        $stmtDeactivate->execute([
            'id' => $editionId
        ]);
    }

    $stmtUpdate = $pdo->prepare(
        "UPDATE editions
         SET title = :title,
             year = :year,
             description = :description,
             status = :status,
             is_active = :is_active,
             general_ranking_visibility = :general_ranking_visibility,
             general_ranking_access = :general_ranking_access,
             ranking_calculation_method = :ranking_calculation_method,
             review_form_type = :review_form_type,
             score_max = :score_max,
             start_date = :start_date,
             end_date = :end_date
         WHERE id = :id"
    );

    $stmtUpdate->execute([
        'title' => $title,
        'year' => $year,
        'description' => $description !== '' ? $description : null,
        'status' => $status,
        'is_active' => $isActive,
        'general_ranking_visibility' => $generalRankingVisibility,
        'general_ranking_access' => $generalRankingAccess,
        'ranking_calculation_method' => $rankingCalculationMethod,
        'review_form_type' => $reviewFormType,
        'score_max' => $scoreMax,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'id' => $editionId
    ]);

    logAction(
        $pdo,
        getCurrentUserId(),
        'edition_update',
        'edition',
        (int) $editionId,
        'Mise à jour de l’édition "' . $title . '".'
    );

    $pdo->commit();

    setFlashMessage('success', 'L’édition a été mise à jour.');
    header('Location: /mangasan/admin/edition_edit.php?id=' . $editionId);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible de mettre à jour l’édition.');
    header('Location: /mangasan/admin/edition_edit.php?id=' . $editionId);
    exit;
}
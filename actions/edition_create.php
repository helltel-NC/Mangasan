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
    return $method === 'average' ? 'average' : 'average';
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

$title = trim((string) ($_POST['title'] ?? ''));
$year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
$description = trim((string) ($_POST['description'] ?? ''));
$status = normalizeEditionStatus((string) ($_POST['status'] ?? 'draft'));
$generalRankingVisibility = normalizeRankingVisibility((string) ($_POST['general_ranking_visibility'] ?? 'hidden'));
$generalRankingAccess = normalizeRankingAccess((string) ($_POST['general_ranking_access'] ?? 'members'));
$rankingCalculationMethod = normalizeRankingMethod((string) ($_POST['ranking_calculation_method'] ?? 'average'));
$scoreMax = normalizeScoreMax(filter_input(INPUT_POST, 'score_max', FILTER_VALIDATE_INT));
$startDate = trim((string) ($_POST['start_date'] ?? ''));
$endDate = trim((string) ($_POST['end_date'] ?? ''));

if ($title === '') {
    setFlashMessage('error', 'Le titre de l’édition est obligatoire.');
    header('Location: /mangasan/admin/edition_edit.php');
    exit;
}

if ($year === false || $year < 2000 || $year > 2100) {
    setFlashMessage('error', 'Année invalide.');
    header('Location: /mangasan/admin/edition_edit.php');
    exit;
}

if ($startDate === '' || $endDate === '') {
    setFlashMessage('error', 'Les dates de début et de fin sont obligatoires.');
    header('Location: /mangasan/admin/edition_edit.php');
    exit;
}

if ($endDate < $startDate) {
    setFlashMessage('error', 'La date de fin doit être supérieure ou égale à la date de début.');
    header('Location: /mangasan/admin/edition_edit.php');
    exit;
}

$isActive = $status === 'active' ? 1 : 0;

try {
    $pdo->beginTransaction();

    if ($isActive === 1) {
        $pdo->exec(
            "UPDATE editions
             SET is_active = 0,
                 status = CASE
                     WHEN status = 'active' THEN 'closed'
                     ELSE status
                 END"
        );
    }

    $stmt = $pdo->prepare(
        "INSERT INTO editions (
            title,
            year,
            description,
            status,
            is_active,
            general_ranking_visibility,
            general_ranking_access,
            ranking_calculation_method,
            score_max,
            start_date,
            end_date
         ) VALUES (
            :title,
            :year,
            :description,
            :status,
            :is_active,
            :general_ranking_visibility,
            :general_ranking_access,
            :ranking_calculation_method,
            :score_max,
            :start_date,
            :end_date
         )"
    );

    $stmt->execute([
        'title' => $title,
        'year' => $year,
        'description' => $description !== '' ? $description : null,
        'status' => $status,
        'is_active' => $isActive,
        'general_ranking_visibility' => $generalRankingVisibility,
        'general_ranking_access' => $generalRankingAccess,
        'ranking_calculation_method' => $rankingCalculationMethod,
        'score_max' => $scoreMax,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);

    $editionId = (int) $pdo->lastInsertId();

    logAction(
        $pdo,
        getCurrentUserId(),
        'edition_create',
        'edition',
        $editionId,
        'Création de l’édition "' . $title . '".'
    );

    $pdo->commit();

    setFlashMessage('success', 'L’édition a été créée.');
    header('Location: /mangasan/admin/edition_edit.php?id=' . $editionId);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible de créer l’édition.');
    header('Location: /mangasan/admin/edition_edit.php');
    exit;
}
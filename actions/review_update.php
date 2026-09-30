<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

function calculateReviewScore(float $story, float $art, float $universe, float $message): float
{
    return round(($story + $art + $universe + $message) / 4, 2);
}

function redirectToAdminReview(int $reviewId, string $returnTo): never
{
    $url = '/mangasan/admin/review_edit.php?id=' . $reviewId;

    if ($returnTo !== '/mangasan/admin/reviews.php') {
        $url .= '&return_to=' . rawurlencode($returnTo);
    }

    header('Location: ' . $url);
    exit;
}

function cleanPostText(string $key, int $maxLength = 10000): ?string
{
    $value = trim((string) ($_POST[$key] ?? ''));

    if ($value === '') {
        return null;
    }

    return substr($value, 0, $maxLength);
}

function getTargetAudiencesFromPost(): array
{
    $allowed = ['shonen', 'shojo', 'seinen', 'josei', 'kodomo', 'tout_public'];
    $values = $_POST['target_audiences'] ?? [];

    if (!is_array($values)) {
        return [];
    }

    $filtered = [];

    foreach ($values as $value) {
        $value = (string) $value;

        if (in_array($value, $allowed, true) && !in_array($value, $filtered, true)) {
            $filtered[] = $value;
        }
    }

    return $filtered;
}

function getOptionalPersonalRank(): int
{
    $rawRank = $_POST['personal_rank'] ?? '';

    if (is_array($rawRank)) {
        return -1;
    }

    $rawRank = trim((string) $rawRank);

    if ($rawRank === '') {
        return 0;
    }

    $rank = filter_var($rawRank, FILTER_VALIDATE_INT);

    if ($rank === false) {
        return -1;
    }

    return (int) $rank;
}

function buildReadingSheetData(array $readingSheet): array
{
    return [
        'form_type' => 'mangasan_reading_sheet_v1',
        'manga_title' => (string) $readingSheet['manga_title'],
        'manga_author' => cleanPostText('manga_author', 255),
        'target_audiences' => getTargetAudiencesFromPost(),
        'genres_themes' => cleanPostText('genres_themes', 500),
        'story_frame' => cleanPostText('story_frame'),
        'story_theme' => cleanPostText('story_theme'),
        'main_characters' => cleanPostText('main_characters'),
        'story_opinion' => cleanPostText('story_opinion'),
        'illustrator' => cleanPostText('illustrator', 255),
        'art_graphism' => cleanPostText('art_graphism'),
        'art_bubbles' => cleanPostText('art_bubbles'),
        'art_opinion' => cleanPostText('art_opinion'),
        'liked_points' => cleanPostText('liked_points'),
        'disliked_points' => cleanPostText('disliked_points'),
        'defense_text' => cleanPostText('defense_text'),
        'appreciation' => cleanPostText('appreciation')
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

$reviewId = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$personalRank = getOptionalPersonalRank();
$returnTo = trim((string) ($_POST['return_to'] ?? '/mangasan/admin/reviews.php'));

if (!str_starts_with($returnTo, '/mangasan/admin/reviews.php')) {
    $returnTo = '/mangasan/admin/reviews.php';
}

if (!$reviewId) {
    setFlashMessage('error', 'Fiche de lecture invalide.');
    header('Location: ' . $returnTo);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        reviews.id,
        reviews.user_id,
        reviews.edition_id,
        reviews.manga_id,
        reviews.personal_rank,
        reviews.status,
        reviews.is_locked,
        reviews.review_data,
        editions.score_max,
        editions.review_form_type,
        mangas.title AS manga_title,
        mangas.author AS manga_author,
        mangas.illustrator AS manga_illustrator,
        (
            SELECT COUNT(*)
            FROM edition_mangas visible_em
            INNER JOIN mangas visible_mangas ON visible_mangas.id = visible_em.manga_id
            WHERE visible_em.edition_id = reviews.edition_id
              AND visible_em.is_visible = 1
              AND visible_mangas.status = 'active'
        ) AS edition_manga_count
     FROM reviews
     INNER JOIN editions ON editions.id = reviews.edition_id
     INNER JOIN mangas ON mangas.id = reviews.manga_id
     WHERE reviews.id = :id
     LIMIT 1"
);
$stmt->execute([
    'id' => $reviewId
]);

$readingSheet = $stmt->fetch();

if (!$readingSheet) {
    setFlashMessage('error', 'Fiche de lecture introuvable.');
    header('Location: ' . $returnTo);
    exit;
}

if ($personalRank < 0) {
    setFlashMessage('error', 'Le rang personnel est invalide.');
    redirectToAdminReview($reviewId, $returnTo);
}

$editionMangaCount = max(1, (int) ($readingSheet['edition_manga_count'] ?? 1));

if ($personalRank > 0 && $personalRank > $editionMangaCount) {
    setFlashMessage('error', 'Le rang personnel doit être compris entre 1 et ' . $editionMangaCount . '.');
    redirectToAdminReview($reviewId, $returnTo);
}

$oldPersonalRank = (int) ($readingSheet['personal_rank'] ?? 0);
$rankConflictReview = null;
$rankSwapApplied = false;

if ($personalRank > 0 && $personalRank !== $oldPersonalRank) {
    $rankConflictStmt = $pdo->prepare(
        "SELECT id, personal_rank
         FROM reviews
         WHERE user_id = :user_id
           AND edition_id = :edition_id
           AND personal_rank = :personal_rank
           AND id <> :review_id
         LIMIT 1"
    );
    $rankConflictStmt->execute([
        'user_id' => (int) $readingSheet['user_id'],
        'edition_id' => (int) $readingSheet['edition_id'],
        'personal_rank' => $personalRank,
        'review_id' => $reviewId
    ]);

    $rankConflictReview = $rankConflictStmt->fetch() ?: null;

    if ($rankConflictReview && $oldPersonalRank <= 0) {
        setFlashMessage('error', 'Ce rang est déjà utilisé par une autre fiche de cet élève. Choisissez un rang libre ou modifiez d’abord une fiche déjà classée.');
        redirectToAdminReview($reviewId, $returnTo);
    }
}

$reviewFormType = (string) ($readingSheet['review_form_type'] ?? 'classic_score');
$updateSql = '';
$updateParams = [];

if ($reviewFormType === 'mangasan_reading_sheet_v1') {
    $reviewData = buildReadingSheetData($readingSheet);

    try {
        $reviewDataJson = json_encode($reviewData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        setFlashMessage('error', 'Impossible d’enregistrer la fiche de lecture.');
        redirectToAdminReview($reviewId, $returnTo);
    }

    $reviewText = $reviewData['appreciation']
        ?? $reviewData['defense_text']
        ?? $reviewData['story_opinion']
        ?? null;

    $updateSql =
        "UPDATE reviews
         SET story_score = 0,
             art_score = 0,
             universe_score = 0,
             message_score = 0,
             score = 0,
             personal_rank = :personal_rank,
             review_text = :review_text,
             review_data = :review_data
         WHERE id = :id";

    $updateParams = [
        'personal_rank' => $personalRank,
        'review_text' => $reviewText,
        'review_data' => $reviewDataJson,
        'id' => $reviewId
    ];
} else {
    $storyScore = filter_input(INPUT_POST, 'story_score', FILTER_VALIDATE_FLOAT);
    $artScore = filter_input(INPUT_POST, 'art_score', FILTER_VALIDATE_FLOAT);
    $universeScore = filter_input(INPUT_POST, 'universe_score', FILTER_VALIDATE_FLOAT);
    $messageScore = filter_input(INPUT_POST, 'message_score', FILTER_VALIDATE_FLOAT);
    $reviewText = trim((string) ($_POST['review_text'] ?? ''));

    $scoreMax = (float) $readingSheet['score_max'];

    $subScores = [
        'Histoire' => $storyScore,
        'Style de dessin' => $artScore,
        'Univers' => $universeScore,
        'Messages / thèmes' => $messageScore
    ];

    foreach ($subScores as $label => $value) {
        if ($value === false || $value === null) {
            setFlashMessage('error', 'La note "' . $label . '" est invalide.');
            redirectToAdminReview($reviewId, $returnTo);
        }

        if ((float) $value < 0 || (float) $value > $scoreMax) {
            setFlashMessage('error', 'La note "' . $label . '" doit être comprise entre 0 et ' . $scoreMax . '.');
            redirectToAdminReview($reviewId, $returnTo);
        }
    }

    $finalScore = calculateReviewScore((float) $storyScore, (float) $artScore, (float) $universeScore, (float) $messageScore);

    $updateSql =
        "UPDATE reviews
         SET story_score = :story_score,
             art_score = :art_score,
             universe_score = :universe_score,
             message_score = :message_score,
             score = :score,
             personal_rank = :personal_rank,
             review_text = :review_text
         WHERE id = :id";

    $updateParams = [
        'story_score' => $storyScore,
        'art_score' => $artScore,
        'universe_score' => $universeScore,
        'message_score' => $messageScore,
        'score' => $finalScore,
        'personal_rank' => $personalRank,
        'review_text' => $reviewText !== '' ? $reviewText : null,
        'id' => $reviewId
    ];
}

try {
    $pdo->beginTransaction();

    if ($rankConflictReview !== null && $oldPersonalRank > 0) {
        $swapRankStmt = $pdo->prepare(
            "UPDATE reviews
             SET personal_rank = :old_personal_rank
             WHERE id = :conflict_review_id
               AND user_id = :user_id
               AND edition_id = :edition_id"
        );

        $swapRankStmt->execute([
            'old_personal_rank' => $oldPersonalRank,
            'conflict_review_id' => (int) $rankConflictReview['id'],
            'user_id' => (int) $readingSheet['user_id'],
            'edition_id' => (int) $readingSheet['edition_id']
        ]);

        $rankSwapApplied = true;
    }

    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($updateParams);

    $pdo->commit();
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible de mettre à jour la fiche de lecture.');
    redirectToAdminReview($reviewId, $returnTo);
}

logAction(
    $pdo,
    getCurrentUserId(),
    'review_update',
    'review',
    (int) $reviewId,
    'Mise à jour de la fiche de lecture du manga "' . (string) $readingSheet['manga_title'] . '".'
);

setFlashMessage(
    'success',
    $rankSwapApplied
        ? 'La fiche de lecture a été mise à jour. Les rangs concernés ont été échangés automatiquement.'
        : 'La fiche de lecture a été mise à jour.'
);
redirectToAdminReview($reviewId, $returnTo);

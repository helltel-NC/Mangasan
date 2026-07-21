<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireLogin();

function calculateReviewScore(float $story, float $art, float $universe, float $message): float
{
    return round(($story + $art + $universe + $message) / 4, 2);
}

function redirectToReviewPage(int $editionId, int $mangaId): never
{
    header('Location: /mangasan/public/review_edit.php?edition_id=' . $editionId . '&manga_id=' . $mangaId);
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

function buildReadingSheetData(array $review): array
{
    return [
        'form_type' => 'mangasan_reading_sheet_v1',
        'manga_title' => (string) $review['manga_title'],
        'manga_author' => cleanPostText('manga_author', 255) ?? (!empty($review['manga_author']) ? (string) $review['manga_author'] : null),
        'target_audiences' => getTargetAudiencesFromPost(),
        'genres_themes' => cleanPostText('genres_themes', 500),
        'story_frame' => cleanPostText('story_frame'),
        'story_theme' => cleanPostText('story_theme'),
        'main_characters' => cleanPostText('main_characters'),
        'story_opinion' => cleanPostText('story_opinion'),
        'illustrator' => cleanPostText('illustrator', 255) ?? (!empty($review['manga_illustrator']) ? (string) $review['manga_illustrator'] : null),
        'art_graphism' => cleanPostText('art_graphism'),
        'art_bubbles' => cleanPostText('art_bubbles'),
        'art_opinion' => cleanPostText('art_opinion'),
        'liked_points' => cleanPostText('liked_points'),
        'disliked_points' => cleanPostText('disliked_points'),
        'defense_text' => cleanPostText('defense_text'),
        'appreciation' => cleanPostText('appreciation')
    ];
}

function validateReadingSheetData(array $reviewData, int $editionId, int $mangaId): void
{
    // La fiche Manga San doit pouvoir être sauvegardée partiellement.
    // Les champs vides sont acceptés : l'élève peut revenir compléter sa fiche plus tard.
    // La validation stricte est donc volontairement désactivée pour ce modèle.
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/public/index.php');
    exit;
}

$reviewId = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$editionId = filter_input(INPUT_POST, 'edition_id', FILTER_VALIDATE_INT);
$mangaId = filter_input(INPUT_POST, 'manga_id', FILTER_VALIDATE_INT);
$personalRank = getOptionalPersonalRank();

if (!$reviewId || !$editionId || !$mangaId) {
    setFlashMessage('error', 'Modification de fiche de lecture invalide.');
    header('Location: /mangasan/public/index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        reviews.*,
        editions.score_max,
        editions.review_form_type,
        editions.status AS edition_status,
        editions.is_active AS edition_is_active,
        mangas.title AS manga_title,
        mangas.author AS manga_author,
        mangas.illustrator AS manga_illustrator,
        (
            SELECT COUNT(*)
            FROM edition_mangas visible_em
            INNER JOIN mangas visible_mangas ON visible_mangas.id = visible_em.manga_id
            WHERE visible_em.edition_id = editions.id
              AND visible_em.is_visible = 1
              AND visible_mangas.status = 'active'
        ) AS edition_manga_count
     FROM reviews
     INNER JOIN editions ON editions.id = reviews.edition_id
     INNER JOIN mangas ON mangas.id = reviews.manga_id
     WHERE reviews.id = :id
       AND reviews.user_id = :user_id
     LIMIT 1"
);
$stmt->execute([
    'id' => $reviewId,
    'user_id' => getCurrentUserId()
]);

$review = $stmt->fetch();

if (!$review) {
    setFlashMessage('error', 'Fiche de lecture introuvable.');
    header('Location: /mangasan/public/index.php');
    exit;
}

if ((int) $review['edition_id'] !== $editionId || (int) $review['manga_id'] !== $mangaId) {
    setFlashMessage('error', 'Modification de fiche de lecture invalide.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

if ((string) $review['status'] === 'locked' || (int) $review['is_locked'] === 1) {
    setFlashMessage('error', 'Cette fiche de lecture est verrouillée.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

if ((string) $review['edition_status'] !== 'active' || (int) $review['edition_is_active'] !== 1) {
    setFlashMessage('error', 'Cette édition n’est plus ouverte à la modification.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

if ($personalRank < 0) {
    setFlashMessage('error', 'Le rang personnel est invalide.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

$editionMangaCount = max(1, (int) $review['edition_manga_count']);

if ($personalRank > 0 && $personalRank > $editionMangaCount) {
    setFlashMessage('error', 'Le rang personnel doit être compris entre 1 et ' . $editionMangaCount . '.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

$oldPersonalRank = (int) ($review['personal_rank'] ?? 0);
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
        'user_id' => getCurrentUserId(),
        'edition_id' => (int) $review['edition_id'],
        'personal_rank' => $personalRank,
        'review_id' => $reviewId
    ]);

    $rankConflictReview = $rankConflictStmt->fetch() ?: null;

    if ($rankConflictReview && $oldPersonalRank <= 0) {
        setFlashMessage('error', 'Ce rang est déjà utilisé. Classe d’abord cette fiche avec un rang libre, puis modifie ton classement.');
        redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
    }
}

$reviewFormType = (string) ($review['review_form_type'] ?? 'classic_score');
$updateSql = '';
$updateParams = [];

if ($reviewFormType === 'mangasan_reading_sheet_v1') {
    $reviewData = buildReadingSheetData($review);
    validateReadingSheetData($reviewData, (int) $review['edition_id'], (int) $review['manga_id']);

    try {
        $reviewDataJson = json_encode($reviewData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        setFlashMessage('error', 'Impossible d’enregistrer la fiche de lecture.');
        redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
    }

    $reviewText = $reviewData['appreciation'] ?? $reviewData['defense_text'] ?? null;

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
    $scoreMax = (float) $review['score_max'];

    $subScores = [
        'Histoire' => $storyScore,
        'Style de dessin' => $artScore,
        'Univers' => $universeScore,
        'Messages / thèmes' => $messageScore
    ];

    foreach ($subScores as $label => $value) {
        if ($value === false || $value === null) {
            setFlashMessage('error', 'La note "' . $label . '" est invalide.');
            redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
        }

        if ((float) $value < 0 || (float) $value > $scoreMax) {
            setFlashMessage('error', 'La note "' . $label . '" doit être comprise entre 0 et ' . $scoreMax . '.');
            redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
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
            'user_id' => getCurrentUserId(),
            'edition_id' => (int) $review['edition_id']
        ]);

        $rankSwapApplied = true;
    }

    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($updateParams);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible de mettre à jour ta fiche de lecture.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

logAction(
    $pdo,
    getCurrentUserId(),
    'review_update_user',
    'review',
    (int) $reviewId,
    'Mise à jour de la fiche de lecture utilisateur pour le manga "' . (string) $review['manga_title'] . '".'
);

setFlashMessage(
    'success',
    $rankSwapApplied
        ? 'Ta fiche de lecture a été mise à jour. Les rangs concernés ont été échangés automatiquement.'
        : 'Ta fiche de lecture a été mise à jour.'
);
redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);

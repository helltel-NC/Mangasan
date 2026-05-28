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

function redirectToAdminReview(int $reviewId): never
{
    header('Location: /mangasan/admin/review_edit.php?id=' . $reviewId);
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
$personalRank = filter_input(INPUT_POST, 'personal_rank', FILTER_VALIDATE_INT);

if (!$reviewId) {
    setFlashMessage('error', 'Fiche de lecture invalide.');
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        reviews.id,
        reviews.user_id,
        reviews.edition_id,
        reviews.manga_id,
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
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

if ($personalRank === false || $personalRank === null || $personalRank < 1) {
    setFlashMessage('error', 'Le rang personnel est obligatoire et doit être supérieur ou égal à 1.');
    redirectToAdminReview($reviewId);
}

$editionMangaCount = max(1, (int) ($readingSheet['edition_manga_count'] ?? 1));

if ($personalRank > $editionMangaCount) {
    setFlashMessage('error', 'Le rang personnel doit être compris entre 1 et ' . $editionMangaCount . '.');
    redirectToAdminReview($reviewId);
}

$rankConflictStmt = $pdo->prepare(
    "SELECT id
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

if ($rankConflictStmt->fetch()) {
    setFlashMessage('error', 'Ce rang personnel est déjà utilisé par cet utilisateur pour un autre manga de la même édition.');
    redirectToAdminReview($reviewId);
}

$reviewFormType = (string) ($readingSheet['review_form_type'] ?? 'classic_score');

if ($reviewFormType === 'mangasan_reading_sheet_v1') {
    $reviewData = buildReadingSheetData($readingSheet);

    try {
        $reviewDataJson = json_encode($reviewData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        setFlashMessage('error', 'Impossible d’enregistrer la fiche de lecture.');
        redirectToAdminReview($reviewId);
    }

    $reviewText = $reviewData['appreciation']
        ?? $reviewData['defense_text']
        ?? $reviewData['story_opinion']
        ?? null;

    $updateStmt = $pdo->prepare(
        "UPDATE reviews
         SET story_score = 0,
             art_score = 0,
             universe_score = 0,
             message_score = 0,
             score = 0,
             personal_rank = :personal_rank,
             review_text = :review_text,
             review_data = :review_data
         WHERE id = :id"
    );

    $updateStmt->execute([
        'personal_rank' => $personalRank,
        'review_text' => $reviewText,
        'review_data' => $reviewDataJson,
        'id' => $reviewId
    ]);
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
            redirectToAdminReview($reviewId);
        }

        if ((float) $value < 0 || (float) $value > $scoreMax) {
            setFlashMessage('error', 'La note "' . $label . '" doit être comprise entre 0 et ' . $scoreMax . '.');
            redirectToAdminReview($reviewId);
        }
    }

    $finalScore = calculateReviewScore((float) $storyScore, (float) $artScore, (float) $universeScore, (float) $messageScore);

    $updateStmt = $pdo->prepare(
        "UPDATE reviews
         SET story_score = :story_score,
             art_score = :art_score,
             universe_score = :universe_score,
             message_score = :message_score,
             score = :score,
             personal_rank = :personal_rank,
             review_text = :review_text
         WHERE id = :id"
    );

    $updateStmt->execute([
        'story_score' => $storyScore,
        'art_score' => $artScore,
        'universe_score' => $universeScore,
        'message_score' => $messageScore,
        'score' => $finalScore,
        'personal_rank' => $personalRank,
        'review_text' => $reviewText !== '' ? $reviewText : null,
        'id' => $reviewId
    ]);
}

logAction(
    $pdo,
    getCurrentUserId(),
    'review_update',
    'review',
    (int) $reviewId,
    'Mise à jour de la fiche de lecture du manga "' . (string) $readingSheet['manga_title'] . '".'
);

setFlashMessage('success', 'La fiche de lecture a été mise à jour.');
redirectToAdminReview($reviewId);

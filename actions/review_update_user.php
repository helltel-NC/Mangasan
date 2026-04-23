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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/public/index.php');
    exit;
}

$reviewId = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$editionId = filter_input(INPUT_POST, 'edition_id', FILTER_VALIDATE_INT);
$mangaId = filter_input(INPUT_POST, 'manga_id', FILTER_VALIDATE_INT);
$storyScore = filter_input(INPUT_POST, 'story_score', FILTER_VALIDATE_FLOAT);
$artScore = filter_input(INPUT_POST, 'art_score', FILTER_VALIDATE_FLOAT);
$universeScore = filter_input(INPUT_POST, 'universe_score', FILTER_VALIDATE_FLOAT);
$messageScore = filter_input(INPUT_POST, 'message_score', FILTER_VALIDATE_FLOAT);
$personalRank = filter_input(INPUT_POST, 'personal_rank', FILTER_VALIDATE_INT);
$reviewText = trim((string) ($_POST['review_text'] ?? ''));

if (!$reviewId || !$editionId || !$mangaId) {
    setFlashMessage('error', 'Modification de fiche de lecture invalide.');
    header('Location: /mangasan/public/index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        reviews.*,
        editions.score_max,
        editions.status AS edition_status,
        editions.is_active AS edition_is_active,
        mangas.title AS manga_title
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

if ((string) $review['status'] === 'locked' || (int) $review['is_locked'] === 1) {
    setFlashMessage('error', 'Cette fiche de lecture est verrouillée.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

if ((string) $review['edition_status'] !== 'active' || (int) $review['edition_is_active'] !== 1) {
    setFlashMessage('error', 'Cette édition n’est plus ouverte à la modification.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
}

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

if ($personalRank === false || $personalRank === null || $personalRank < 1) {
    setFlashMessage('error', 'Le rang personnel est obligatoire.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
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
    'user_id' => getCurrentUserId(),
    'edition_id' => (int) $review['edition_id'],
    'personal_rank' => $personalRank,
    'review_id' => $reviewId
]);

if ($rankConflictStmt->fetch()) {
    setFlashMessage('error', 'Tu utilises déjà ce rang personnel pour un autre manga de cette édition.');
    redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
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

logAction(
    $pdo,
    getCurrentUserId(),
    'review_update_user',
    'review',
    (int) $reviewId,
    'Mise à jour de la fiche de lecture utilisateur pour le manga "' . (string) $review['manga_title'] . '".'
);

setFlashMessage('success', 'Ta fiche de lecture a été mise à jour.');
redirectToReviewPage((int) $review['edition_id'], (int) $review['manga_id']);
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

$reviewId = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$storyScore = filter_input(INPUT_POST, 'story_score', FILTER_VALIDATE_FLOAT);
$artScore = filter_input(INPUT_POST, 'art_score', FILTER_VALIDATE_FLOAT);
$universeScore = filter_input(INPUT_POST, 'universe_score', FILTER_VALIDATE_FLOAT);
$messageScore = filter_input(INPUT_POST, 'message_score', FILTER_VALIDATE_FLOAT);
$personalRank = filter_input(INPUT_POST, 'personal_rank', FILTER_VALIDATE_INT);
$reviewText = trim((string) ($_POST['review_text'] ?? ''));

if (!$reviewId) {
    setFlashMessage('error', 'Fiche de lecture invalide.');
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        reviews.id,
        reviews.status,
        reviews.is_locked,
        editions.score_max,
        mangas.title AS manga_title
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
        header('Location: /mangasan/admin/review_edit.php?id=' . $reviewId);
        exit;
    }

    if ((float) $value < 0 || (float) $value > $scoreMax) {
        setFlashMessage('error', 'La note "' . $label . '" doit être comprise entre 0 et ' . $scoreMax . '.');
        header('Location: /mangasan/admin/review_edit.php?id=' . $reviewId);
        exit;
    }
}

if ($personalRank === false || $personalRank === null || $personalRank < 1) {
    setFlashMessage('error', 'Le rang personnel est obligatoire et doit être supérieur ou égal à 1.');
    header('Location: /mangasan/admin/review_edit.php?id=' . $reviewId);
    exit;
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
    'review_update',
    'review',
    (int) $reviewId,
    'Mise à jour de la fiche de lecture du manga "' . (string) $readingSheet['manga_title'] . '".'
);

setFlashMessage('success', 'La fiche de lecture a été mise à jour.');
header('Location: /mangasan/admin/review_edit.php?id=' . $reviewId);
exit;
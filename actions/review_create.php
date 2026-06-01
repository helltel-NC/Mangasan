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

function buildReadingSheetData(array $context): array
{
    return [
        'form_type' => 'mangasan_reading_sheet_v1',
        'manga_title' => (string) $context['manga_title'],
        'manga_author' => cleanPostText('manga_author', 255) ?? (!empty($context['manga_author']) ? (string) $context['manga_author'] : null),
        'target_audiences' => getTargetAudiencesFromPost(),
        'genres_themes' => cleanPostText('genres_themes', 500),
        'story_frame' => cleanPostText('story_frame'),
        'story_theme' => cleanPostText('story_theme'),
        'main_characters' => cleanPostText('main_characters'),
        'story_opinion' => cleanPostText('story_opinion'),
        'illustrator' => cleanPostText('illustrator', 255) ?? (!empty($context['manga_illustrator']) ? (string) $context['manga_illustrator'] : null),
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

$editionId = filter_input(INPUT_POST, 'edition_id', FILTER_VALIDATE_INT);
$mangaId = filter_input(INPUT_POST, 'manga_id', FILTER_VALIDATE_INT);
$personalRank = getOptionalPersonalRank();

if (!$editionId || !$mangaId) {
    setFlashMessage('error', 'Création de fiche de lecture invalide.');
    header('Location: /mangasan/public/index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        editions.id AS edition_id,
        editions.title AS edition_title,
        editions.status AS edition_status,
        editions.is_active AS edition_is_active,
        editions.score_max,
        editions.review_form_type,
        mangas.id AS manga_id,
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
     FROM edition_mangas
     INNER JOIN editions ON editions.id = edition_mangas.edition_id
     INNER JOIN mangas ON mangas.id = edition_mangas.manga_id
     WHERE edition_mangas.edition_id = :edition_id
       AND edition_mangas.manga_id = :manga_id
       AND edition_mangas.is_visible = 1
       AND mangas.status = 'active'
     LIMIT 1"
);
$stmt->execute([
    'edition_id' => $editionId,
    'manga_id' => $mangaId
]);

$context = $stmt->fetch();

if (!$context) {
    setFlashMessage('error', 'Manga ou édition introuvable.');
    header('Location: /mangasan/public/index.php');
    exit;
}

if ((string) $context['edition_status'] !== 'active' || (int) $context['edition_is_active'] !== 1) {
    setFlashMessage('error', 'Cette édition n’est plus ouverte à la saisie.');
    redirectToReviewPage($editionId, $mangaId);
}

if ($personalRank < 0) {
    setFlashMessage('error', 'Le rang personnel est invalide.');
    redirectToReviewPage($editionId, $mangaId);
}

$editionMangaCount = max(1, (int) $context['edition_manga_count']);

if ($personalRank > 0 && $personalRank > $editionMangaCount) {
    setFlashMessage('error', 'Le rang personnel doit être compris entre 1 et ' . $editionMangaCount . '.');
    redirectToReviewPage($editionId, $mangaId);
}

$existingReviewStmt = $pdo->prepare(
    "SELECT id
     FROM reviews
     WHERE user_id = :user_id
       AND edition_id = :edition_id
       AND manga_id = :manga_id
     LIMIT 1"
);
$existingReviewStmt->execute([
    'user_id' => getCurrentUserId(),
    'edition_id' => $editionId,
    'manga_id' => $mangaId
]);

if ($existingReviewStmt->fetch()) {
    setFlashMessage('error', 'Une fiche de lecture existe déjà pour ce manga dans cette édition.');
    redirectToReviewPage($editionId, $mangaId);
}

if ($personalRank > 0) {
    $rankConflictStmt = $pdo->prepare(
        "SELECT id
         FROM reviews
         WHERE user_id = :user_id
           AND edition_id = :edition_id
           AND personal_rank = :personal_rank
         LIMIT 1"
    );
    $rankConflictStmt->execute([
        'user_id' => getCurrentUserId(),
        'edition_id' => $editionId,
        'personal_rank' => $personalRank
    ]);

    if ($rankConflictStmt->fetch()) {
        setFlashMessage('error', 'Tu utilises déjà ce rang personnel pour un autre manga de cette édition.');
        redirectToReviewPage($editionId, $mangaId);
    }
}

$reviewFormType = (string) ($context['review_form_type'] ?? 'classic_score');

if ($reviewFormType === 'mangasan_reading_sheet_v1') {
    $reviewData = buildReadingSheetData($context);
    validateReadingSheetData($reviewData, $editionId, $mangaId);

    try {
        $reviewDataJson = json_encode($reviewData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        setFlashMessage('error', 'Impossible d’enregistrer la fiche de lecture.');
        redirectToReviewPage($editionId, $mangaId);
    }

    $reviewText = $reviewData['appreciation'] ?? $reviewData['defense_text'] ?? null;

    $insertStmt = $pdo->prepare(
        "INSERT INTO reviews (
            user_id,
            edition_id,
            manga_id,
            story_score,
            art_score,
            universe_score,
            message_score,
            score,
            personal_rank,
            review_text,
            review_data,
            status,
            is_locked
         ) VALUES (
            :user_id,
            :edition_id,
            :manga_id,
            0,
            0,
            0,
            0,
            0,
            :personal_rank,
            :review_text,
            :review_data,
            'editable',
            0
         )"
    );

    $insertStmt->execute([
        'user_id' => getCurrentUserId(),
        'edition_id' => $editionId,
        'manga_id' => $mangaId,
        'personal_rank' => $personalRank,
        'review_text' => $reviewText,
        'review_data' => $reviewDataJson
    ]);
} else {
    $storyScore = filter_input(INPUT_POST, 'story_score', FILTER_VALIDATE_FLOAT);
    $artScore = filter_input(INPUT_POST, 'art_score', FILTER_VALIDATE_FLOAT);
    $universeScore = filter_input(INPUT_POST, 'universe_score', FILTER_VALIDATE_FLOAT);
    $messageScore = filter_input(INPUT_POST, 'message_score', FILTER_VALIDATE_FLOAT);
    $reviewText = trim((string) ($_POST['review_text'] ?? ''));
    $scoreMax = (float) $context['score_max'];

    $subScores = [
        'Histoire' => $storyScore,
        'Style de dessin' => $artScore,
        'Univers' => $universeScore,
        'Messages / thèmes' => $messageScore
    ];

    foreach ($subScores as $label => $value) {
        if ($value === false || $value === null) {
            setFlashMessage('error', 'La note "' . $label . '" est invalide.');
            redirectToReviewPage($editionId, $mangaId);
        }

        if ((float) $value < 0 || (float) $value > $scoreMax) {
            setFlashMessage('error', 'La note "' . $label . '" doit être comprise entre 0 et ' . $scoreMax . '.');
            redirectToReviewPage($editionId, $mangaId);
        }
    }

    $finalScore = calculateReviewScore((float) $storyScore, (float) $artScore, (float) $universeScore, (float) $messageScore);

    $insertStmt = $pdo->prepare(
        "INSERT INTO reviews (
            user_id,
            edition_id,
            manga_id,
            story_score,
            art_score,
            universe_score,
            message_score,
            score,
            personal_rank,
            review_text,
            status,
            is_locked
         ) VALUES (
            :user_id,
            :edition_id,
            :manga_id,
            :story_score,
            :art_score,
            :universe_score,
            :message_score,
            :score,
            :personal_rank,
            :review_text,
            'editable',
            0
         )"
    );

    $insertStmt->execute([
        'user_id' => getCurrentUserId(),
        'edition_id' => $editionId,
        'manga_id' => $mangaId,
        'story_score' => $storyScore,
        'art_score' => $artScore,
        'universe_score' => $universeScore,
        'message_score' => $messageScore,
        'score' => $finalScore,
        'personal_rank' => $personalRank,
        'review_text' => $reviewText !== '' ? $reviewText : null
    ]);
}

logAction(
    $pdo,
    getCurrentUserId(),
    'review_create_user',
    'review',
    (int) $pdo->lastInsertId(),
    'Création de la fiche de lecture utilisateur pour le manga "' . (string) $context['manga_title'] . '".'
);

setFlashMessage('success', 'Ta fiche de lecture a été enregistrée.');
redirectToReviewPage($editionId, $mangaId);

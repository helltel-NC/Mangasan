<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';

requireLogin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolveMangaImagePath(?string $path): ?string
{
    $path = trim((string) $path);

    if ($path === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    return '/mangasan/' . ltrim($path, '/');
}

function decodeReviewData(?string $rawData): array
{
    if ($rawData === null || trim($rawData) === '') {
        return [];
    }

    $decoded = json_decode($rawData, true);

    return is_array($decoded) ? $decoded : [];
}

function reviewDataValue(array $reviewData, string $key, ?string $fallback = null): string
{
    $value = $reviewData[$key] ?? $fallback ?? '';

    if (is_array($value)) {
        return implode(', ', $value);
    }

    return (string) $value;
}

function isAudienceChecked(array $reviewData, string $audience): bool
{
    $values = $reviewData['target_audiences'] ?? [];

    if (!is_array($values)) {
        return false;
    }

    return in_array($audience, $values, true);
}

function calculateReviewScore(float $story, float $art, float $universe, float $message): float
{
    return round(($story + $art + $universe + $message) / 4, 2);
}

$editionId = filter_input(INPUT_GET, 'edition_id', FILTER_VALIDATE_INT);
$mangaId = filter_input(INPUT_GET, 'manga_id', FILTER_VALIDATE_INT);

if (!$editionId || !$mangaId) {
    setFlashMessage('error', 'Accès à la fiche de lecture invalide.');
    header('Location: /mangasan/public/index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        editions.id AS edition_id,
        editions.title AS edition_title,
        editions.year AS edition_year,
        editions.status AS edition_status,
        editions.is_active AS edition_is_active,
        editions.score_max,
        editions.review_form_type,
        mangas.id AS manga_id,
        mangas.title AS manga_title,
        mangas.subtitle AS manga_subtitle,
        mangas.author AS manga_author,
        mangas.illustrator AS manga_illustrator,
        mangas.summary AS manga_summary,
        mangas.cover_image,
        mangas.card_image,
        mangas.video_url,
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

$reviewStmt = $pdo->prepare(
    "SELECT *
     FROM reviews
     WHERE user_id = :user_id
       AND edition_id = :edition_id
       AND manga_id = :manga_id
     LIMIT 1"
);
$reviewStmt->execute([
    'user_id' => getCurrentUserId(),
    'edition_id' => $editionId,
    'manga_id' => $mangaId
]);

$existingReview = $reviewStmt->fetch() ?: null;

$userStmt = $pdo->prepare(
    "SELECT first_name, last_name, class_name
     FROM users
     WHERE id = :id
     LIMIT 1"
);
$userStmt->execute([
    'id' => getCurrentUserId()
]);
$currentUser = $userStmt->fetch() ?: null;

$isLocked = $existingReview !== null
    && ((string) $existingReview['status'] === 'locked' || (int) $existingReview['is_locked'] === 1);

$reviewFormType = (string) ($context['review_form_type'] ?? 'classic_score');
$isReadingSheetV1 = $reviewFormType === 'mangasan_reading_sheet_v1';
$editionMangaCount = max(1, (int) $context['edition_manga_count']);
$reviewData = decodeReviewData($existingReview['review_data'] ?? null);

if ($existingReview !== null && empty($reviewData) && !empty($existingReview['review_text'])) {
    $reviewData['appreciation'] = (string) $existingReview['review_text'];
}

$pageTitle = 'Ma fiche de lecture - ' . (string) $context['manga_title'];
$extraCss = [
    '/mangasan/public/assets/css/home.css',
    '/mangasan/public/assets/css/review.css'
];
$extraJs = $isReadingSheetV1 ? [] : [
    '/mangasan/public/assets/js/review-form.js'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$storyScore = $existingReview['story_score'] ?? '';
$artScore = $existingReview['art_score'] ?? '';
$universeScore = $existingReview['universe_score'] ?? '';
$messageScore = $existingReview['message_score'] ?? '';
$personalRank = $existingReview['personal_rank'] ?? '';
$reviewText = $existingReview['review_text'] ?? '';
$currentScore = $existingReview !== null
    ? (float) $existingReview['score']
    : 0.00;

$formAction = $existingReview !== null
    ? '/mangasan/actions/review_update_user.php'
    : '/mangasan/actions/review_create.php';

$targetAudiences = [
    'shonen' => 'Shōnen',
    'shojo' => 'Shōjo',
    'seinen' => 'Seinen',
    'josei' => 'Josei',
    'kodomo' => 'Kodomo / jeunesse',
    'tout_public' => 'Tout public'
];

$coverPath = $context['cover_image'] ?: $context['card_image'];
$coverSrc = resolveMangaImagePath(is_string($coverPath) ? $coverPath : null);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="review-page">
    <section class="review-compact-heading">
        <div class="container review-heading-inner">
            <div>
                <p class="review-heading-eyebrow"><?php echo $isReadingSheetV1 ? 'Ma fiche de lecture' : 'Ma review'; ?></p>
                <h1 class="review-heading-title"><?php echo e($context['manga_title']); ?></h1>
                <?php if (!empty($context['manga_subtitle'])): ?>
                    <p class="review-heading-subtitle"><?php echo e((string) $context['manga_subtitle']); ?></p>
                <?php endif; ?>
            </div>

            <div class="review-heading-meta">
                <span><?php echo e((string) $context['edition_title']); ?> - <?php echo e((string) $context['edition_year']); ?></span>
                <strong><?php echo $existingReview !== null ? ($isLocked ? 'Consultation' : 'Modification') : 'Création'; ?></strong>
            </div>
        </div>
    </section>

    <section class="home-section review-main-section">
        <div class="container">
            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <?php if ($isLocked): ?>
                <div class="alert error">
                    Cette fiche de lecture est verrouillée. Tu peux la consulter, mais tu ne peux plus la modifier.
                </div>
            <?php endif; ?>

            <section class="review-manga-card">
                <div class="review-manga-cover-wrap">
                    <?php if ($coverSrc !== null): ?>
                        <img src="<?php echo e($coverSrc); ?>" alt="Couverture de <?php echo e((string) $context['manga_title']); ?>" class="review-cover-image">
                    <?php else: ?>
                        <div class="review-cover-placeholder">Couverture non renseignée</div>
                    <?php endif; ?>
                </div>

                <div class="review-manga-info">
                    <p class="review-kicker">Manga sélectionné</p>
                    <h2><?php echo e((string) $context['manga_title']); ?></h2>

                    <?php if (!empty($context['manga_subtitle'])): ?>
                        <p class="review-manga-subtitle"><?php echo e((string) $context['manga_subtitle']); ?></p>
                    <?php endif; ?>

                    <div class="review-manga-facts">
                        <?php if (!empty($context['manga_author'])): ?>
                            <div>
                                <span>Auteur</span>
                                <strong><?php echo e((string) $context['manga_author']); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($context['manga_illustrator'])): ?>
                            <div>
                                <span>Illustrateur</span>
                                <strong><?php echo e((string) $context['manga_illustrator']); ?></strong>
                            </div>
                        <?php endif; ?>

                        <div>
                            <span>Édition</span>
                            <strong><?php echo e((string) $context['edition_year']); ?></strong>
                        </div>
                    </div>

                    <div class="review-manga-summary">
                        <h3>Résumé de l’histoire</h3>
                        <?php if (!empty($context['manga_summary'])): ?>
                            <p><?php echo nl2br(e((string) $context['manga_summary'])); ?></p>
                        <?php else: ?>
                            <p class="review-empty-text">Aucun résumé n’est renseigné pour ce manga.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="review-layout">
                <div class="review-panel">
                    <form method="post" action="<?php echo e($formAction); ?>" class="review-form">
                        <?php if ($existingReview !== null): ?>
                            <input type="hidden" name="review_id" value="<?php echo (int) $existingReview['id']; ?>">
                        <?php endif; ?>

                        <input type="hidden" name="edition_id" value="<?php echo (int) $context['edition_id']; ?>">
                        <input type="hidden" name="manga_id" value="<?php echo (int) $context['manga_id']; ?>">
                        <input type="hidden" name="score_max" value="<?php echo e((string) $context['score_max']); ?>">

                        <?php if ($isReadingSheetV1): ?>
                            <div class="review-form-section">
                                <h2>Informations du manga</h2>

                                <div class="review-grid">
                                    <div class="review-field">
                                        <label for="manga_title">Titre du manga</label>
                                        <input type="text" id="manga_title" value="<?php echo e((string) $context['manga_title']); ?>" disabled>
                                    </div>

                                    <div class="review-field">
                                        <label for="manga_author">Auteur</label>
                                        <input type="text" id="manga_author" name="manga_author" value="<?php echo e(reviewDataValue($reviewData, 'manga_author', (string) ($context['manga_author'] ?? ''))); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                                    </div>
                                </div>

                                <div class="review-field">
                                    <label>Public ciblé</label>
                                    <div class="review-checkbox-list">
                                        <?php foreach ($targetAudiences as $value => $label): ?>
                                            <label class="review-checkbox">
                                                <input type="checkbox" name="target_audiences[]" value="<?php echo e($value); ?>" <?php echo isAudienceChecked($reviewData, $value) ? 'checked' : ''; ?> <?php echo $isLocked ? 'disabled' : ''; ?>>
                                                <span><?php echo e($label); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="review-field">
                                    <label for="genres_themes">Genres / thèmes</label>
                                    <input type="text" id="genres_themes" name="genres_themes" value="<?php echo e(reviewDataValue($reviewData, 'genres_themes')); ?>" placeholder="Exemples : action, fantastique, scolaire, romance..." <?php echo $isLocked ? 'disabled' : ''; ?>>
                                </div>
                            </div>

                            <div class="review-form-section">
                                <h2>Scénario</h2>

                                <div class="review-field">
                                    <label for="story_frame">Le cadre de l’histoire</label>
                                    <textarea id="story_frame" name="story_frame" placeholder="Exemples : époque, pays, monde réel, monde imaginaire, lycée, futur, passé..." <?php echo $isLocked ? 'disabled' : 'required'; ?>><?php echo e(reviewDataValue($reviewData, 'story_frame')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="story_theme">Le thème général</label>
                                    <textarea id="story_theme" name="story_theme" placeholder="Exemples : intrigue, quête initiatique, historique, action, fantastique, sociétal..." <?php echo $isLocked ? 'disabled' : 'required'; ?>><?php echo e(reviewDataValue($reviewData, 'story_theme')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="main_characters">Les personnages principaux</label>
                                    <textarea id="main_characters" name="main_characters" placeholder="Exemples : identité, personnalité, rôle dans l’histoire, évolution..." <?php echo $isLocked ? 'disabled' : 'required'; ?>><?php echo e(reviewDataValue($reviewData, 'main_characters')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="story_opinion">Avis sur le scénario</label>
                                    <textarea id="story_opinion" name="story_opinion" placeholder="Explique si le scénario est clair, intéressant, original, rythmé..." <?php echo $isLocked ? 'disabled' : ''; ?>><?php echo e(reviewDataValue($reviewData, 'story_opinion')); ?></textarea>
                                </div>
                            </div>

                            <div class="review-form-section">
                                <h2>Dessin</h2>

                                <div class="review-field">
                                    <label for="illustrator">Illustrateur</label>
                                    <input type="text" id="illustrator" name="illustrator" value="<?php echo e(reviewDataValue($reviewData, 'illustrator', (string) ($context['manga_illustrator'] ?? ''))); ?>" <?php echo $isLocked ? 'disabled' : ''; ?>>
                                </div>

                                <div class="review-field">
                                    <label for="art_graphism">Graphisme</label>
                                    <textarea id="art_graphism" name="art_graphism" placeholder="Exemples : style, ambiance, détails, équilibre texte / image, lisibilité..." <?php echo $isLocked ? 'disabled' : 'required'; ?>><?php echo e(reviewDataValue($reviewData, 'art_graphism')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="art_bubbles">Les bulles</label>
                                    <textarea id="art_bubbles" name="art_bubbles" placeholder="Exemples : bulles faciles à suivre, bien placées, lisibles, rythme de lecture..." <?php echo $isLocked ? 'disabled' : 'required'; ?>><?php echo e(reviewDataValue($reviewData, 'art_bubbles')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="art_opinion">Avis sur le dessin</label>
                                    <textarea id="art_opinion" name="art_opinion" placeholder="Explique ce que tu penses du dessin et de la mise en page." <?php echo $isLocked ? 'disabled' : ''; ?>><?php echo e(reviewDataValue($reviewData, 'art_opinion')); ?></textarea>
                                </div>
                            </div>

                            <div class="review-form-section">
                                <h2>Impressions personnelles</h2>

                                <div class="review-field">
                                    <label for="liked_points">Ce que j’ai aimé</label>
                                    <textarea id="liked_points" name="liked_points" placeholder="Explique les éléments que tu as appréciés dans ce manga." <?php echo $isLocked ? 'disabled' : ''; ?>><?php echo e(reviewDataValue($reviewData, 'liked_points')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="disliked_points">Ce que je n’ai pas aimé</label>
                                    <textarea id="disliked_points" name="disliked_points" placeholder="Explique les éléments qui t’ont moins plu ou gêné." <?php echo $isLocked ? 'disabled' : ''; ?>><?php echo e(reviewDataValue($reviewData, 'disliked_points')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="defense_text">Je défends ce manga</label>
                                    <textarea id="defense_text" name="defense_text" placeholder="Explique pourquoi tu recommanderais ce manga à d’autres élèves." <?php echo $isLocked ? 'disabled' : ''; ?>><?php echo e(reviewDataValue($reviewData, 'defense_text')); ?></textarea>
                                </div>

                                <div class="review-field">
                                    <label for="appreciation">Mon appréciation</label>
                                    <textarea id="appreciation" name="appreciation" placeholder="Donne ton avis général sur ce manga." <?php echo $isLocked ? 'disabled' : 'required'; ?>><?php echo e(reviewDataValue($reviewData, 'appreciation')); ?></textarea>
                                </div>
                            </div>

                            <div class="review-form-section">
                                <h2>Classement personnel</h2>

                                <div class="review-field">
                                    <label for="personal_rank">Je classe ce manga à la position</label>
                                    <input type="number" id="personal_rank" name="personal_rank" min="1" max="<?php echo $editionMangaCount; ?>" value="<?php echo e((string) $personalRank); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                                    <small>1 = ton manga préféré de l’édition. Maximum : <?php echo $editionMangaCount; ?>.</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="review-form-section">
                                <h2>Notation guidée</h2>

                                <div class="review-grid">
                                    <div class="review-field">
                                        <label for="story_score">Histoire</label>
                                        <input type="number" id="story_score" name="story_score" min="0" max="<?php echo e((string) $context['score_max']); ?>" step="0.01" value="<?php echo e((string) $storyScore); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                                    </div>

                                    <div class="review-field">
                                        <label for="art_score">Style de dessin</label>
                                        <input type="number" id="art_score" name="art_score" min="0" max="<?php echo e((string) $context['score_max']); ?>" step="0.01" value="<?php echo e((string) $artScore); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                                    </div>

                                    <div class="review-field">
                                        <label for="universe_score">Univers</label>
                                        <input type="number" id="universe_score" name="universe_score" min="0" max="<?php echo e((string) $context['score_max']); ?>" step="0.01" value="<?php echo e((string) $universeScore); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                                    </div>

                                    <div class="review-field">
                                        <label for="message_score">Messages / thèmes</label>
                                        <input type="number" id="message_score" name="message_score" min="0" max="<?php echo e((string) $context['score_max']); ?>" step="0.01" value="<?php echo e((string) $messageScore); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                                    </div>
                                </div>

                                <div class="review-score-box">
                                    <span>Note finale calculée</span>
                                    <strong><span id="reviewScorePreview"><?php echo e(number_format($currentScore, 2, '.', '')); ?></span> / <span id="reviewScoreMax"><?php echo e((string) $context['score_max']); ?></span></strong>
                                </div>
                            </div>

                            <div class="review-form-section">
                                <h2>Classement personnel</h2>

                                <div class="review-field">
                                    <label for="personal_rank">Rang personnel</label>
                                    <input type="number" id="personal_rank" name="personal_rank" min="1" max="<?php echo $editionMangaCount; ?>" value="<?php echo e((string) $personalRank); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                                    <small>1 = ton manga préféré de l’édition. Maximum : <?php echo $editionMangaCount; ?>.</small>
                                </div>
                            </div>

                            <div class="review-form-section">
                                <h2>Avis libre</h2>

                                <div class="review-field">
                                    <label for="review_text">Ton avis</label>
                                    <textarea id="review_text" name="review_text" <?php echo $isLocked ? 'disabled' : ''; ?>><?php echo e((string) $reviewText); ?></textarea>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isLocked): ?>
                            <div class="review-form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $existingReview !== null ? 'Enregistrer les modifications' : 'Créer ma fiche'; ?>
                                </button>
                                <a href="/mangasan/public/index.php" class="btn btn-secondary">Retour</a>
                            </div>
                        <?php else: ?>
                            <div class="review-form-actions">
                                <a href="/mangasan/public/index.php" class="btn btn-secondary">Retour</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <aside class="review-side-card">
                    <h2>Suivi de la fiche</h2>

                    <div class="review-side-block">
                        <span>Manga</span>
                        <strong><?php echo e((string) $context['manga_title']); ?></strong>
                    </div>

                    <?php if (!empty($context['manga_author'])): ?>
                        <div class="review-side-block">
                            <span>Auteur</span>
                            <strong><?php echo e((string) $context['manga_author']); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="review-side-block">
                        <span>Édition</span>
                        <strong><?php echo e((string) $context['edition_title']); ?> - <?php echo e((string) $context['edition_year']); ?></strong>
                    </div>

                    <div class="review-side-block">
                        <span>Mangas à classer</span>
                        <strong><?php echo $editionMangaCount; ?></strong>
                    </div>

                    <?php if ($currentUser !== null): ?>
                        <div class="review-side-block">
                            <span>Élève</span>
                            <strong><?php echo e(trim((string) $currentUser['first_name'] . ' ' . (string) $currentUser['last_name'])); ?></strong>
                            <?php if (!empty($currentUser['class_name'])): ?>
                                <p><?php echo e((string) $currentUser['class_name']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($existingReview !== null): ?>
                        <div class="review-side-block">
                            <span>Statut</span>
                            <strong><?php echo $isLocked ? 'Verrouillée' : 'Modifiable'; ?></strong>
                        </div>

                        <div class="review-side-block">
                            <span>Créée le</span>
                            <strong><?php echo e((string) $existingReview['created_at']); ?></strong>
                        </div>

                        <div class="review-side-block">
                            <span>Mise à jour le</span>
                            <strong><?php echo e((string) $existingReview['updated_at']); ?></strong>
                        </div>
                    <?php endif; ?>

                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

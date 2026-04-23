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

function calculateReviewScore(float $story, float $art, float $universe, float $message): float
{
    return round(($story + $art + $universe + $message) / 4, 2);
}

$editionId = filter_input(INPUT_GET, 'edition_id', FILTER_VALIDATE_INT);
$mangaId = filter_input(INPUT_GET, 'manga_id', FILTER_VALIDATE_INT);

if (!$editionId || !$mangaId) {
    setFlashMessage('error', 'Accès à la review invalide.');
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
        mangas.id AS manga_id,
        mangas.title AS manga_title,
        mangas.subtitle AS manga_subtitle,
        mangas.summary AS manga_summary,
        mangas.cover_image,
        mangas.card_image,
        mangas.video_url
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

$isLocked = $existingReview !== null
    && ((string) $existingReview['status'] === 'locked' || (int) $existingReview['is_locked'] === 1);

$pageTitle = 'Ma review - ' . (string) $context['manga_title'];
$extraCss = [
    '/mangasan/public/assets/css/home.css',
    '/mangasan/public/assets/css/review.css'
];
$extraJs = [
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

require_once __DIR__ . '/../includes/header.php';
?>

<main class="review-page">
    <header class="site-header">
        <div class="container header-inner">
            <div class="logo"><?php echo e($theme['site_title']); ?></div>
            <nav class="nav">
                <a href="/mangasan/public/index.php">Retour au site</a>
            </nav>
        </div>
    </header>

    <section class="hero review-hero">
        <div class="container hero-content">
            <div class="hero-text">
                <p class="hero-kicker">Ma review</p>
                <h1 class="hero-title"><?php echo e($context['manga_title']); ?></h1>

                <?php if (!empty($context['manga_subtitle'])): ?>
                    <p class="hero-description"><?php echo e((string) $context['manga_subtitle']); ?></p>
                <?php endif; ?>

                <div class="review-meta-list">
                    <div class="review-meta-item">
                        <span>Édition</span>
                        <strong><?php echo e((string) $context['edition_title']); ?> - <?php echo e((string) $context['edition_year']); ?></strong>
                    </div>

                    <div class="review-meta-item">
                        <span>Barème</span>
                        <strong>/ <?php echo e((string) $context['score_max']); ?></strong>
                    </div>

                    <div class="review-meta-item">
                        <span>Mode</span>
                        <strong>
                            <?php
                            if ($existingReview === null) {
                                echo 'Création';
                            } elseif ($isLocked) {
                                echo 'Consultation';
                            } else {
                                echo 'Modification';
                            }
                            ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="review-cover-card">
                    <?php $cover = $context['cover_image'] ?: $context['card_image']; ?>
                    <?php if (!empty($cover)): ?>
                        <img src="<?php echo e((string) $cover); ?>" alt="<?php echo e((string) $context['manga_title']); ?>" class="review-cover-image">
                    <?php else: ?>
                        <div class="review-cover-placeholder">Aucune image</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="container">
            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <?php if ($isLocked): ?>
                <div class="alert error">
                    Cette review est verrouillée. Tu peux la consulter, mais tu ne peux plus la modifier.
                </div>
            <?php endif; ?>

            <div class="review-layout">
                <div class="review-panel">
                    <form method="post" action="<?php echo e($formAction); ?>" class="review-form">
                        <?php if ($existingReview !== null): ?>
                            <input type="hidden" name="review_id" value="<?php echo (int) $existingReview['id']; ?>">
                        <?php endif; ?>

                        <input type="hidden" name="edition_id" value="<?php echo (int) $context['edition_id']; ?>">
                        <input type="hidden" name="manga_id" value="<?php echo (int) $context['manga_id']; ?>">
                        <input type="hidden" name="score_max" value="<?php echo e((string) $context['score_max']); ?>">

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
                                <input type="number" id="personal_rank" name="personal_rank" min="1" value="<?php echo e((string) $personalRank); ?>" <?php echo $isLocked ? 'disabled' : 'required'; ?>>
                            </div>
                        </div>

                        <div class="review-form-section">
                            <h2>Avis libre</h2>

                            <div class="review-field">
                                <label for="review_text">Ton avis</label>
                                <textarea id="review_text" name="review_text" <?php echo $isLocked ? 'disabled' : ''; ?>><?php echo e((string) $reviewText); ?></textarea>
                            </div>
                        </div>

                        <?php if (!$isLocked): ?>
                            <div class="review-form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $existingReview !== null ? 'Enregistrer les modifications' : 'Créer ma review'; ?>
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
                    <h2>Résumé</h2>

                    <div class="review-side-block">
                        <span>Manga</span>
                        <strong><?php echo e((string) $context['manga_title']); ?></strong>
                    </div>

                    <div class="review-side-block">
                        <span>Édition</span>
                        <strong><?php echo e((string) $context['edition_title']); ?> - <?php echo e((string) $context['edition_year']); ?></strong>
                    </div>

                    <div class="review-side-block">
                        <span>Barème</span>
                        <strong>/ <?php echo e((string) $context['score_max']); ?></strong>
                    </div>

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

                    <?php if (!empty($context['manga_summary'])): ?>
                        <div class="review-side-block">
                            <span>Résumé</span>
                            <p><?php echo nl2br(e((string) $context['manga_summary'])); ?></p>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
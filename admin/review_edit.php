<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';

requireAdmin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function readingSheetStatusLabel(string $status): string
{
    return match ($status) {
        'editable' => 'Modifiable',
        'locked' => 'Verrouillée',
        default => $status
    };
}

$reviewId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reviewId) {
    setFlashMessage('error', 'Fiche de lecture invalide.');
    header('Location: /mangasan/admin/reviews.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        reviews.*,
        editions.title AS edition_title,
        editions.year AS edition_year,
        editions.score_max,
        mangas.title AS manga_title,
        mangas.subtitle AS manga_subtitle,
        COALESCE(
            NULLIF(users.display_name, ''),
            NULLIF(TRIM(CONCAT(users.first_name, ' ', users.last_name)), ''),
            users.username
        ) AS reviewer_name,
        COALESCE(
            NULLIF(locker.display_name, ''),
            NULLIF(TRIM(CONCAT(locker.first_name, ' ', locker.last_name)), ''),
            locker.username
        ) AS locked_by_name
     FROM reviews
     INNER JOIN editions ON editions.id = reviews.edition_id
     INNER JOIN mangas ON mangas.id = reviews.manga_id
     INNER JOIN users ON users.id = reviews.user_id
     LEFT JOIN users AS locker ON locker.id = reviews.locked_by
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

$pageTitle = 'Modifier une fiche de lecture - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];
$extraJs = [
    '/mangasan/public/assets/js/admin-reviews.js'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Modifier une fiche de lecture</h1>
                    <p>Contrôle complet sur la note détaillée, le rang personnel et le texte d’avis.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/reviews.php" class="btn btn-secondary">Retour fiches de lecture</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-layout-two-columns">
                <div class="admin-panel">
                    <form method="post" action="/mangasan/actions/review_update.php" class="admin-form">
                        <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">

                        <div class="admin-form-section">
                            <h2>Contexte</h2>

                            <div class="admin-review-meta">
                                <div class="admin-summary-item">
                                    <span>Utilisateur</span>
                                    <strong><?php echo e($readingSheet['reviewer_name']); ?></strong>
                                </div>

                                <div class="admin-summary-item">
                                    <span>Édition</span>
                                    <strong><?php echo e($readingSheet['edition_title']); ?> - <?php echo e((string) $readingSheet['edition_year']); ?></strong>
                                </div>

                                <div class="admin-summary-item">
                                    <span>Manga</span>
                                    <strong><?php echo e($readingSheet['manga_title']); ?></strong>
                                </div>

                                <div class="admin-summary-item">
                                    <span>Barème</span>
                                    <strong>/ <?php echo e((string) $readingSheet['score_max']); ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h2>Notation détaillée</h2>

                            <div class="admin-review-grid">
                                <div class="admin-field">
                                    <label for="story_score">Histoire</label>
                                    <input type="number" id="story_score" name="story_score" min="0" max="<?php echo e((string) $readingSheet['score_max']); ?>" step="0.01" value="<?php echo e((string) $readingSheet['story_score']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="art_score">Style de dessin</label>
                                    <input type="number" id="art_score" name="art_score" min="0" max="<?php echo e((string) $readingSheet['score_max']); ?>" step="0.01" value="<?php echo e((string) $readingSheet['art_score']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="universe_score">Univers</label>
                                    <input type="number" id="universe_score" name="universe_score" min="0" max="<?php echo e((string) $readingSheet['score_max']); ?>" step="0.01" value="<?php echo e((string) $readingSheet['universe_score']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="message_score">Messages / thèmes</label>
                                    <input type="number" id="message_score" name="message_score" min="0" max="<?php echo e((string) $readingSheet['score_max']); ?>" step="0.01" value="<?php echo e((string) $readingSheet['message_score']); ?>" required>
                                </div>
                            </div>

                            <div class="admin-score-box">
                                <span>Note finale calculée</span>
                                <strong><span id="reviewScorePreview"><?php echo e(number_format((float) $readingSheet['score'], 2, '.', '')); ?></span> / <span id="reviewScoreMax"><?php echo e((string) $readingSheet['score_max']); ?></span></strong>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h2>Classement et avis</h2>

                            <div class="admin-field">
                                <label for="personal_rank">Rang personnel</label>
                                <input type="number" id="personal_rank" name="personal_rank" min="1" value="<?php echo (int) $readingSheet['personal_rank']; ?>" required>
                            </div>

                            <div class="admin-field">
                                <label for="review_text">Avis libre</label>
                                <textarea id="review_text" name="review_text"><?php echo e((string) $readingSheet['review_text']); ?></textarea>
                            </div>
                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>

                            <?php if ((string) $readingSheet['status'] === 'locked'): ?>
                                <form method="post" action="/mangasan/actions/review_unlock.php">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                    <input type="hidden" name="redirect_to" value="/mangasan/admin/review_edit.php?id=<?php echo (int) $readingSheet['id']; ?>">
                                    <button type="submit" class="btn btn-secondary">Déverrouiller</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="/mangasan/actions/review_lock.php">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                    <input type="hidden" name="redirect_to" value="/mangasan/admin/review_edit.php?id=<?php echo (int) $readingSheet['id']; ?>">
                                    <button type="submit" class="btn btn-secondary">Verrouiller</button>
                                </form>
                            <?php endif; ?>

                            <form method="post" action="/mangasan/actions/review_delete.php" onsubmit="return confirm('Supprimer cette fiche de lecture ? Cette action est irréversible.');">
                                <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                <input type="hidden" name="redirect_to" value="/mangasan/admin/reviews.php">
                                <button type="submit" class="btn btn-secondary">Supprimer</button>
                            </form>
                        </div>
                    </form>
                </div>

                <aside class="admin-preview-card">
                    <div class="admin-preview-head">
                        <h2>Résumé</h2>
                    </div>

                    <div class="admin-preview-section">
                        <div class="admin-summary-item">
                            <span>Statut</span>
                            <strong><?php echo e(readingSheetStatusLabel((string) $readingSheet['status'])); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Note actuelle</span>
                            <strong><?php echo e(number_format((float) $readingSheet['score'], 2, '.', '')); ?> / <?php echo e((string) $readingSheet['score_max']); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Rang personnel</span>
                            <strong><?php echo (int) $readingSheet['personal_rank']; ?></strong>
                        </div>

                        <?php if ((string) $readingSheet['status'] === 'locked'): ?>
                            <div class="admin-summary-item">
                                <span>Verrouillée le</span>
                                <strong><?php echo e((string) $readingSheet['locked_at']); ?></strong>
                            </div>

                            <div class="admin-summary-item">
                                <span>Verrouillée par</span>
                                <strong><?php echo e($readingSheet['locked_by_name']); ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="admin-summary-item">
                            <span>Créée le</span>
                            <strong><?php echo e((string) $readingSheet['created_at']); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Mise à jour le</span>
                            <strong><?php echo e((string) $readingSheet['updated_at']); ?></strong>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/help.php';

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

function reviewFormTypeLabel(?string $formType): string
{
    return match ((string) $formType) {
        'mangasan_reading_sheet_v1' => 'Fiche Mangasan',
        'classic_score' => 'Fiche avec notes',
        default => (string) ($formType ?? 'classic_score')
    };
}

function rankingMethodLabel(?string $method): string
{
    return match ((string) $method) {
        'rank_points' => 'Points par rang',
        'average', 'average_score' => 'Moyenne des notes',
        default => (string) ($method ?? 'average_score')
    };
}

function calculateRankPoints(int $totalMangas, int $personalRank): int
{
    if ($totalMangas < 1 || $personalRank < 1 || $personalRank > $totalMangas) {
        return 0;
    }

    return $totalMangas - $personalRank + 1;
}

function targetAudienceOptions(): array
{
    return [
        'shonen' => 'Shōnen',
        'shojo' => 'Shōjo',
        'seinen' => 'Seinen',
        'josei' => 'Josei',
        'kodomo' => 'Kodomo / jeunesse',
        'tout_public' => 'Tout public'
    ];
}

function decodeReviewData(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }

    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function reviewDataValue(array $reviewData, string $key, ?string $fallback = null): string
{
    $value = $reviewData[$key] ?? $fallback ?? '';

    if (is_array($value)) {
        return implode(', ', array_map('strval', $value));
    }

    return (string) $value;
}

$reviewId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$returnTo = trim((string) ($_GET['return_to'] ?? '/mangasan/admin/reviews.php'));

if (!str_starts_with($returnTo, '/mangasan/admin/reviews.php')) {
    $returnTo = '/mangasan/admin/reviews.php';
}

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
        editions.review_form_type,
        editions.ranking_calculation_method,
        mangas.title AS manga_title,
        mangas.subtitle AS manga_subtitle,
        mangas.author AS manga_author,
        mangas.illustrator AS manga_illustrator,
        mangas.summary AS manga_summary,
        COALESCE(
            NULLIF(users.display_name, ''),
            NULLIF(TRIM(CONCAT(users.first_name, ' ', users.last_name)), ''),
            users.username
        ) AS reviewer_name,
        users.class_name AS reviewer_class_name,
        COALESCE(
            NULLIF(locker.display_name, ''),
            NULLIF(TRIM(CONCAT(locker.first_name, ' ', locker.last_name)), ''),
            locker.username
        ) AS locked_by_name,
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

$reviewFormType = (string) ($readingSheet['review_form_type'] ?? 'classic_score');
$rankingMethod = (string) ($readingSheet['ranking_calculation_method'] ?? 'average_score');
$isMangaSanSheet = $reviewFormType === 'mangasan_reading_sheet_v1';
$reviewData = decodeReviewData($readingSheet['review_data'] ?? null);
$totalMangas = max(1, (int) ($readingSheet['edition_manga_count'] ?? 1));
$rankPoints = calculateRankPoints($totalMangas, (int) $readingSheet['personal_rank']);

$pageTitle = 'Modifier une fiche de lecture - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = $isMangaSanSheet
    ? ['/mangasan/public/assets/js/help-system.js']
    : [
        '/mangasan/public/assets/js/admin-reviews.js',
        '/mangasan/public/assets/js/help-system.js',
    ];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-review-edit-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="reviewEditHelpHeading">
                <div class="admin-page-heading">
                    <h1>Modifier une fiche de lecture</h1>
                    <p>
                        <?php if ($isMangaSanSheet): ?>
                            Consultation et correction de la fiche Mangasan enregistrée par l’élève.
                        <?php else: ?>
                            Contrôle complet sur la note détaillée, le rang personnel et le texte d’avis.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="<?php echo e($returnTo); ?>" class="btn btn-secondary">Retour fiches de lecture</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-layout-two-columns admin-review-edit-layout">
                <div class="admin-panel">
                    <form method="post" action="/mangasan/actions/review_update.php" class="admin-form">
                        <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                        <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">

                        <div class="admin-form-section" id="reviewEditHelpContext">
                            <h2>Contexte</h2>

                            <div class="admin-review-meta">
                                <div class="admin-summary-item">
                                    <span>Utilisateur</span>
                                    <strong><?php echo e($readingSheet['reviewer_name']); ?></strong>
                                </div>

                                <?php if (!empty($readingSheet['reviewer_class_name'])): ?>
                                    <div class="admin-summary-item">
                                        <span>Classe</span>
                                        <strong><?php echo e((string) $readingSheet['reviewer_class_name']); ?></strong>
                                    </div>
                                <?php endif; ?>

                                <div class="admin-summary-item">
                                    <span>Édition</span>
                                    <strong><?php echo e($readingSheet['edition_title']); ?> - <?php echo e((string) $readingSheet['edition_year']); ?></strong>
                                </div>

                                <div class="admin-summary-item">
                                    <span>Manga</span>
                                    <strong><?php echo e($readingSheet['manga_title']); ?></strong>
                                </div>

                                <div class="admin-summary-item">
                                    <span>Type de fiche</span>
                                    <strong><?php echo e(reviewFormTypeLabel($reviewFormType)); ?></strong>
                                </div>

                                <div class="admin-summary-item">
                                    <span>Classement</span>
                                    <strong><?php echo e(rankingMethodLabel($rankingMethod)); ?></strong>
                                </div>
                            </div>
                        </div>

                        <?php if ($isMangaSanSheet && !empty($readingSheet['manga_summary'])): ?>
                            <div class="admin-form-section">
                                <h2>Résumé du manga</h2>
                                <p><?php echo nl2br(e((string) $readingSheet['manga_summary'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($isMangaSanSheet): ?>
                            <div class="admin-form-section" id="reviewEditHelpMangaInfo">
                                <h2>Informations manga</h2>

                                <div class="admin-review-grid">
                                    <div class="admin-field">
                                        <label for="manga_author">Auteur</label>
                                        <input type="text" id="manga_author" name="manga_author" value="<?php echo e(reviewDataValue($reviewData, 'manga_author', (string) ($readingSheet['manga_author'] ?? ''))); ?>" maxlength="255">
                                    </div>

                                    <div class="admin-field">
                                        <label for="illustrator">Illustrateur</label>
                                        <input type="text" id="illustrator" name="illustrator" value="<?php echo e(reviewDataValue($reviewData, 'illustrator', (string) ($readingSheet['manga_illustrator'] ?? ''))); ?>" maxlength="255">
                                    </div>
                                </div>

                                <div class="admin-field">
                                    <label for="target_audiences">Public ciblé</label>
                                    <?php $selectedAudiences = $reviewData['target_audiences'] ?? []; ?>
                                    <?php $selectedAudiences = is_array($selectedAudiences) ? $selectedAudiences : []; ?>
                                    <select id="target_audiences" name="target_audiences[]" multiple size="6">
                                        <?php foreach (targetAudienceOptions() as $value => $label): ?>
                                            <option value="<?php echo e($value); ?>" <?php echo in_array($value, $selectedAudiences, true) ? 'selected' : ''; ?>>
                                                <?php echo e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Maintenir Ctrl pour sélectionner plusieurs publics.</small>
                                </div>

                                <div class="admin-field">
                                    <label for="genres_themes">Genres / thèmes</label>
                                    <input type="text" id="genres_themes" name="genres_themes" value="<?php echo e(reviewDataValue($reviewData, 'genres_themes')); ?>" placeholder="Exemples : action, fantastique, scolaire..." maxlength="500">
                                </div>
                            </div>

                            <div class="admin-form-section" id="reviewEditHelpScenario">
                                <h2>Scénario</h2>

                                <div class="admin-field">
                                    <label for="story_frame">Le cadre</label>
                                    <textarea id="story_frame" name="story_frame" placeholder="Époque, pays, univers, contexte..."><?php echo e(reviewDataValue($reviewData, 'story_frame')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="story_theme">Le thème général</label>
                                    <textarea id="story_theme" name="story_theme" placeholder="Intrigue, quête, action, fantastique, historique, sociétal..."><?php echo e(reviewDataValue($reviewData, 'story_theme')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="main_characters">Les personnages principaux</label>
                                    <textarea id="main_characters" name="main_characters" placeholder="Identité, personnalité, rôle dans l’histoire, évolution..."><?php echo e(reviewDataValue($reviewData, 'main_characters')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="story_opinion">Avis sur le scénario</label>
                                    <textarea id="story_opinion" name="story_opinion"><?php echo e(reviewDataValue($reviewData, 'story_opinion')); ?></textarea>
                                </div>
                            </div>

                            <div class="admin-form-section" id="reviewEditHelpDrawing">
                                <h2>Dessin</h2>

                                <div class="admin-field">
                                    <label for="art_graphism">Graphisme</label>
                                    <textarea id="art_graphism" name="art_graphism" placeholder="Style, détails, ambiance, équilibre texte / image..."><?php echo e(reviewDataValue($reviewData, 'art_graphism')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="art_bubbles">Les bulles</label>
                                    <textarea id="art_bubbles" name="art_bubbles" placeholder="Lisibilité, placement, compréhension, rythme..."><?php echo e(reviewDataValue($reviewData, 'art_bubbles')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="art_opinion">Avis sur le dessin</label>
                                    <textarea id="art_opinion" name="art_opinion"><?php echo e(reviewDataValue($reviewData, 'art_opinion')); ?></textarea>
                                </div>
                            </div>

                            <div class="admin-form-section" id="reviewEditHelpImpressions">
                                <h2>Impressions personnelles</h2>

                                <div class="admin-field">
                                    <label for="liked_points">Ce que l’élève a aimé</label>
                                    <textarea id="liked_points" name="liked_points"><?php echo e(reviewDataValue($reviewData, 'liked_points')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="disliked_points">Ce que l’élève n’a pas aimé</label>
                                    <textarea id="disliked_points" name="disliked_points"><?php echo e(reviewDataValue($reviewData, 'disliked_points')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="defense_text">Je défends ce manga</label>
                                    <textarea id="defense_text" name="defense_text"><?php echo e(reviewDataValue($reviewData, 'defense_text')); ?></textarea>
                                </div>

                                <div class="admin-field">
                                    <label for="appreciation">Mon appréciation</label>
                                    <textarea id="appreciation" name="appreciation"><?php echo e(reviewDataValue($reviewData, 'appreciation', (string) ($readingSheet['review_text'] ?? ''))); ?></textarea>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="admin-form-section" id="reviewEditHelpScores">
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

                            <div class="admin-form-section" id="reviewEditHelpOpinion">
                                <h2>Avis</h2>

                                <div class="admin-field">
                                    <label for="review_text">Avis libre</label>
                                    <textarea id="review_text" name="review_text"><?php echo e((string) $readingSheet['review_text']); ?></textarea>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="admin-form-section" id="reviewEditHelpRank">
                            <h2>Classement personnel</h2>

                            <div class="admin-field">
                                <label for="personal_rank">Rang personnel</label>
                                <input type="number" id="personal_rank" name="personal_rank" min="0" max="<?php echo $totalMangas; ?>" value="<?php echo (int) $readingSheet['personal_rank'] > 0 ? (int) $readingSheet['personal_rank'] : ''; ?>" placeholder="Non classée">
                                <small>Laisser vide ou saisir 0 si la fiche n’est pas encore classée. Sinon, le rang doit être compris entre 1 et <?php echo $totalMangas; ?>.</small>
                            </div>
                        </div>

                        <div class="admin-form-actions" id="reviewEditHelpSave">
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>

                <aside class="admin-preview-card admin-review-summary-card" id="reviewEditHelpSummary">
                    <div class="admin-preview-head">
                        <h2>Résumé</h2>
                    </div>

                    <div class="admin-preview-section">
                        <div class="admin-summary-item">
                            <span>Statut</span>
                            <strong><?php echo e(readingSheetStatusLabel((string) $readingSheet['status'])); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span><?php echo $rankingMethod === 'rank_points' ? 'Points actuels' : 'Note actuelle'; ?></span>
                            <strong>
                                <?php if ($rankingMethod === 'rank_points'): ?>
                                    <?php echo (int) $readingSheet['personal_rank'] > 0 ? $rankPoints . ' pts' : 'Non classée'; ?>
                                <?php else: ?>
                                    <?php echo e(number_format((float) $readingSheet['score'], 2, '.', '')); ?> / <?php echo e((string) $readingSheet['score_max']); ?>
                                <?php endif; ?>
                            </strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Rang personnel</span>
                            <strong><?php echo (int) $readingSheet['personal_rank'] > 0 ? (int) $readingSheet['personal_rank'] . ' / ' . $totalMangas : 'Non classée'; ?></strong>
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

                    <div class="admin-preview-section admin-review-status-actions" id="reviewEditHelpStatusActions">
                        <?php if ((string) $readingSheet['status'] === 'locked'): ?>
                            <form method="post" action="/mangasan/actions/review_unlock.php">
                                <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                <input type="hidden" name="redirect_to" value="/mangasan/admin/review_edit.php?id=<?php echo (int) $readingSheet['id']; ?>&amp;return_to=<?php echo rawurlencode($returnTo); ?>">
                                <button type="submit" class="btn btn-secondary">Déverrouiller</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/mangasan/actions/review_lock.php">
                                <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                <input type="hidden" name="redirect_to" value="/mangasan/admin/review_edit.php?id=<?php echo (int) $readingSheet['id']; ?>&amp;return_to=<?php echo rawurlencode($returnTo); ?>">
                                <button type="submit" class="btn btn-secondary">Verrouiller</button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="/mangasan/actions/review_delete.php" onsubmit="return confirm('Supprimer cette fiche de lecture ? Cette action est irréversible.');">
                            <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo e($returnTo); ?>">
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php
$reviewHelpSteps = [
    [
        'target' => '#reviewEditHelpHeading',
        'title' => 'Modifier une fiche de lecture',
        'text' => 'Cette page permet à l’administration de consulter et corriger une fiche enregistrée par un élève. Les modifications effectuées ici sont enregistrées directement dans sa fiche.',
        'tip' => 'Le type de fiche dépend de la configuration de l’édition.'
    ],
    [
        'target' => '#reviewEditHelpContext',
        'title' => 'Contexte de la fiche',
        'text' => 'Cette zone rappelle l’élève, sa classe, l’édition, le manga, le type de fiche et la méthode de classement. Elle permet de vérifier que vous modifiez bien la bonne fiche.'
    ],
];

if ($isMangaSanSheet) {
    $reviewHelpSteps[] = [
        'target' => '#reviewEditHelpMangaInfo',
        'title' => 'Informations sur le manga',
        'text' => 'Ces champs reprennent les informations saisies ou complétées dans la fiche Mangasan : auteur, illustrateur, public ciblé et genres ou thèmes.',
        'tip' => 'Pour sélectionner plusieurs publics dans la liste, maintenez Ctrl pendant la sélection.'
    ];
    $reviewHelpSteps[] = [
        'target' => '#reviewEditHelpScenario',
        'title' => 'Scénario',
        'text' => 'Cette partie contient l’analyse du cadre, du thème général, des personnages principaux et l’avis de l’élève sur le scénario.'
    ];
    $reviewHelpSteps[] = [
        'target' => '#reviewEditHelpDrawing',
        'title' => 'Dessin',
        'text' => 'Cette partie rassemble les remarques de l’élève sur le graphisme, les bulles et son avis général sur le dessin.'
    ];
    $reviewHelpSteps[] = [
        'target' => '#reviewEditHelpImpressions',
        'title' => 'Impressions personnelles',
        'text' => 'Vous retrouvez ici ce que l’élève a aimé ou non, son argumentaire pour défendre le manga et son appréciation générale.'
    ];
} else {
    $reviewHelpSteps[] = [
        'target' => '#reviewEditHelpScores',
        'title' => 'Notation détaillée',
        'text' => 'La fiche classique utilise quatre notes. La note finale est recalculée automatiquement à partir de leur moyenne lorsque vous enregistrez.',
        'tip' => 'Chaque note doit rester comprise entre 0 et la note maximale définie dans l’édition.'
    ];
    $reviewHelpSteps[] = [
        'target' => '#reviewEditHelpOpinion',
        'title' => 'Avis libre',
        'text' => 'Ce champ contient le commentaire rédigé par l’élève pour accompagner ses notes.'
    ];
}

$reviewHelpSteps[] = [
    'target' => '#reviewEditHelpRank',
    'title' => 'Classement personnel',
    'text' => 'Le rang correspond à la position donnée par l’élève à ce manga dans l’édition. Une fiche peut rester non classée en laissant ce champ vide ou à 0.',
    'tip' => 'Si vous remplacez un rang déjà utilisé et que cette fiche avait déjà un autre rang, les deux positions sont échangées automatiquement.'
];
$reviewHelpSteps[] = [
    'target' => '#reviewEditHelpSave',
    'title' => 'Enregistrer les corrections',
    'text' => 'Ce bouton enregistre les modifications apportées à la fiche. Les données visibles par l’administration et utilisées pour les classements sont alors mises à jour.'
];
$reviewHelpSteps[] = [
    'target' => '#reviewEditHelpSummary',
    'title' => 'Résumé de la fiche',
    'text' => 'Cette colonne rappelle le statut, le résultat actuel, le rang personnel ainsi que les dates de création et de dernière modification.'
];
$reviewHelpSteps[] = [
    'target' => '#reviewEditHelpStatusActions',
    'title' => 'Verrouiller, déverrouiller ou supprimer',
    'text' => 'Verrouiller empêche l’élève de modifier la fiche. Déverrouiller lui rend la modification possible. Supprimer efface définitivement la fiche.',
    'tip' => 'Le verrouillage conserve toutes les données. La suppression, elle, est irréversible.'
];

renderAdminHelpGuide([
    'id' => 'admin-review-edit',
    'title' => $isMangaSanSheet ? 'Guide — Fiche Mangasan' : 'Guide — Fiche avec notes',
    'steps' => $reviewHelpSteps,
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

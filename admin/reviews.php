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

$pageTitle = 'Gestion des fiches de lecture - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/admin-reviews-bulk.js',
    '/mangasan/public/assets/js/help-system.js',
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$editionId = filter_input(INPUT_GET, 'edition_id', FILTER_VALIDATE_INT);
$editionId = $editionId === false ? null : $editionId;
$status = trim((string) ($_GET['status'] ?? ''));
$formType = trim((string) ($_GET['form_type'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

$allowedStatuses = ['editable', 'locked'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$allowedFormTypes = ['classic_score', 'mangasan_reading_sheet_v1'];
if (!in_array($formType, $allowedFormTypes, true)) {
    $formType = '';
}

$editionsStmt = $pdo->query(
    "SELECT id, title, year, status, review_form_type, ranking_calculation_method
     FROM editions
     ORDER BY is_active DESC, year DESC, start_date DESC, id DESC"
);
$editions = $editionsStmt->fetchAll();

$sql = "
    SELECT
        reviews.id,
        reviews.user_id,
        reviews.edition_id,
        reviews.manga_id,
        reviews.story_score,
        reviews.art_score,
        reviews.universe_score,
        reviews.message_score,
        reviews.score,
        reviews.personal_rank,
        reviews.review_text,
        reviews.status,
        reviews.is_locked,
        reviews.created_at,
        reviews.updated_at,
        reviews.locked_at,
        editions.title AS edition_title,
        editions.year AS edition_year,
        editions.score_max,
        editions.review_form_type,
        editions.ranking_calculation_method,
        mangas.title AS manga_title,
        COALESCE(
            NULLIF(users.display_name, ''),
            NULLIF(TRIM(CONCAT(users.first_name, ' ', users.last_name)), ''),
            users.username
        ) AS reviewer_name,
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
    WHERE 1 = 1
";

$params = [];

if ($editionId) {
    $sql .= " AND reviews.edition_id = :edition_id";
    $params['edition_id'] = $editionId;
}

if ($status !== '') {
    $sql .= " AND reviews.status = :status";
    $params['status'] = $status;
}

if ($formType !== '') {
    $sql .= " AND editions.review_form_type = :form_type";
    $params['form_type'] = $formType;
}

if ($search !== '') {
    $sql .= "
        AND (
            mangas.title LIKE :search_manga_title
            OR users.username LIKE :search_username
            OR users.display_name LIKE :search_display_name
            OR users.first_name LIKE :search_first_name
            OR users.last_name LIKE :search_last_name
            OR editions.title LIKE :search_edition_title
            OR reviews.review_text LIKE :search_review_text
        )
    ";

    $searchLike = '%' . $search . '%';

    $params['search_manga_title'] = $searchLike;
    $params['search_username'] = $searchLike;
    $params['search_display_name'] = $searchLike;
    $params['search_first_name'] = $searchLike;
    $params['search_last_name'] = $searchLike;
    $params['search_edition_title'] = $searchLike;
    $params['search_review_text'] = $searchLike;
}

$sql .= " ORDER BY editions.is_active DESC, editions.year DESC, reviews.personal_rank ASC, reviews.updated_at DESC, reviews.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$readingSheets = $stmt->fetchAll();

$displayedCount = count($readingSheets);
$lockedCount = 0;
$editableCount = 0;
$userIds = [];

foreach ($readingSheets as $readingSheet) {
    if ((string) $readingSheet['status'] === 'locked') {
        $lockedCount++;
    } else {
        $editableCount++;
    }

    $userIds[(int) $readingSheet['user_id']] = true;
}

$participantCount = count($userIds);

$filterQuery = [];
if ($editionId) {
    $filterQuery['edition_id'] = (int) $editionId;
}
if ($formType !== '') {
    $filterQuery['form_type'] = $formType;
}
if ($status !== '') {
    $filterQuery['status'] = $status;
}
if ($search !== '') {
    $filterQuery['search'] = $search;
}

$currentListUrl = '/mangasan/admin/reviews.php';
if ($filterQuery !== []) {
    $currentListUrl .= '?' . http_build_query($filterQuery);
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-reviews-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="reviewsHelpHeading">
                <div class="admin-page-heading">
                    <h1>Gestion des fiches de lecture</h1>
                    <p>Consulte, corrige, verrouille ou supprime les fiches enregistrées par les élèves.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/rankings.php" class="btn btn-secondary">Classements</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-reviews-page-layout">
                <section class="admin-reviews-overview" id="reviewsHelpOverview" aria-label="Vue d’ensemble des fiches affichées">
                    <article class="admin-review-stat-card">
                        <span>Fiches affichées</span>
                        <strong><?php echo $displayedCount; ?></strong>
                    </article>
                    <article class="admin-review-stat-card">
                        <span>Modifiables</span>
                        <strong><?php echo $editableCount; ?></strong>
                    </article>
                    <article class="admin-review-stat-card">
                        <span>Verrouillées</span>
                        <strong><?php echo $lockedCount; ?></strong>
                    </article>
                    <article class="admin-review-stat-card">
                        <span>Utilisateurs</span>
                        <strong><?php echo $participantCount; ?></strong>
                    </article>
                </section>

                <section class="admin-panel admin-reviews-filter-panel" id="reviewsHelpFilters">
                    <div class="admin-reviews-panel-heading">
                        <div>
                            <h2>Recherche et filtres</h2>
                            <p>Réduis la liste à une édition, un type de fiche, un statut ou une recherche précise.</p>
                        </div>
                        <span class="admin-reviews-result-count"><?php echo $displayedCount; ?> résultat<?php echo $displayedCount > 1 ? 's' : ''; ?></span>
                    </div>

                    <form method="get" action="/mangasan/admin/reviews.php" class="admin-filter-form">
                        <div class="admin-reviews-filter-grid">
                            <div class="admin-field">
                                <label for="edition_id">Édition</label>
                                <select id="edition_id" name="edition_id">
                                    <option value="">Toutes les éditions</option>
                                    <?php foreach ($editions as $edition): ?>
                                        <option value="<?php echo (int) $edition['id']; ?>" <?php echo $editionId === (int) $edition['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($edition['title']); ?> - <?php echo e((string) $edition['year']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="form_type">Type de fiche</label>
                                <select id="form_type" name="form_type">
                                    <option value="">Tous les types</option>
                                    <option value="classic_score" <?php echo $formType === 'classic_score' ? 'selected' : ''; ?>>Fiche avec notes</option>
                                    <option value="mangasan_reading_sheet_v1" <?php echo $formType === 'mangasan_reading_sheet_v1' ? 'selected' : ''; ?>>Fiche Mangasan</option>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="status">Statut</label>
                                <select id="status" name="status">
                                    <option value="">Tous les statuts</option>
                                    <option value="editable" <?php echo $status === 'editable' ? 'selected' : ''; ?>>Modifiable</option>
                                    <option value="locked" <?php echo $status === 'locked' ? 'selected' : ''; ?>>Verrouillée</option>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="search">Recherche</label>
                                <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="Utilisateur, manga, édition, avis...">
                            </div>
                        </div>

                        <div class="admin-filter-actions">
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                            <a href="/mangasan/admin/reviews.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </section>

                <section class="admin-panel admin-reviews-bulk-panel" id="reviewsHelpBulk">
                    <form method="post" action="/mangasan/actions/reviews_bulk_action.php" id="bulkReadingSheetForm" class="admin-reviews-bulk-form">
                        <input type="hidden" name="redirect_to" value="<?php echo e($currentListUrl); ?>">

                        <div class="admin-reviews-bulk-top">
                            <div class="admin-reviews-bulk-summary">
                                <strong>Actions groupées</strong>
                                <span id="reviewsBulkSelectionCount">0 fiche sélectionnée</span>
                            </div>

                            <div class="admin-reviews-bulk-controls">
                                <select id="bulk_action" name="bulk_action" aria-label="Action groupée" required>
                                    <option value="">Choisir une action...</option>
                                    <option value="lock">Verrouiller</option>
                                    <option value="unlock">Déverrouiller</option>
                                    <option value="delete">Supprimer définitivement</option>
                                </select>
                                <button type="submit" class="btn btn-primary" id="reviewsBulkApply">Appliquer</button>
                            </div>
                        </div>

                        <div class="admin-reviews-danger-box is-hidden" id="reviewsBulkDeleteWarning">
                            <strong>Suppression définitive</strong>
                            <span>Les fiches sélectionnées seront supprimées de la base. Cette opération est irréversible.</span>
                            <label class="admin-inline-checkbox">
                                <input type="checkbox" name="confirm_delete" value="1" id="reviewsConfirmDelete">
                                Je confirme la suppression des fiches sélectionnées.
                            </label>
                        </div>
                    </form>
                </section>

                <section class="admin-panel admin-reviews-list-panel" id="reviewsHelpList">
                    <div class="admin-reviews-list-heading">
                        <div>
                            <h2>Fiches de lecture</h2>
                            <p>Utilise la première colonne pour sélectionner plusieurs fiches ou ouvre une fiche pour consulter son contenu complet.</p>
                        </div>
                    </div>

                    <div class="admin-table-wrapper">
                        <table class="admin-table admin-reviews-table">
                            <thead>
                                <tr>
                                    <th class="admin-selection-cell">
                                        <input type="checkbox" id="bulkSelectAll" aria-label="Sélectionner toutes les fiches affichées">
                                    </th>
                                    <th>Utilisateur</th>
                                    <th>Édition</th>
                                    <th>Type</th>
                                    <th>Manga</th>
                                    <th>Résultat</th>
                                    <th>Rang</th>
                                    <th>Statut</th>
                                    <th>Mise à jour</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($readingSheets as $readingSheet): ?>
                                    <?php
                                        $sheetMethod = (string) ($readingSheet['ranking_calculation_method'] ?? 'average_score');
                                        $totalMangas = (int) ($readingSheet['edition_manga_count'] ?? 0);
                                        $personalRank = (int) ($readingSheet['personal_rank'] ?? 0);
                                        $rankPoints = calculateRankPoints($totalMangas, $personalRank);
                                    ?>
                                    <tr class="admin-review-row">
                                        <td class="admin-selection-cell" data-label="Sélection">
                                            <input
                                                type="checkbox"
                                                name="review_ids[]"
                                                value="<?php echo (int) $readingSheet['id']; ?>"
                                                form="bulkReadingSheetForm"
                                                class="bulk-reading-sheet-checkbox"
                                                aria-label="Sélectionner la fiche de <?php echo e($readingSheet['reviewer_name']); ?> pour <?php echo e($readingSheet['manga_title']); ?>"
                                            >
                                        </td>

                                        <td data-label="Utilisateur"><strong><?php echo e($readingSheet['reviewer_name']); ?></strong></td>

                                        <td data-label="Édition">
                                            <strong><?php echo e($readingSheet['edition_title']); ?></strong>
                                            <br>
                                            <span class="admin-cell-muted"><?php echo e((string) $readingSheet['edition_year']); ?></span>
                                            <br>
                                            <span class="admin-cell-muted"><?php echo e(rankingMethodLabel($sheetMethod)); ?></span>
                                        </td>

                                        <td data-label="Type"><?php echo e(reviewFormTypeLabel((string) ($readingSheet['review_form_type'] ?? 'classic_score'))); ?></td>

                                        <td data-label="Manga"><strong><?php echo e($readingSheet['manga_title']); ?></strong></td>

                                        <td data-label="Résultat">
                                            <?php if ($sheetMethod === 'rank_points'): ?>
                                                <span class="admin-score-pill"><?php echo $personalRank > 0 ? $rankPoints . ' pts' : 'Non classée'; ?></span>
                                            <?php else: ?>
                                                <span class="admin-score-pill">
                                                    <?php echo e(number_format((float) $readingSheet['score'], 2, '.', '')); ?> / <?php echo e((string) $readingSheet['score_max']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td data-label="Rang"><?php echo $personalRank > 0 ? $personalRank : '—'; ?></td>

                                        <td data-label="Statut">
                                            <span class="admin-badge <?php echo $readingSheet['status'] === 'locked' ? 'is-locked' : 'is-editable'; ?>">
                                                <?php echo e(readingSheetStatusLabel((string) $readingSheet['status'])); ?>
                                            </span>

                                            <?php if ($readingSheet['status'] === 'locked' && !empty($readingSheet['locked_by_name'])): ?>
                                                <br>
                                                <span class="admin-cell-muted">Par <?php echo e($readingSheet['locked_by_name']); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td data-label="Mise à jour"><?php echo e((string) $readingSheet['updated_at']); ?></td>

                                        <td data-label="Actions">
                                            <div class="admin-actions-inline reviews-help-row-actions">
                                                <a href="/mangasan/admin/review_edit.php?id=<?php echo (int) $readingSheet['id']; ?>&amp;return_to=<?php echo rawurlencode($currentListUrl); ?>" class="btn btn-primary">Ouvrir</a>

                                                <?php if ((string) $readingSheet['status'] === 'locked'): ?>
                                                    <form method="post" action="/mangasan/actions/review_unlock.php">
                                                        <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                                        <input type="hidden" name="redirect_to" value="<?php echo e($currentListUrl); ?>">
                                                        <button type="submit" class="btn btn-secondary">Déverrouiller</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" action="/mangasan/actions/review_lock.php">
                                                        <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                                        <input type="hidden" name="redirect_to" value="<?php echo e($currentListUrl); ?>">
                                                        <button type="submit" class="btn btn-secondary">Verrouiller</button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="post" action="/mangasan/actions/review_delete.php" onsubmit="return confirm('Supprimer cette fiche de lecture ? Cette action est irréversible.');">
                                                    <input type="hidden" name="review_id" value="<?php echo (int) $readingSheet['id']; ?>">
                                                    <input type="hidden" name="redirect_to" value="<?php echo e($currentListUrl); ?>">
                                                    <button type="submit" class="btn btn-danger">Supprimer</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$readingSheets): ?>
                                    <tr>
                                        <td colspan="10">Aucune fiche de lecture ne correspond aux filtres actuels.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </section>
</main>

<?php
renderAdminHelpGuide([
    'id' => 'admin-reviews',
    'title' => 'Guide — Fiches de lecture',
    'steps' => [
        [
            'target' => '#reviewsHelpHeading',
            'title' => 'Gestion des fiches de lecture',
            'text' => 'Cette page regroupe les fiches enregistrées par les élèves. Vous pouvez les rechercher, les ouvrir, les verrouiller, les déverrouiller ou les supprimer.',
            'tip' => 'Le bouton Classements permet de consulter les résultats calculés à partir de ces fiches.'
        ],
        [
            'target' => '#reviewsHelpOverview',
            'title' => 'Vue d’ensemble',
            'text' => 'Ces compteurs résument uniquement les fiches actuellement affichées après application des filtres : total, fiches modifiables, fiches verrouillées et nombre d’utilisateurs concernés.'
        ],
        [
            'target' => '#reviewsHelpFilters',
            'title' => 'Rechercher et filtrer',
            'text' => 'Vous pouvez limiter la liste à une édition, un type de fiche, un statut ou rechercher un utilisateur, un manga, une édition ou du texte contenu dans un avis.',
            'tip' => 'Réinitialiser retire tous les filtres et réaffiche l’ensemble des fiches.'
        ],
        [
            'target' => '#reviewsHelpBulk',
            'title' => 'Actions groupées',
            'text' => 'Cochez plusieurs fiches dans le tableau puis choisissez une action pour les verrouiller, les déverrouiller ou les supprimer en une seule opération.',
            'tip' => 'La suppression groupée demande une confirmation supplémentaire car elle est définitive.'
        ],
        [
            'target' => '#reviewsHelpList',
            'title' => 'Lire la liste',
            'text' => 'Chaque ligne indique l’élève, l’édition, le type de fiche, le manga, le résultat utilisé pour le classement, le rang personnel et le statut de la fiche.'
        ],
        [
            'target' => '.reviews-help-row-actions',
            'title' => 'Actions sur une fiche',
            'text' => 'Ouvrir affiche le contenu complet et permet une correction administrative. Verrouiller empêche l’élève de modifier sa fiche. Déverrouiller lui rend la modification possible. Supprimer efface définitivement la fiche.',
            'tip' => 'Le verrouillage ne supprime aucune donnée : il bloque uniquement les modifications côté élève.'
        ],
    ],
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

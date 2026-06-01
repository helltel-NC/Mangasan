<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/rankings.php';

requireAdmin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminRankingMethodLabel(?string $method): string
{
    return match ((string) $method) {
        'rank_points' => 'Points selon le rang personnel',
        'average', 'average_score' => 'Moyenne des notes',
        default => (string) ($method ?? 'average_score')
    };
}

function adminReviewFormTypeLabel(?string $formType): string
{
    return match ((string) $formType) {
        'mangasan_reading_sheet_v1' => 'Fiche Manga San',
        'classic_score' => 'Fiche avec notes',
        default => (string) ($formType ?? 'classic_score')
    };
}

function adminEditionStatusLabel(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Brouillon',
        'active' => 'Active',
        'closed' => 'Clôturée',
        'archived' => 'Archivée',
        default => (string) ($status ?? '')
    };
}

function adminFormatRankingPrimaryValue(array $row, array $edition): string
{
    $method = (string) ($edition['ranking_calculation_method'] ?? 'average_score');

    if ($method === 'rank_points') {
        if ((int) ($row['reviews_count'] ?? 0) < 1) {
            return '—';
        }

        return (string) ((int) ($row['total_points'] ?? 0)) . ' pts';
    }

    if ((int) ($row['reviews_count'] ?? 0) < 1 || $row['average_score'] === null) {
        return '—';
    }

    return number_format((float) $row['average_score'], 2, ',', ' ') . ' / ' . (int) ($edition['score_max'] ?? 20);
}

function adminFormatDateRange(?string $startDate, ?string $endDate): string
{
    $startDate = trim((string) $startDate);
    $endDate = trim((string) $endDate);

    if ($startDate === '' && $endDate === '') {
        return '—';
    }

    if ($startDate !== '' && $endDate !== '') {
        return $startDate . ' → ' . $endDate;
    }

    return $startDate !== '' ? 'Depuis ' . $startDate : 'Jusqu’au ' . $endDate;
}

$pageTitle = 'Classements des éditions - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$editionsStmt = $pdo->query(
    "SELECT
        id,
        title,
        year,
        description,
        status,
        is_active,
        review_form_type,
        ranking_calculation_method,
        score_max,
        general_ranking_visibility,
        general_ranking_access,
        start_date,
        end_date
     FROM editions
     ORDER BY is_active DESC, year DESC, start_date DESC, id DESC"
);
$editions = $editionsStmt->fetchAll();

$requestedEditionId = filter_input(INPUT_GET, 'edition_id', FILTER_VALIDATE_INT);
$selectedEdition = null;

if ($requestedEditionId) {
    foreach ($editions as $edition) {
        if ((int) $edition['id'] === $requestedEditionId) {
            $selectedEdition = $edition;
            break;
        }
    }
}

if ($selectedEdition === null) {
    foreach ($editions as $edition) {
        if ((int) $edition['is_active'] === 1) {
            $selectedEdition = $edition;
            break;
        }
    }
}

if ($selectedEdition === null && $editions) {
    $selectedEdition = $editions[0];
}

$selectedEditionId = $selectedEdition ? (int) $selectedEdition['id'] : 0;
$rankingRows = [];
$editionStats = [
    'visible_mangas' => 0,
    'reviews_count' => 0,
    'reviewers_count' => 0,
    'locked_reviews' => 0
];

if ($selectedEditionId > 0) {
    $rankingRows = getEditionRanking($pdo, $selectedEditionId);

    $statsStmt = $pdo->prepare(
        "SELECT
            (
                SELECT COUNT(*)
                FROM edition_mangas
                INNER JOIN mangas ON mangas.id = edition_mangas.manga_id
                WHERE edition_mangas.edition_id = :edition_id_visible
                  AND edition_mangas.is_visible = 1
                  AND mangas.status = 'active'
            ) AS visible_mangas,
            (
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.edition_id = :edition_id_reviews
            ) AS reviews_count,
            (
                SELECT COUNT(DISTINCT reviews.user_id)
                FROM reviews
                WHERE reviews.edition_id = :edition_id_reviewers
            ) AS reviewers_count,
            (
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.edition_id = :edition_id_locked
                  AND reviews.is_locked = 1
            ) AS locked_reviews"
    );

    $statsStmt->execute([
        'edition_id_visible' => $selectedEditionId,
        'edition_id_reviews' => $selectedEditionId,
        'edition_id_reviewers' => $selectedEditionId,
        'edition_id_locked' => $selectedEditionId
    ]);

    $statsRow = $statsStmt->fetch();

    if ($statsRow) {
        $editionStats = [
            'visible_mangas' => (int) ($statsRow['visible_mangas'] ?? 0),
            'reviews_count' => (int) ($statsRow['reviews_count'] ?? 0),
            'reviewers_count' => (int) ($statsRow['reviewers_count'] ?? 0),
            'locked_reviews' => (int) ($statsRow['locked_reviews'] ?? 0)
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Classements des éditions</h1>
                    <p>Consulte le classement général de l’édition active ou d’une ancienne édition.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/reviews.php<?php echo $selectedEditionId > 0 ? '?edition_id=' . $selectedEditionId : ''; ?>" class="btn btn-secondary">Voir les fiches</a>
                    <a href="/mangasan/admin/editions.php" class="btn btn-secondary">Gérer les éditions</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-panel">
                <form method="get" action="/mangasan/admin/rankings.php" class="admin-filter-form">
                    <div class="admin-form-grid">
                        <div class="admin-field">
                            <label for="edition_id">Édition à consulter</label>
                            <select id="edition_id" name="edition_id">
                                <?php foreach ($editions as $edition): ?>
                                    <option value="<?php echo (int) $edition['id']; ?>" <?php echo $selectedEditionId === (int) $edition['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($edition['title']); ?> - <?php echo e((string) $edition['year']); ?>
                                        <?php echo (int) $edition['is_active'] === 1 ? ' (active)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="admin-field admin-field-actions">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Afficher le classement</button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($selectedEdition): ?>
                <div class="admin-panel">
                    <div class="admin-summary-grid">
                        <div class="admin-summary-card">
                            <span>Édition</span>
                            <strong>
                                <?php echo e($selectedEdition['title']); ?>
                                <?php echo (int) $selectedEdition['is_active'] === 1 ? ' · Active' : ''; ?>
                            </strong>
                        </div>

                        <div class="admin-summary-card">
                            <span>Méthode</span>
                            <strong><?php echo e(adminRankingMethodLabel((string) $selectedEdition['ranking_calculation_method'])); ?></strong>
                        </div>

                        <div class="admin-summary-card">
                            <span>Type de fiche</span>
                            <strong><?php echo e(adminReviewFormTypeLabel((string) $selectedEdition['review_form_type'])); ?></strong>
                        </div>

                        <div class="admin-summary-card">
                            <span>Période</span>
                            <strong><?php echo e(adminFormatDateRange($selectedEdition['start_date'] ?? null, $selectedEdition['end_date'] ?? null)); ?></strong>
                        </div>

                        <div class="admin-summary-card">
                            <span>Mangas visibles</span>
                            <strong><?php echo (int) $editionStats['visible_mangas']; ?></strong>
                        </div>

                        <div class="admin-summary-card">
                            <span>Fiches reçues</span>
                            <strong><?php echo (int) $editionStats['reviews_count']; ?></strong>
                        </div>

                        <div class="admin-summary-card">
                            <span>Participants</span>
                            <strong><?php echo (int) $editionStats['reviewers_count']; ?></strong>
                        </div>

                        <div class="admin-summary-card">
                            <span>Fiches verrouillées</span>
                            <strong><?php echo (int) $editionStats['locked_reviews']; ?></strong>
                        </div>
                    </div>
                </div>

                <div class="admin-panel">
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Classement</th>
                                    <th>Manga</th>
                                    <th>Résultat</th>
                                    <th>Fiches</th>
                                    <th>Rang moyen</th>
                                    <th>Moyenne</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($rankingRows as $rankingItem): ?>
                                    <?php
                                    $hasReviews = (int) $rankingItem['reviews_count'] > 0;
                                    $reviewsUrl = '/mangasan/admin/reviews.php?edition_id=' . $selectedEditionId . '&search=' . urlencode((string) $rankingItem['title']);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($rankingItem['display_rank'] !== null): ?>
                                                <strong>#<?php echo (int) $rankingItem['display_rank']; ?></strong>
                                            <?php else: ?>
                                                <span class="admin-cell-muted">Non classé</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <strong><?php echo e((string) $rankingItem['title']); ?></strong>
                                            <?php if (!empty($rankingItem['subtitle'])): ?>
                                                <br>
                                                <span class="admin-cell-muted"><?php echo e((string) $rankingItem['subtitle']); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <strong><?php echo e(adminFormatRankingPrimaryValue($rankingItem, $selectedEdition)); ?></strong>
                                        </td>

                                        <td>
                                            <?php echo (int) $rankingItem['reviews_count']; ?>
                                        </td>

                                        <td>
                                            <?php if ($rankingItem['average_rank'] !== null): ?>
                                                <?php echo e(number_format((float) $rankingItem['average_rank'], 2, ',', ' ')); ?>
                                            <?php else: ?>
                                                <span class="admin-cell-muted">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($rankingItem['average_score'] !== null && $hasReviews): ?>
                                                <?php echo e(number_format((float) $rankingItem['average_score'], 2, ',', ' ')); ?>
                                                / <?php echo (int) $selectedEdition['score_max']; ?>
                                            <?php else: ?>
                                                <span class="admin-cell-muted">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="admin-actions-inline">
                                                <a href="<?php echo e($reviewsUrl); ?>" class="btn btn-secondary">Voir fiches</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$rankingRows): ?>
                                    <tr>
                                        <td colspan="7">Aucun manga visible pour cette édition.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert error">
                    Aucune édition n’existe encore. Crée une édition avant de consulter un classement.
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

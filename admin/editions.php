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

function editionStatusLabel(string $status): string
{
    return match ($status) {
        'draft' => 'Brouillon',
        'active' => 'Active',
        'closed' => 'Clôturée',
        'archived' => 'Archivée',
        default => $status
    };
}


$pageTitle = 'Gestion des éditions - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$stmt = $pdo->query(
    "SELECT
        editions.id,
        editions.title,
        editions.year,
        editions.description,
        editions.status,
        editions.is_active,
        editions.general_ranking_visibility,
        editions.general_ranking_access,
        editions.ranking_calculation_method,
        editions.score_max,
        editions.start_date,
        editions.end_date,
        editions.created_at,
        editions.updated_at,
        (
            SELECT COUNT(*)
            FROM edition_mangas
            WHERE edition_mangas.edition_id = editions.id
        ) AS mangas_count,
        (
            SELECT COUNT(*)
            FROM reviews
            WHERE reviews.edition_id = editions.id
        ) AS reviews_count
     FROM editions
     ORDER BY editions.is_active DESC, editions.year DESC, editions.start_date DESC, editions.id DESC"
);

$editions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Gestion des éditions</h1>
                    <p>Crée, modifie, active, archive ou supprime les éditions Mangasan.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/edition_edit.php" class="btn btn-primary">Créer une édition</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-panel">
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Année</th>
                                <th>Statut</th>
                                <th>Active</th>
                                <th>Dates</th>
                                <th>Mangas</th>
                                <th>Reviews</th>
                                <th>Classement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            <?php foreach ($editions as $edition): ?>
                <tr>
                    <td>
                        <strong><?php echo e($edition['title']); ?></strong>
                        <?php if (!empty($edition['description'])): ?>
                            <br>
                            <span class="admin-cell-muted">
                                <?php echo e(mb_strimwidth((string) $edition['description'], 0, 90, '...')); ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <td><?php echo e((string) $edition['year']); ?></td>

                    <td>
                        <span class="admin-badge <?php echo $edition['status'] === 'active' ? 'is-visible' : 'is-hidden'; ?>">
                            <?php echo e(editionStatusLabel((string) $edition['status'])); ?>
                        </span>
                    </td>

                    <td>
                        <?php echo (int) $edition['is_active'] === 1 ? 'Oui' : 'Non'; ?>
                    </td>

                    <td>
                        <?php echo e((string) $edition['start_date']); ?>
                        <br>
                        <span class="admin-cell-muted"><?php echo e((string) $edition['end_date']); ?></span>
                    </td>

                    <td><?php echo (int) $edition['mangas_count']; ?></td>

                    <td><?php echo (int) $edition['reviews_count']; ?></td>

                    <td>
                        <?php echo (string) $edition['general_ranking_visibility'] === 'visible' ? 'Visible' : 'Masqué'; ?>
                        <br>
                        <span class="admin-cell-muted">
                            <?php echo (string) $edition['general_ranking_access'] === 'public' ? 'Public' : 'Membres'; ?>
                        </span>
                        <br>
                        <span class="admin-cell-muted">
                            <?php echo e((string) $edition['ranking_calculation_method']); ?> - /<?php echo (int) $edition['score_max']; ?>
                        </span>
                    </td>

                    <td>
                        <div class="admin-actions-inline">
                            <a href="/mangasan/admin/edition_edit.php?id=<?php echo (int) $edition['id']; ?>" class="btn btn-primary">Modifier</a>

                            <form method="post" action="/mangasan/actions/edition_toggle_active.php">
                                <input type="hidden" name="edition_id" value="<?php echo (int) $edition['id']; ?>">
                                <button type="submit" class="btn btn-secondary">
                                    <?php echo (int) $edition['is_active'] === 1 ? 'Désactiver' : 'Activer'; ?>
                                </button>
                            </form>

                            <form method="post" action="/mangasan/actions/edition_delete.php" onsubmit="return confirm('Supprimer cette édition ? Les liaisons mangas et les reviews liées seront aussi supprimées.');">
                                <input type="hidden" name="edition_id" value="<?php echo (int) $edition['id']; ?>">
                                <button type="submit" class="btn btn-secondary">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

                            <?php if (!$editions): ?>
                                <tr>
                                    <td colspan="9">Aucune édition trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
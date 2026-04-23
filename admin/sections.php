<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
requireAdmin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Gestion des sections - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];

$flashMessages = getFlashMessages();

$stmt = $pdo->query(
    'SELECT
        site_sections.id,
        site_sections.section_key,
        site_sections.section_type,
        site_sections.title,
        site_sections.subtilte,
        site_sections.display_order,
        site_sections.is_visible,
        site_sections.updated_at,
        COALESCE(
            NULLIF(users.display_name, \'\'),
            NULLIF(TRIM(CONCAT(users.first_name, \' \', users.last_name)), \'\'),
            users.username
        ) AS updated_by_name
     FROM site_sections
     LEFT JOIN users ON users.id = site_sections.updated_by
     ORDER BY site_sections.display_order ASC, site_sections.id ASC'
);

$sections = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Gestion des sections</h1>
                    <p>Modifie les contenus, l’ordre d’affichage et la visibilité des sections du site public.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/setting.php" class="btn btn-primary">Paramètres du site</a>
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
                                <th>Ordre</th>
                                <th>Clé</th>
                                <th>Type</th>
                                <th>Titre</th>
                                <th>Visibilité</th>
                                <th>Mis à jour le</th>
                                <th>Mis à jour par</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sections as $section): ?>
                                <tr>
                                    <td><?php echo (int) $section['display_order']; ?></td>
                                    <td><?php echo e($section['section_key']); ?></td>
                                    <td><?php echo e($section['section_type']); ?></td>
                                    <td>
                                        <strong><?php echo e($section['title']); ?></strong>
                                        <?php if (!empty($section['subtilte'])): ?>
                                            <br>
                                            <span class="admin-cell-muted"><?php echo e($section['subtilte']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="admin-badge <?php echo (int) $section['is_visible'] === 1 ? 'is-visible' : 'is-hidden'; ?>">
                                            <?php echo (int) $section['is_visible'] === 1 ? 'Visible' : 'Masquée'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo e($section['updated_at']); ?></td>
                                    <td><?php echo e($section['updated_by_name'] ?? '—'); ?></td>
                                    <td>
                                        <div class="admin-actions-inline">
                                            <a href="/mangasan/admin/section_edit.php?id=<?php echo (int) $section['id']; ?>" class="btn btn-primary">Modifier</a>

                                            <form method="post" action="/mangasan/actions/section_toggle_visibility.php">
                                                <input type="hidden" name="section_id" value="<?php echo (int) $section['id']; ?>">
                                                <button type="submit" class="btn btn-secondary">
                                                    <?php echo (int) $section['is_visible'] === 1 ? 'Masquer' : 'Afficher'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$sections): ?>
                                <tr>
                                    <td colspan="8">Aucune section trouvée.</td>
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
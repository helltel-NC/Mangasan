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

function formatSectionDate(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : $value;
}

$pageTitle = 'Gestion des sections - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/help-system.js',
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
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
$visibleCount = 0;

foreach ($sections as $section) {
    if ((int) $section['is_visible'] === 1) {
        ++$visibleCount;
    }
}

$hiddenCount = count($sections) - $visibleCount;

$helpGuide = [
    'id' => 'admin-sections',
    'title' => 'Guide — Sections du site',
    'steps' => [
        [
            'target' => '#sectionsHelpHeading',
            'title' => 'Sections du site',
            'text' => 'Cette page regroupe les blocs de contenu affichés sur le site public. Les sections sont déjà créées : ici, tu modifies leur contenu, leur ordre et leur visibilité.',
        ],
        [
            'target' => '#sectionsHelpStats',
            'title' => 'Vue d’ensemble',
            'text' => 'Ces compteurs indiquent combien de sections existent et combien sont actuellement visibles ou masquées.',
        ],
        [
            'target' => '#sectionsHelpList',
            'title' => 'Liste des sections',
            'text' => 'Les sections sont affichées dans leur ordre actuel. La clé et le type sont des informations techniques ; le titre correspond au contenu visible sur le site.',
        ],
        [
            'target' => '.admin-section-order',
            'title' => 'Ordre d’affichage',
            'text' => 'Le numéro indique la position de la section sur le site public. L’ordre se modifie depuis la fiche de la section.',
        ],
        [
            'target' => '.admin-section-visibility',
            'title' => 'Visibilité',
            'text' => 'Une section visible apparaît sur le site public. Une section masquée reste enregistrée mais n’est plus affichée aux visiteurs.',
        ],
        [
            'target' => '.admin-section-actions',
            'title' => 'Modifier ou afficher/masquer',
            'text' => 'Modifier ouvre le contenu complet de la section. Le second bouton permet de changer rapidement sa visibilité sans ouvrir la fiche.',
        ],
        [
            'target' => '#sectionsHelpSettings',
            'title' => 'Paramètres du site',
            'text' => 'Ce bouton ouvre les réglages généraux de Mangasan : identité, couleurs, bandeau d’accueil et effets visuels.',
        ],
    ],
];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-sections-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="sectionsHelpHeading">
                <div class="admin-page-heading">
                    <h1>Gestion des sections</h1>
                    <p>Gère les contenus et la visibilité des sections du site public.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/setting.php" class="btn btn-primary" id="sectionsHelpSettings">Paramètres du site</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <section class="admin-sections-stats" id="sectionsHelpStats" aria-label="Résumé des sections">
                <article class="admin-section-stat-card">
                    <span>Sections</span>
                    <strong><?php echo count($sections); ?></strong>
                </article>

                <article class="admin-section-stat-card">
                    <span>Visibles</span>
                    <strong><?php echo $visibleCount; ?></strong>
                </article>

                <article class="admin-section-stat-card">
                    <span>Masquées</span>
                    <strong><?php echo $hiddenCount; ?></strong>
                </article>
            </section>

            <section class="admin-panel admin-sections-list-panel" id="sectionsHelpList">
                <div class="admin-sections-list-head">
                    <div>
                        <h2>Sections enregistrées</h2>
                        <p>Affichées dans l’ordre actuel du site.</p>
                    </div>
                    <span><?php echo count($sections); ?> section<?php echo count($sections) > 1 ? 's' : ''; ?></span>
                </div>

                <?php if ($sections): ?>
                    <div class="admin-sections-list">
                        <?php foreach ($sections as $section): ?>
                            <article class="admin-section-row">
                                <div class="admin-section-order" aria-label="Ordre d’affichage">
                                    <span>Ordre</span>
                                    <strong><?php echo (int) $section['display_order']; ?></strong>
                                </div>

                                <div class="admin-section-main">
                                    <div class="admin-section-title-line">
                                        <h3><?php echo e((string) $section['title']); ?></h3>
                                        <span class="admin-badge admin-section-visibility <?php echo (int) $section['is_visible'] === 1 ? 'is-visible' : 'is-hidden'; ?>">
                                            <?php echo (int) $section['is_visible'] === 1 ? 'Visible' : 'Masquée'; ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($section['subtilte'])): ?>
                                        <p class="admin-section-subtitle"><?php echo e((string) $section['subtilte']); ?></p>
                                    <?php endif; ?>

                                    <div class="admin-section-meta">
                                        <span><strong>Clé :</strong> <?php echo e((string) $section['section_key']); ?></span>
                                        <span><strong>Type :</strong> <?php echo e((string) $section['section_type']); ?></span>
                                        <span><strong>Mis à jour :</strong> <?php echo e(formatSectionDate((string) $section['updated_at'])); ?></span>
                                        <span><strong>Par :</strong> <?php echo e((string) ($section['updated_by_name'] ?? '—')); ?></span>
                                    </div>
                                </div>

                                <div class="admin-section-actions">
                                    <a href="/mangasan/admin/section_edit.php?id=<?php echo (int) $section['id']; ?>" class="btn btn-primary">Modifier</a>

                                    <form method="post" action="/mangasan/actions/section_toggle_visibility.php">
                                        <input type="hidden" name="section_id" value="<?php echo (int) $section['id']; ?>">
                                        <button type="submit" class="btn btn-secondary">
                                            <?php echo (int) $section['is_visible'] === 1 ? 'Masquer' : 'Afficher'; ?>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state">Aucune section trouvée.</div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php renderAdminHelpGuide($helpGuide); ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

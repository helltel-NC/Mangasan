<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/help.php';

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
requireAdmin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatAdminDate(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Non définie';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y', $timestamp);
}

$pageTitle = 'Administration - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/admin-dashboard.js',
    '/mangasan/public/assets/js/help-system.js',
];

$currentUserId = getCurrentUserId();
$currentUsername = getCurrentUsername();
$currentRole = getCurrentUserRole();

$adminDisplayName = $currentUsername ?? 'Administrateur';

if ($currentUserId !== null) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(
            NULLIF(display_name, ''),
            NULLIF(TRIM(CONCAT(first_name, ' ', last_name)), ''),
            username
        ) AS admin_name
        FROM users
        WHERE id = :id
        LIMIT 1"
    );
    $stmt->execute([
        'id' => $currentUserId,
    ]);

    $adminRow = $stmt->fetch();

    if ($adminRow && !empty($adminRow['admin_name'])) {
        $adminDisplayName = $adminRow['admin_name'];
    }
}

$stats = [
    'users_total' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'users_active' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'admins_total' => (int) $pdo->query("
        SELECT COUNT(*)
        FROM users
        INNER JOIN roles ON roles.id = users.role_id
        WHERE roles.name = 'admin'
    ")->fetchColumn(),
    'editions_total' => (int) $pdo->query("SELECT COUNT(*) FROM editions")->fetchColumn(),
    'editions_active' => (int) $pdo->query("SELECT COUNT(*) FROM editions WHERE is_active = 1")->fetchColumn(),
    'mangas_total' => (int) $pdo->query("SELECT COUNT(*) FROM mangas")->fetchColumn(),
    'reviews_total' => (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'reviews_locked' => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_locked = 1")->fetchColumn(),
    'sections_total' => (int) $pdo->query("SELECT COUNT(*) FROM site_sections")->fetchColumn(),
    'sections_visible' => (int) $pdo->query("SELECT COUNT(*) FROM site_sections WHERE is_visible = 1")->fetchColumn(),
];

$activeEditionStmt = $pdo->query(
    "SELECT id, title, year, status, start_date, end_date
    FROM editions
    WHERE is_active = 1
    ORDER BY updated_at DESC
    LIMIT 1"
);
$activeEdition = $activeEditionStmt->fetch();

$activeEditionStats = [
    'mangas' => 0,
    'reviews' => 0,
    'participants' => 0,
];

if ($activeEdition) {
    $editionId = (int) $activeEdition['id'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM edition_mangas WHERE edition_id = :edition_id AND is_visible = 1");
    $stmt->execute(['edition_id' => $editionId]);
    $activeEditionStats['mangas'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE edition_id = :edition_id");
    $stmt->execute(['edition_id' => $editionId]);
    $activeEditionStats['reviews'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM reviews WHERE edition_id = :edition_id");
    $stmt->execute(['edition_id' => $editionId]);
    $activeEditionStats['participants'] = (int) $stmt->fetchColumn();
}

$siteSettingsStmt = $pdo->query(
    "SELECT
        site_title,
        primary_color,
        secondary_color,
        background_color,
        text_color,
        accent_color,
        hero_background_type,
        hero_background_value,
        hero_login_position,
        updated_at
    FROM site_settings
    ORDER BY id ASC
    LIMIT 1"
);
$siteSettings = $siteSettingsStmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="admin-dashboard-hero">
        <div class="container admin-dashboard-hero-inner">
            <div class="admin-dashboard-heading" id="dashboardHelpHeading">
                <p class="hero-kicker">Espace administrateur</p>
                <h1>Administration Mangasan</h1>
                <p>
                    Gérez les utilisateurs, les éditions, les mangas, les fiches de lecture
                    et la configuration du site depuis un seul espace.
                </p>
            </div>

            <div class="admin-dashboard-session" aria-label="Session administrateur">
                <div class="admin-dashboard-session-user">
                    <span>Connecté en tant que</span>
                    <strong><?php echo e($adminDisplayName); ?></strong>
                    <small><?php echo e($currentUsername); ?> · <?php echo e($currentRole); ?></small>
                </div>

                <div class="admin-dashboard-session-actions" id="dashboardHelpSessionActions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/public/index.php" class="btn btn-secondary">Retour au site</a>
                    <a href="/mangasan/actions/logout.php" class="btn admin-logout-btn">Déconnexion</a>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section admin-dashboard-content">
        <div class="container">
            <div class="admin-dashboard-tabs" id="dashboardHelpTabs" role="tablist" aria-label="Sections du tableau de bord">
                <button
                    type="button"
                    class="admin-dashboard-tab is-active"
                    id="admin-tab-overview"
                    role="tab"
                    aria-selected="true"
                    aria-controls="admin-panel-overview"
                    data-admin-tab="overview"
                >
                    Vue d’ensemble
                </button>

                <button
                    type="button"
                    class="admin-dashboard-tab"
                    id="admin-tab-configuration"
                    role="tab"
                    aria-selected="false"
                    aria-controls="admin-panel-configuration"
                    data-admin-tab="configuration"
                >
                    Configuration du site
                </button>
            </div>

            <div
                class="admin-dashboard-tab-panel is-active"
                id="admin-panel-overview"
                role="tabpanel"
                aria-labelledby="admin-tab-overview"
                data-admin-panel="overview"
            >
                <div class="admin-dashboard-section-heading" id="dashboardHelpOverviewHeading">
                    <div>
                        <p class="section-kicker">Vue d’ensemble</p>
                        <h2>État actuel de Mangasan</h2>
                    </div>
                    <p>Les informations essentielles avant d’accéder aux différents outils de gestion.</p>
                </div>

                <div class="admin-overview-stats" id="dashboardHelpOverviewStats">
                    <article class="admin-stat-card">
                        <span>Utilisateurs</span>
                        <strong><?php echo (int) $stats['users_total']; ?></strong>
                        <small><?php echo (int) $stats['users_active']; ?> actifs · <?php echo (int) $stats['admins_total']; ?> administrateur(s)</small>
                    </article>

                    <article class="admin-stat-card">
                        <span>Mangas</span>
                        <strong><?php echo (int) $stats['mangas_total']; ?></strong>
                        <small>dans le catalogue Mangasan</small>
                    </article>

                    <article class="admin-stat-card">
                        <span>Fiches de lecture</span>
                        <strong><?php echo (int) $stats['reviews_total']; ?></strong>
                        <small><?php echo (int) $stats['reviews_locked']; ?> verrouillée(s)</small>
                    </article>

                    <article class="admin-stat-card">
                        <span>Éditions</span>
                        <strong><?php echo (int) $stats['editions_total']; ?></strong>
                        <small><?php echo (int) $stats['editions_active']; ?> édition(s) active(s)</small>
                    </article>
                </div>

                <article class="admin-active-edition-card" id="dashboardHelpActiveEdition">
                    <div class="admin-active-edition-main">
                        <p class="section-kicker">Édition active</p>

                        <?php if ($activeEdition): ?>
                            <h2><?php echo e($activeEdition['title']); ?></h2>
                            <p>
                                <?php echo e((string) $activeEdition['year']); ?> ·
                                du <?php echo e(formatAdminDate($activeEdition['start_date'])); ?>
                                au <?php echo e(formatAdminDate($activeEdition['end_date'])); ?> ·
                                statut : <?php echo e($activeEdition['status']); ?>
                            </p>
                        <?php else: ?>
                            <h2>Aucune édition active</h2>
                            <p>Activez une édition pour afficher ici son état et ses statistiques.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($activeEdition): ?>
                        <div class="admin-active-edition-stats">
                            <div>
                                <strong><?php echo (int) $activeEditionStats['mangas']; ?></strong>
                                <span>Mangas visibles</span>
                            </div>
                            <div>
                                <strong><?php echo (int) $activeEditionStats['participants']; ?></strong>
                                <span>Participants</span>
                            </div>
                            <div>
                                <strong><?php echo (int) $activeEditionStats['reviews']; ?></strong>
                                <span>Fiches</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="admin-active-edition-actions">
                        <a href="/mangasan/admin/editions.php" class="btn btn-primary">Gérer les éditions</a>
                        <?php if ($activeEdition): ?>
                            <a href="/mangasan/admin/rankings.php?edition_id=<?php echo (int) $activeEdition['id']; ?>" class="btn btn-secondary">Voir le classement</a>
                        <?php endif; ?>
                    </div>
                </article>

                <div class="admin-dashboard-section-heading admin-dashboard-management-heading">
                    <div>
                        <p class="section-kicker">Gestion</p>
                        <h2>Outils d’administration</h2>
                    </div>
                </div>

                <div class="admin-management-grid" id="dashboardHelpManagement">
                    <article class="admin-management-card">
                        <div>
                            <h3>Utilisateurs</h3>
                            <p>Comptes élèves et administrateurs, filtres, impression et actions groupées.</p>
                        </div>
                        <a href="/mangasan/admin/users.php" class="btn btn-primary">Gérer les utilisateurs</a>
                    </article>

                    <article class="admin-management-card">
                        <div>
                            <h3>Éditions</h3>
                            <p>Création des éditions, périodes, activation et paramètres de classement.</p>
                        </div>
                        <a href="/mangasan/admin/editions.php" class="btn btn-primary">Gérer les éditions</a>
                    </article>

                    <article class="admin-management-card">
                        <div>
                            <h3>Mangas</h3>
                            <p>Catalogue, informations des mangas et rattachement aux différentes éditions.</p>
                        </div>
                        <a href="/mangasan/admin/mangas.php" class="btn btn-primary">Gérer les mangas</a>
                    </article>

                    <article class="admin-management-card">
                        <div>
                            <h3>Fiches de lecture</h3>
                            <p>Consultation, modification, verrouillage et suivi des fiches remplies.</p>
                        </div>
                        <a href="/mangasan/admin/reviews.php" class="btn btn-primary">Gérer les fiches</a>
                    </article>

                    <article class="admin-management-card">
                        <div>
                            <h3>Classements</h3>
                            <p>Consultez les résultats de l’édition active ou des anciennes éditions.</p>
                        </div>
                        <a href="/mangasan/admin/rankings.php" class="btn btn-primary">Voir les classements</a>
                    </article>
                </div>
            </div>

            <div
                class="admin-dashboard-tab-panel"
                id="admin-panel-configuration"
                role="tabpanel"
                aria-labelledby="admin-tab-configuration"
                data-admin-panel="configuration"
                hidden
            >
                <div class="admin-dashboard-section-heading" id="dashboardHelpConfigurationHeading">
                    <div>
                        <p class="section-kicker">Configuration</p>
                        <h2>Configuration du site</h2>
                    </div>
                    <p>Personnalisez l’apparence et les contenus visibles sur la partie publique de Mangasan.</p>
                </div>

                <div class="admin-configuration-summary" id="dashboardHelpConfigurationSummary">
                    <article class="admin-config-state-card">
                        <span>Titre du site</span>
                        <strong><?php echo e($siteSettings['site_title'] ?? 'Mangasan'); ?></strong>
                    </article>

                    <article class="admin-config-state-card">
                        <span>Fond du bandeau</span>
                        <strong><?php echo e($siteSettings['hero_background_type'] ?? 'Non défini'); ?></strong>
                    </article>

                    <article class="admin-config-state-card">
                        <span>Position de connexion</span>
                        <strong><?php echo e($siteSettings['hero_login_position'] ?? 'Non définie'); ?></strong>
                    </article>

                    <article class="admin-config-state-card">
                        <span>Sections publiques</span>
                        <strong><?php echo (int) $stats['sections_visible']; ?> / <?php echo (int) $stats['sections_total']; ?></strong>
                    </article>
                </div>

                <div class="admin-configuration-grid" id="dashboardHelpConfigurationTools">
                    <article class="admin-configuration-card">
                        <div>
                            <p class="section-kicker">Apparence</p>
                            <h3>Paramètres du site</h3>
                            <p>
                                Modifiez le titre, le logo, la bannière, les couleurs, les textes du bandeau
                                et les effets visuels du site public.
                            </p>
                            <?php if ($siteSettings && !empty($siteSettings['updated_at'])): ?>
                                <small>Dernière modification : <?php echo e($siteSettings['updated_at']); ?></small>
                            <?php endif; ?>
                        </div>
                        <a href="/mangasan/admin/setting.php" class="btn btn-primary">Configurer l’apparence</a>
                    </article>

                    <article class="admin-configuration-card">
                        <div>
                            <p class="section-kicker">Contenu public</p>
                            <h3>Sections du site</h3>
                            <p>
                                Gérez les sections affichées sur l’accueil, leur contenu, leur ordre
                                et leur visibilité.
                            </p>
                            <small><?php echo (int) $stats['sections_visible']; ?> section(s) visible(s) sur <?php echo (int) $stats['sections_total']; ?></small>
                        </div>
                        <a href="/mangasan/admin/sections.php" class="btn btn-primary">Gérer les sections</a>
                    </article>

                    <article class="admin-configuration-card">
                        <div>
                            <p class="section-kicker">Mise en page</p>
                            <h3>Éditeur visuel</h3>
                            <p>
                                Organisez directement le site public sur une grille : déplacez les blocs,
                                ajustez leur largeur et leur hauteur, puis publiez la disposition lorsque le résultat vous convient.
                            </p>
                            <small>Les sections restent gérées séparément : l’éditeur ne modifie que leur disposition.</small>
                        </div>
                        <a href="/mangasan/admin/layout_editor.php" class="btn btn-primary">Ouvrir l’éditeur visuel</a>
                    </article>
                </div>

                <article class="admin-public-preview-card" id="dashboardHelpPublicPreview">
                    <div>
                        <h3>Vérifier le rendu public</h3>
                        <p>Ouvrez le site public pour contrôler immédiatement les modifications de configuration.</p>
                    </div>
                    <a href="/mangasan/public/index.php" class="btn btn-secondary">Voir le site public</a>
                </article>
            </div>
        </div>
    </section>
</main>

<?php
renderAdminHelpGuide([
    'id' => 'admin-dashboard',
    'title' => 'Guide — Tableau de bord',
    'steps' => [
        [
            'target' => '#dashboardHelpHeading',
            'title' => 'Bienvenue dans l’administration',
            'text' => 'Ce tableau de bord est le point d’entrée de la console Mangasan. Il regroupe les informations utiles et les accès vers les principales fonctions de gestion.',
            'tip' => 'Le guide peut être fermé à tout moment avec la croix ou la touche Échap.'
        ],
        [
            'target' => '#dashboardHelpTabs',
            'title' => 'Deux espaces dans le tableau de bord',
            'text' => 'L’onglet Vue d’ensemble regroupe le suivi courant de Mangasan. L’onglet Configuration du site rassemble les réglages qui modifient l’apparence et le contenu de la partie publique.',
            'tip' => 'Pendant ce guide, Mangasan changera automatiquement d’onglet lorsque l’étape suivante se trouve dans une autre section.'
        ],
        [
            'target' => '#dashboardHelpOverviewStats',
            'title' => 'Les chiffres principaux',
            'text' => 'Ces cartes donnent un aperçu rapide du nombre d’utilisateurs, de mangas, de fiches de lecture et d’éditions enregistrés dans Mangasan.',
            'tab' => 'overview'
        ],
        [
            'target' => '#dashboardHelpActiveEdition',
            'title' => 'Suivre l’édition active',
            'text' => 'Ce bloc présente l’édition actuellement active, ses dates et quelques indicateurs : mangas visibles, participants et fiches remplies. Les boutons permettent d’accéder directement aux éditions ou au classement.',
            'tab' => 'overview',
            'tip' => 'Si aucune édition n’est active, ce bloc l’indique et propose tout de même l’accès à la gestion des éditions.'
        ],
        [
            'target' => '#dashboardHelpManagement',
            'title' => 'Accéder aux outils de gestion',
            'text' => 'Ces cartes ouvrent les différentes parties de l’administration : utilisateurs, éditions, mangas, fiches de lecture et classements. Chaque page dispose ensuite de ses propres outils de gestion.',
            'tab' => 'overview'
        ],
        [
            'target' => '#dashboardHelpConfigurationHeading',
            'title' => 'Configuration du site',
            'text' => 'Cette partie concerne le site visible par les visiteurs et les élèves. Elle permet de contrôler son apparence générale et les sections affichées sur l’accueil.',
            'tab' => 'configuration'
        ],
        [
            'target' => '#dashboardHelpConfigurationSummary',
            'title' => 'État de la configuration',
            'text' => 'Ces cartes résument quelques réglages actuellement appliqués : titre du site, type de fond du bandeau, position de la zone de connexion et nombre de sections publiques visibles.',
            'tab' => 'configuration'
        ],
        [
            'target' => '#dashboardHelpConfigurationTools',
            'title' => 'Modifier l’apparence et les sections',
            'text' => 'Paramètres du site ouvre la personnalisation du logo, de la bannière, des couleurs, des textes et des effets visuels. Sections du site gère le contenu et la visibilité. Éditeur visuel permet ensuite de déplacer et redimensionner les blocs visibles directement sur le rendu public.',
            'tab' => 'configuration'
        ],
        [
            'target' => '#dashboardHelpPublicPreview',
            'title' => 'Vérifier le résultat sur le site public',
            'text' => 'Après une modification de configuration, ce bouton permet d’ouvrir rapidement la partie publique afin de vérifier le rendu réellement visible par les utilisateurs.',
            'tab' => 'configuration'
        ],
        [
            'target' => '#dashboardHelpSessionActions',
            'title' => 'Aide, retour au site et déconnexion',
            'text' => 'Le bouton Aide relance ce guide. Retour au site quitte la console sans fermer votre session. Déconnexion ferme votre session Mangasan et doit être utilisée lorsque vous avez terminé sur un poste partagé.',
            'tip' => 'La déconnexion est recommandée dès que vous quittez un ordinateur utilisé par plusieurs personnes.'
        ]
    ]
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

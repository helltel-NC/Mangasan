<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/theme.php';

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
requireAdmin();

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Administration - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
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
        'id' => $currentUserId
    ]);

    $adminRow = $stmt->fetch();

    if ($adminRow && !empty($adminRow['admin_name'])) {
        $adminDisplayName = $adminRow['admin_name'];
    }
}

$stats = [
    'users_total' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'users_active' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'admins_total' => (int)$pdo->query("
        SELECT COUNT(*)
        FROM users
        INNER JOIN roles ON roles.id = users.role_id
        WHERE roles.name = 'admin'
    ")->fetchColumn(),
    'editions_total' => (int)$pdo->query("SELECT COUNT(*) FROM editions")->fetchColumn(),
    'editions_active' => (int)$pdo->query("SELECT COUNT(*) FROM editions WHERE is_active = 1")->fetchColumn(),
    'mangas_total' => (int)$pdo->query("SELECT COUNT(*) FROM mangas")->fetchColumn(),
    'reviews_total' => (int)$pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'reviews_locked' => (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE is_locked = 1")->fetchColumn(),
    'sections_total' => (int)$pdo->query("SELECT COUNT(*) FROM site_sections")->fetchColumn(),
    'sections_visible' => (int)$pdo->query("SELECT COUNT(*) FROM site_sections WHERE is_visible = 1")->fetchColumn()
];

$activeEditionStmt = $pdo->query(
    "SELECT id, title, year, status, start_date, end_date
    FROM editions
    WHERE is_active = 1
    ORDER BY updated_at DESC
    LIMIT 1"
);
$activeEdition = $activeEditionStmt->fetch();

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
    <section class="hero">
        <div class="container hero-content">
            <div class="hero-text">
                <p class="hero-kicker">Espace administrateur</p>
                <h1 class="hero-title">Dashboard Mangasan</h1>
                <p class="hero-description">
                    Gestion globale du site, des utilisateurs, des éditions, des mangas, des avis
                    et des contenus visibles sur la partie publique.
                </p>

                <div class="hero-actions">
                    <a href="/mangasan/admin/sections.php" class="btn btn-primary">Gérer les sections</a>
                    <a href="/mangasan/admin/setting.php" class="btn btn-secondary">Paramètres du site</a>
                    <a href="/mangasan/public/index.php" class="btn btn-secondary">Retour au site public</a>
                </div>
            </div>

            <div class="hero-visual">
                    <div class="hero-placeholder admin-summary-card">
                        <p class="section-kicker">Session active</p>

                    <div class="admin-summary-list">
                        <div class="admin-summary-item">
                            <span>Connecté en tant que</span>
                            <strong><?php echo e($adminDisplayName); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Nom d’utilisateur</span>
                            <strong><?php echo e($currentUsername); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Rôle</span>
                            <strong><?php echo e($currentRole); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section" id="admin-overview">
        <div class="container">
            <h2>Vue d’ensemble</h2>

            <div class="admin-dashboard-grid">
                <article class="admin-card">
                    <h3>Utilisateurs</h3>
                    <p>Total : <?php echo (int)$stats['users_total']; ?></p>
                    <p>Actifs : <?php echo (int)$stats['users_active']; ?></p>
                    <p>Administrateurs : <?php echo (int)$stats['admins_total']; ?></p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/users.php" class="btn btn-primary">Ouvrir</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Éditions</h3>
                    <p>Total : <?php echo (int)$stats['editions_total']; ?></p>
                    <p>Éditions actives : <?php echo (int)$stats['editions_active']; ?></p>

                    <?php if ($activeEdition): ?>
                        <p>Édition active : <?php echo e($activeEdition['title']); ?></p>
                        <p>Année : <?php echo e((string)$activeEdition['year']); ?></p>
                        <p>Statut : <?php echo e($activeEdition['status']); ?></p>
                    <?php else: ?>
                        <p>Aucune édition active actuellement.</p>
                    <?php endif; ?>

                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/editions.php" class="btn btn-primary">Ouvrir</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Mangas</h3>
                    <p>Total : <?php echo (int)$stats['mangas_total']; ?></p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/mangas.php" class="btn btn-primary">Ouvrir</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Fiche de lecture</h3>
                    <p>Total : <?php echo (int)$stats['reviews_total']; ?></p>
                    <p>Verrouillées : <?php echo (int)$stats['reviews_locked']; ?></p>
                      <div class="admin-card-actions">
                        <a href="/mangasan/admin/reviews.php" class="btn btn-primary">Ouvrir</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Sections du site</h3>
                    <p>Total : <?php echo (int)$stats['sections_total']; ?></p>
                    <p>Visibles : <?php echo (int)$stats['sections_visible']; ?></p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/sections.php" class="btn btn-primary">Ouvrir</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Paramètres du site</h3>

                    <?php if ($siteSettings): ?>
                        <p>Titre : <?php echo e($siteSettings['site_title']); ?></p>
                        <p>Fond du hero : <?php echo e($siteSettings['hero_background_type']); ?></p>
                        <p>Position du login : <?php echo e($siteSettings['hero_login_position']); ?></p>
                        <p>Dernière mise à jour : <?php echo e($siteSettings['updated_at']); ?></p>
                    <?php else: ?>
                        <p>Aucun paramètre global trouvé.</p>
                    <?php endif; ?>

                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/setting.php" class="btn btn-primary">Ouvrir</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section" id="admin-quick-info">
        <div class="container">
            <h2>Raccourcis de gestion</h2>

            <div class="admin-dashboard-grid">
                <article class="admin-card">
                    <h3>Site public</h3>
                    <p>Accéder à la page d’accueil publique sans se déconnecter.</p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/public/index.php" class="btn btn-secondary">Retour au site</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Utilisateurs</h3>
                    <p>Création, modification, suppression et suivi des comptes.</p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/users.php" class="btn btn-primary">Gérer</a>
                        <a href="/mangasan/admin/user_edit.php" class="btn btn-secondary">Créer</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Éditions</h3>
                    <p>Gestion des sessions Mangasan et de leur configuration.</p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/editions.php" class="btn btn-primary">Gérer</a>
                        <a href="/mangasan/admin/edition_edit.php" class="btn btn-secondary">Créer</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Mangas</h3>
                    <p>Ajout, modification, suppression et rattachement aux éditions.</p>
                   <div class="admin-card-actions">
                        <a href="/mangasan/admin/mangas.php" class="btn btn-primary">Gérer</a>
                        <a href="/mangasan/admin/manga_edit.php" class="btn btn-secondary">Créer</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Fiche de lecture</h3>
                    <p>Consultation, modération, verrouillage et suppression des avis.</p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/reviews.php" class="btn btn-primary">Gérer</a>
                    </div>
                </article>

                <article class="admin-card">
                    <h3>Apparence</h3>
                    <p>Bannière, couleurs, textes et contenus généraux du site.</p>
                    <div class="admin-card-actions">
                        <a href="/mangasan/admin/setting.php" class="btn btn-primary">Configurer</a>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
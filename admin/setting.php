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

function colorOrDefault(?string $value, string $default): string
{
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
}

$stmt = $pdo->query('SELECT * FROM site_settings ORDER BY id ASC LIMIT 1');
$settings = $stmt->fetch();

if (!$settings) {
    setFlashMessage('error', 'Aucun enregistrement de paramètres du site n’a été trouvé.');
    header('Location: /mangasan/admin/index.php');
    exit;
}

$pageTitle = 'Paramètres du site - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];
$extraJs = [
    '/mangasan/public/assets/js/admin-preview.js'
];

$flashMessages = getFlashMessages();

$primaryColor = colorOrDefault($settings['primary_color'] ?? null, '#e50914');
$secondaryColor = colorOrDefault($settings['secondary_color'] ?? null, '#333333');
$backgroundColor = colorOrDefault($settings['background_color'] ?? null, '#0f0f0f');
$textColor = colorOrDefault($settings['text_color'] ?? null, '#ffffff');
$accentColor = colorOrDefault($settings['accent_color'] ?? null, '#c1121f');
$heroTextColor = colorOrDefault($settings['hero_text_color'] ?? null, '#ffffff');

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Paramètres du site</h1>
                    <p>Configure les couleurs, les médias globaux et la présentation de la page d’accueil.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/sections.php" class="btn btn-primary">Gérer les sections</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-layout-two-columns">
                <div class="admin-panel">
                    <form method="post" action="/mangasan/actions/settings_update.php" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="settings_id" value="<?php echo (int) $settings['id']; ?>">

                        <div class="admin-form-section">
                            <h2>Identité du site</h2>

                            <div class="admin-field">
                                <label for="site_title">Titre du site</label>
                                <input type="text" id="site_title" name="site_title" value="<?php echo e($settings['site_title']); ?>" required>
                            </div>

                            <div class="admin-field">
                                <label for="site_title_type">Type d’affichage du titre</label>
                                <select id="site_title_type" name="site_title_type">
                                    <option value="text" <?php echo $settings['site_title_type'] === 'text' ? 'selected' : ''; ?>>Texte</option>
                                    <option value="image" <?php echo $settings['site_title_type'] === 'image' ? 'selected' : ''; ?>>Logo seul</option>
                                    <option value="text_image" <?php echo $settings['site_title_type'] === 'text_image' ? 'selected' : ''; ?>>Logo + texte</option>
                                    <option value="none" <?php echo $settings['site_title_type'] === 'none' ? 'selected' : ''; ?>>Rien afficher</option>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="logo_path">Chemin / URL du logo</label>
                                <input type="text" id="logo_path" name="logo_path" value="<?php echo e($settings['logo_path']); ?>">
                            </div>

                            <div class="admin-field">
                                <label for="logo_file">Upload logo</label>
                                <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h2>Couleurs</h2>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="primary_color">Couleur principale</label>
                                    <input type="color" id="primary_color" name="primary_color" value="<?php echo e($primaryColor); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="secondary_color">Couleur secondaire</label>
                                    <input type="color" id="secondary_color" name="secondary_color" value="<?php echo e($secondaryColor); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="background_color">Couleur de fond</label>
                                    <input type="color" id="background_color" name="background_color" value="<?php echo e($backgroundColor); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="text_color">Couleur du texte</label>
                                    <input type="color" id="text_color" name="text_color" value="<?php echo e($textColor); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="accent_color">Couleur d’accent</label>
                                    <input type="color" id="accent_color" name="accent_color" value="<?php echo e($accentColor); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="hero_text_color">Couleur du texte hero</label>
                                    <input type="color" id="hero_text_color" name="hero_text_color" value="<?php echo e($heroTextColor); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h2>Hero / accueil</h2>

                            <div class="admin-field">
                                <label for="hero_background_type">Type de fond hero</label>
                                <select id="hero_background_type" name="hero_background_type">
                                    <option value="color" <?php echo $settings['hero_background_type'] === 'color' ? 'selected' : ''; ?>>Couleur</option>
                                    <option value="image" <?php echo $settings['hero_background_type'] === 'image' ? 'selected' : ''; ?>>Image</option>
                                    <option value="video" <?php echo $settings['hero_background_type'] === 'video' ? 'selected' : ''; ?>>Vidéo</option>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="hero_background_value">Chemin / URL du fond hero</label>
                                <input type="text" id="hero_background_value" name="hero_background_value" value="<?php echo e($settings['hero_background_value']); ?>">
                            </div>

                            <div class="admin-field">
                                <label for="hero_background_file">Upload image hero</label>
                                <input type="file" id="hero_background_file" name="hero_background_file" accept=".jpg,.jpeg,.png,.webp">
                            </div>

                            <div class="admin-field">
                                <label for="homepage_intro">Texte d’introduction</label>
                                <textarea id="homepage_intro" name="homepage_intro"><?php echo e($settings['homepage_intro']); ?></textarea>
                            </div>

                            <div class="setting-checkbox-row">
                                <label class="setting-checkbox">
                                    <input
                                        type="checkbox"
                                        name="hide_hero_text"
                                        value="1"
                                        <?php echo !empty($settings['hide_hero_text']) ? 'checked' : ''; ?>
                                    >
                                    <span>Masquer le titre et la description du hero</span>
                                </label>

                                <p class="setting-help">
                                    À utiliser quand l’image ou la vidéo du hero contient déjà le logo / titre.
                                </p>
                            </div>

                            <div class="admin-field">
                                <label for="hero_login_position">Position du formulaire de connexion</label>
                                <select id="hero_login_position" name="hero_login_position">
                                    <option value="left" <?php echo $settings['hero_login_position'] === 'left' ? 'selected' : ''; ?>>Gauche</option>
                                    <option value="right" <?php echo $settings['hero_login_position'] === 'right' ? 'selected' : ''; ?>>Droite</option>
                                </select>
                            </div>
                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                            <a href="/mangasan/admin/index.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>

                <aside class="admin-preview-card" id="settingsPreview">
                    <div class="admin-preview-head">
                        <h2>Aperçu du site</h2>
                    </div>

                    <div class="admin-site-preview-shell" id="sitePreviewRoot">
                        <div class="admin-site-preview-header" id="previewSiteHeader">
                        <div class="admin-site-preview-logo">
                            <?php if ($settings['site_title_type'] === 'text'): ?>
                                <span id="previewSiteTitleText"><?php echo e($settings['site_title']); ?></span>

                            <?php elseif ($settings['site_title_type'] === 'image'): ?>
                                <?php if (!empty($settings['logo_path'])): ?>
                                    <img
                                        id="previewSiteLogoImage"
                                        src="<?php echo e($settings['logo_path']); ?>"
                                        alt="<?php echo e($settings['site_title']); ?>"
                                    >
                                <?php endif; ?>

                            <?php elseif ($settings['site_title_type'] === 'text_image'): ?>
                                <?php if (!empty($settings['logo_path'])): ?>
                                    <img
                                        id="previewSiteLogoImage"
                                        src="<?php echo e($settings['logo_path']); ?>"
                                        alt="<?php echo e($settings['site_title']); ?>"
                                    >
                                <?php endif; ?>
                                <span id="previewSiteTitleText"><?php echo e($settings['site_title']); ?></span>

                            <?php elseif ($settings['site_title_type'] === 'none'): ?>
                                <span id="previewSiteTitleText" class="is-hidden"></span>
                            <?php endif; ?>
                        </div>
                        </div>

                        <div class="admin-site-preview-hero" id="previewHero">
                            <div class="admin-site-preview-hero-inner <?php echo $settings['hero_login_position'] === 'left' ? 'is-login-left' : ''; ?>" id="previewHeroInner">
                                <div class="admin-site-preview-copy<?php echo !empty($settings['hide_hero_text']) ? ' is-hero-text-hidden' : ''; ?>">
                                    <p class="admin-site-preview-kicker">Mangasan</p>
                                    <?php if (empty($settings['hide_hero_text'])): ?>
                                        <h3 id="previewHeroTitle"><?php echo e($settings['site_title']); ?></h3>
                                        <p id="previewHeroIntro"><?php echo e($settings['homepage_intro']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="admin-site-preview-login" id="previewLoginBox">
                                    <div class="admin-site-preview-input"></div>
                                    <div class="admin-site-preview-input"></div>
                                    <div class="admin-site-preview-button">Connexion</div>
                                </div>
                            </div>

                            <div class="admin-site-preview-media-label" id="previewHeroMediaLabel"></div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
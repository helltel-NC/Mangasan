<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/help.php';

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
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/admin-preview.js',
    '/mangasan/public/assets/js/help-system.js',
];

$flashMessages = getFlashMessages();

$primaryColor = colorOrDefault($settings['primary_color'] ?? null, '#e50914');
$secondaryColor = colorOrDefault($settings['secondary_color'] ?? null, '#333333');
$backgroundColor = colorOrDefault($settings['background_color'] ?? null, '#0f0f0f');
$textColor = colorOrDefault($settings['text_color'] ?? null, '#ffffff');
$accentColor = colorOrDefault($settings['accent_color'] ?? null, '#c1121f');
$heroTextColor = colorOrDefault($settings['hero_text_color'] ?? null, '#ffffff');
$visualEffectType = (string) ($settings['visual_effect_type'] ?? 'none');

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="settingsHelpHeading">
                <div class="admin-page-heading">
                    <h1>Paramètres du site</h1>
                    <p>Personnalise l’identité visuelle et la page d’accueil de Mangasan.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/sections.php" class="btn btn-primary">Gérer les sections</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-layout-two-columns admin-settings-edit-layout">
                <div class="admin-panel admin-settings-form-panel">
                    <form method="post" action="/mangasan/actions/settings_update.php" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="settings_id" value="<?php echo (int) $settings['id']; ?>">

                        <section class="admin-settings-form-section" id="settingsHelpIdentity">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">1</span>
                                    <h2>Identité du site</h2>
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="site_title">Titre du site</label>
                                    <input type="text" id="site_title" name="site_title" value="<?php echo e((string) $settings['site_title']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="site_title_type">Affichage du titre</label>
                                    <select id="site_title_type" name="site_title_type">
                                        <option value="text" <?php echo $settings['site_title_type'] === 'text' ? 'selected' : ''; ?>>Texte</option>
                                        <option value="image" <?php echo $settings['site_title_type'] === 'image' ? 'selected' : ''; ?>>Logo seul</option>
                                        <option value="text_image" <?php echo $settings['site_title_type'] === 'text_image' ? 'selected' : ''; ?>>Logo + texte</option>
                                        <option value="none" <?php echo $settings['site_title_type'] === 'none' ? 'selected' : ''; ?>>Rien afficher</option>
                                    </select>
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="logo_path">Chemin / URL du logo</label>
                                    <input type="text" id="logo_path" name="logo_path" value="<?php echo e((string) ($settings['logo_path'] ?? '')); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="logo_file">Importer un logo</label>
                                    <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
                                </div>
                            </div>
                        </section>

                        <section class="admin-settings-form-section" id="settingsHelpColors">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">2</span>
                                    <h2>Couleurs</h2>
                                </div>
                            </div>

                            <div class="admin-settings-colors-grid">
                                <div class="admin-field admin-color-field">
                                    <label for="primary_color">Principale</label>
                                    <input type="color" id="primary_color" name="primary_color" value="<?php echo e($primaryColor); ?>">
                                </div>

                                <div class="admin-field admin-color-field">
                                    <label for="secondary_color">Secondaire</label>
                                    <input type="color" id="secondary_color" name="secondary_color" value="<?php echo e($secondaryColor); ?>">
                                </div>

                                <div class="admin-field admin-color-field">
                                    <label for="background_color">Fond</label>
                                    <input type="color" id="background_color" name="background_color" value="<?php echo e($backgroundColor); ?>">
                                </div>

                                <div class="admin-field admin-color-field">
                                    <label for="text_color">Texte</label>
                                    <input type="color" id="text_color" name="text_color" value="<?php echo e($textColor); ?>">
                                </div>

                                <div class="admin-field admin-color-field">
                                    <label for="accent_color">Accent</label>
                                    <input type="color" id="accent_color" name="accent_color" value="<?php echo e($accentColor); ?>">
                                </div>

                                <div class="admin-field admin-color-field">
                                    <label for="hero_text_color">Texte du bandeau</label>
                                    <input type="color" id="hero_text_color" name="hero_text_color" value="<?php echo e($heroTextColor); ?>">
                                </div>
                            </div>
                        </section>

                        <section class="admin-settings-form-section" id="settingsHelpHero">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">3</span>
                                    <h2>Bandeau d’accueil</h2>
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="hero_background_type">Type de fond</label>
                                    <select id="hero_background_type" name="hero_background_type">
                                        <option value="color" <?php echo $settings['hero_background_type'] === 'color' ? 'selected' : ''; ?>>Couleur</option>
                                        <option value="image" <?php echo $settings['hero_background_type'] === 'image' ? 'selected' : ''; ?>>Image</option>
                                        <option value="video" <?php echo $settings['hero_background_type'] === 'video' ? 'selected' : ''; ?>>Vidéo</option>
                                    </select>
                                </div>

                                <div class="admin-field">
                                    <label for="hero_login_position">Position de la connexion</label>
                                    <select id="hero_login_position" name="hero_login_position">
                                        <option value="left" <?php echo $settings['hero_login_position'] === 'left' ? 'selected' : ''; ?>>Gauche</option>
                                        <option value="right" <?php echo $settings['hero_login_position'] === 'right' ? 'selected' : ''; ?>>Droite</option>
                                    </select>
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="hero_background_value">Chemin / URL du fond</label>
                                    <input type="text" id="hero_background_value" name="hero_background_value" value="<?php echo e((string) ($settings['hero_background_value'] ?? '')); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="hero_background_file">Importer une image</label>
                                    <input type="file" id="hero_background_file" name="hero_background_file" accept=".jpg,.jpeg,.png,.webp">
                                </div>
                            </div>

                            <div class="admin-field">
                                <label for="homepage_intro">Texte d’introduction</label>
                                <textarea id="homepage_intro" name="homepage_intro"><?php echo e((string) ($settings['homepage_intro'] ?? '')); ?></textarea>
                            </div>

                            <label class="setting-checkbox admin-settings-hero-toggle" for="hide_hero_text">
                                <input
                                    type="checkbox"
                                    id="hide_hero_text"
                                    name="hide_hero_text"
                                    value="1"
                                    <?php echo !empty($settings['hide_hero_text']) ? 'checked' : ''; ?>
                                >
                                <span>Masquer le titre et la description du bandeau</span>
                            </label>
                        </section>

                        <section class="admin-settings-form-section" id="settingsHelpEffects">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">4</span>
                                    <h2>Effet visuel</h2>
                                </div>
                            </div>

                            <div class="admin-field">
                                <label for="visual_effect_type">Effet appliqué au site</label>
                                <select id="visual_effect_type" name="visual_effect_type">
                                    <option value="none" <?php echo $visualEffectType === 'none' ? 'selected' : ''; ?>>Aucun</option>
                                    <option value="sparkles" <?php echo $visualEffectType === 'sparkles' ? 'selected' : ''; ?>>Paillettes discrètes</option>
                                    <option value="aurora" <?php echo $visualEffectType === 'aurora' ? 'selected' : ''; ?>>Faisceaux / aurore</option>
                                    <option value="speed_lines" <?php echo $visualEffectType === 'speed_lines' ? 'selected' : ''; ?>>Lignes manga</option>
                                    <option value="manga_dots" <?php echo $visualEffectType === 'manga_dots' ? 'selected' : ''; ?>>Trame manga</option>
                                    <option value="music_notes" <?php echo $visualEffectType === 'music_notes' ? 'selected' : ''; ?>>Notes de musique</option>
                                </select>
                            </div>
                        </section>

                        <div class="admin-settings-form-actions" id="settingsHelpSave">
                            <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
                            <a href="/mangasan/admin/index.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>

                <aside class="admin-preview-card admin-settings-preview-card" id="settingsPreview">
                    <div class="admin-preview-head">
                        <div>
                            <h2>Aperçu du site</h2>
                            <span class="admin-settings-preview-state" id="previewEffectLabel">Effet : aucun</span>
                        </div>
                    </div>

                    <div class="admin-site-preview-shell" id="sitePreviewRoot">
                        <div class="admin-site-preview-header" id="previewSiteHeader">
                            <div class="admin-site-preview-logo">
                                <img
                                    id="previewSiteLogoImage"
                                    src="<?php echo e((string) ($settings['logo_path'] ?? '')); ?>"
                                    alt="<?php echo e((string) $settings['site_title']); ?>"
                                    class="<?php echo in_array($settings['site_title_type'], ['image', 'text_image'], true) && !empty($settings['logo_path']) ? '' : 'is-hidden'; ?>"
                                >
                                <span
                                    id="previewSiteTitleText"
                                    class="<?php echo in_array($settings['site_title_type'], ['text', 'text_image'], true) ? '' : 'is-hidden'; ?>"
                                ><?php echo e((string) $settings['site_title']); ?></span>
                            </div>
                        </div>

                        <div class="admin-site-preview-hero" id="previewHero">
                            <div class="admin-site-preview-effect-layer" id="previewEffectLayer" aria-hidden="true"></div>

                            <div class="admin-site-preview-hero-inner <?php echo $settings['hero_login_position'] === 'left' ? 'is-login-left' : ''; ?>" id="previewHeroInner">
                                <div class="admin-site-preview-copy <?php echo !empty($settings['hide_hero_text']) ? 'is-hidden' : ''; ?>" id="previewHeroCopy">
                                    <p class="admin-site-preview-kicker">Mangasan</p>
                                    <h3 id="previewHeroTitle"><?php echo e((string) $settings['site_title']); ?></h3>
                                    <p id="previewHeroIntro"><?php echo e((string) ($settings['homepage_intro'] ?? '')); ?></p>
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

                    <p class="admin-settings-preview-note">L’aperçu se met à jour pendant la saisie. Les changements ne sont appliqués au site qu’après enregistrement.</p>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php
renderAdminHelpGuide([
    'id' => 'admin-settings',
    'title' => 'Guide — Paramètres du site',
    'steps' => [
        [
            'target' => '#settingsHelpHeading',
            'title' => 'Paramètres du site',
            'text' => 'Cette page contrôle l’identité visuelle et le bandeau d’accueil de Mangasan. Les réglages sont regroupés par thème pour éviter de modifier un élément par erreur.'
        ],
        [
            'target' => '#settingsHelpIdentity',
            'title' => 'Identité du site',
            'text' => 'Choisissez le titre affiché dans l’en-tête et la façon de l’afficher : texte, logo, les deux ou aucun. Le logo peut être indiqué par un chemin ou importé directement.'
        ],
        [
            'target' => '#settingsHelpColors',
            'title' => 'Couleurs du thème',
            'text' => 'Ces couleurs définissent l’apparence générale du site : boutons, arrière-plan, textes, accents et texte du bandeau. L’aperçu à droite permet de vérifier immédiatement le résultat.'
        ],
        [
            'target' => '#settingsHelpHero',
            'title' => 'Bandeau d’accueil',
            'text' => 'Configurez ici le fond du bandeau, son texte et la position du formulaire de connexion. Le fond peut être une couleur, une image ou une vidéo.',
            'tip' => 'Si le titre est déjà intégré dans l’image du bandeau, cochez l’option pour masquer le titre et la description ajoutés par le site.'
        ],
        [
            'target' => '#settingsHelpEffects',
            'title' => 'Effet visuel',
            'text' => 'Sélectionnez l’ambiance graphique appliquée aux pages publiques. Les effets utilisent automatiquement les couleurs du thème choisi.'
        ],
        [
            'target' => '#settingsPreview',
            'title' => 'Aperçu en direct',
            'text' => 'Ce panneau donne une prévisualisation du titre, des couleurs, du bandeau et de la connexion pendant la saisie. Il ne modifie pas encore le site.'
        ],
        [
            'target' => '#settingsHelpSave',
            'title' => 'Enregistrer',
            'text' => 'Enregistrer applique les paramètres au site. Annuler revient au tableau de bord sans enregistrer les changements en cours.'
        ],
    ],
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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

$sectionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$sectionId) {
    setFlashMessage('error', 'Section invalide.');
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM site_sections WHERE id = :id LIMIT 1');
$stmt->execute([
    'id' => $sectionId,
]);

$section = $stmt->fetch();

if (!$section) {
    setFlashMessage('error', 'Section introuvable.');
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$pageTitle = 'Modifier une section - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/admin-preview.js',
    '/mangasan/public/assets/js/admin-section-edit.js',
    '/mangasan/public/assets/js/help-system.js',
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$helpGuide = [
    'id' => 'admin-section-edit',
    'title' => 'Guide — Modifier une section',
    'steps' => [
        [
            'target' => '#sectionEditHelpHeading',
            'title' => 'Modifier une section',
            'text' => 'Cette page modifie un bloc du site public. L’aperçu de droite permet de vérifier le résultat avant l’enregistrement.',
        ],
        [
            'target' => '#sectionEditHelpIdentity',
            'title' => 'Identification technique',
            'text' => 'La clé et le type identifient la section dans le code. Ils sont affichés pour information et ne peuvent pas être modifiés ici.',
        ],
        [
            'target' => '#sectionEditHelpContent',
            'title' => 'Contenu',
            'text' => 'Modifie ici le titre, le sous-titre et le texte principal affichés dans cette section.',
        ],
        [
            'target' => '#sectionEditHelpMedia',
            'title' => 'Média',
            'text' => 'Choisis Aucun, Image ou Vidéo. Pour une image, tu peux utiliser un chemin existant ou envoyer un nouveau fichier. Pour une vidéo, indique son chemin ou son URL.',
        ],
        [
            'target' => '#sectionEditHelpDisplay',
            'title' => 'Affichage',
            'text' => 'L’ordre détermine la position de la section sur le site. La visibilité permet de l’afficher ou de la masquer sans supprimer son contenu.',
        ],
        [
            'target' => '#sectionPreview',
            'title' => 'Aperçu',
            'text' => 'L’aperçu se met à jour pendant la saisie. Il sert à contrôler le contenu et le média avant d’enregistrer.',
        ],
        [
            'target' => '#sectionEditHelpActions',
            'title' => 'Enregistrer',
            'text' => 'Enregistrer applique les modifications. Annuler revient à la liste sans enregistrer les changements en cours.',
        ],
    ],
];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-section-edit-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="sectionEditHelpHeading">
                <div class="admin-page-heading">
                    <h1>Modifier la section</h1>
                    <p><?php echo e((string) $section['title']); ?></p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/sections.php" class="btn btn-secondary">Retour sections</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-section-edit-layout">
                <form method="post" action="/mangasan/actions/section_update.php" enctype="multipart/form-data" class="admin-section-edit-form">
                    <input type="hidden" name="section_id" value="<?php echo (int) $section['id']; ?>">

                    <section class="admin-form-section admin-section-form-section" id="sectionEditHelpIdentity">
                        <div class="admin-form-section-heading">
                            <div>
                                <span class="admin-form-section-kicker">1</span>
                                <h2>Identification</h2>
                            </div>
                        </div>

                        <div class="admin-form-grid">
                            <div class="admin-field">
                                <label for="section_key">Clé technique</label>
                                <input type="text" id="section_key" value="<?php echo e((string) $section['section_key']); ?>" readonly class="admin-readonly">
                            </div>

                            <div class="admin-field">
                                <label for="section_type">Type de section</label>
                                <input type="text" id="section_type" value="<?php echo e((string) $section['section_type']); ?>" readonly class="admin-readonly">
                            </div>
                        </div>
                    </section>

                    <section class="admin-form-section admin-section-form-section" id="sectionEditHelpContent">
                        <div class="admin-form-section-heading">
                            <div>
                                <span class="admin-form-section-kicker">2</span>
                                <h2>Contenu</h2>
                            </div>
                        </div>

                        <div class="admin-field">
                            <label for="title">Titre</label>
                            <input type="text" id="title" name="title" value="<?php echo e((string) $section['title']); ?>" required>
                        </div>

                        <div class="admin-field">
                            <label for="subtilte">Sous-titre</label>
                            <input type="text" id="subtilte" name="subtilte" value="<?php echo e((string) $section['subtilte']); ?>">
                        </div>

                        <div class="admin-field">
                            <label for="content">Contenu</label>
                            <textarea id="content" name="content" rows="8"><?php echo e((string) $section['content']); ?></textarea>
                        </div>
                    </section>

                    <section class="admin-form-section admin-section-form-section" id="sectionEditHelpMedia">
                        <div class="admin-form-section-heading">
                            <div>
                                <span class="admin-form-section-kicker">3</span>
                                <h2>Média</h2>
                            </div>
                        </div>

                        <div class="admin-field">
                            <label for="media_type">Type de média</label>
                            <select id="media_type" name="media_type">
                                <option value="none" <?php echo $section['media_type'] === 'none' ? 'selected' : ''; ?>>Aucun</option>
                                <option value="image" <?php echo $section['media_type'] === 'image' ? 'selected' : ''; ?>>Image</option>
                                <option value="video" <?php echo $section['media_type'] === 'video' ? 'selected' : ''; ?>>Vidéo</option>
                            </select>
                        </div>

                        <div class="admin-section-media-fields" id="sectionMediaFields">
                            <div class="admin-field" id="sectionMediaValueField">
                                <label for="media_value" id="sectionMediaValueLabel">Chemin / URL du média</label>
                                <input type="text" id="media_value" name="media_value" value="<?php echo e((string) $section['media_value']); ?>">
                            </div>

                            <div class="admin-field" id="sectionMediaUploadField">
                                <label for="media_file">Nouvelle image</label>
                                <input type="file" id="media_file" name="media_file" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>
                    </section>

                    <section class="admin-form-section admin-section-form-section" id="sectionEditHelpDisplay">
                        <div class="admin-form-section-heading">
                            <div>
                                <span class="admin-form-section-kicker">4</span>
                                <h2>Affichage</h2>
                            </div>
                        </div>

                        <div class="admin-form-grid">
                            <div class="admin-field">
                                <label for="display_order">Ordre d’affichage</label>
                                <input type="number" id="display_order" name="display_order" min="0" value="<?php echo (int) $section['display_order']; ?>" required>
                            </div>

                            <div class="admin-field">
                                <label for="is_visible">Visibilité</label>
                                <select id="is_visible" name="is_visible">
                                    <option value="1" <?php echo (int) $section['is_visible'] === 1 ? 'selected' : ''; ?>>Visible</option>
                                    <option value="0" <?php echo (int) $section['is_visible'] === 0 ? 'selected' : ''; ?>>Masquée</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <div class="admin-form-actions admin-section-form-actions" id="sectionEditHelpActions">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <a href="/mangasan/admin/sections.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>

                <aside class="admin-preview-card admin-section-preview-card" id="sectionPreview">
                    <div class="admin-preview-head">
                        <div>
                            <span class="admin-section-preview-label">Aperçu</span>
                            <h2><?php echo e((string) $section['section_key']); ?></h2>
                        </div>
                        <span id="sectionPreviewVisibility" class="admin-badge <?php echo (int) $section['is_visible'] === 1 ? 'is-visible' : 'is-hidden'; ?>">
                            <?php echo (int) $section['is_visible'] === 1 ? 'Visible' : 'Masquée'; ?>
                        </span>
                    </div>

                    <div class="admin-preview-section">
                        <h3 id="sectionPreviewTitle"><?php echo e((string) $section['title']); ?></h3>

                        <p id="sectionPreviewSubtitle" class="admin-preview-subtitle">
                            <?php echo e((string) $section['subtilte']); ?>
                        </p>

                        <div id="sectionPreviewMedia" class="admin-preview-media <?php echo $section['media_type'] === 'none' ? 'is-hidden' : ''; ?>">
                            <img
                                id="sectionPreviewImage"
                                src="<?php echo $section['media_type'] === 'image' && !empty($section['media_value']) ? e((string) $section['media_value']) : ''; ?>"
                                alt=""
                                class="<?php echo $section['media_type'] === 'image' ? '' : 'is-hidden'; ?>"
                            >
                            <div
                                id="sectionPreviewVideo"
                                class="admin-preview-video <?php echo $section['media_type'] === 'video' ? '' : 'is-hidden'; ?>"
                            >
                                <?php echo $section['media_type'] === 'video' ? e((string) $section['media_value']) : 'Aucune vidéo'; ?>
                            </div>
                        </div>

                        <p id="sectionPreviewContent" class="admin-preview-text"><?php echo e((string) $section['content']); ?></p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php renderAdminHelpGuide($helpGuide); ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

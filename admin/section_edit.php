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

$sectionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$sectionId) {
    setFlashMessage('error', 'Section invalide.');
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM site_sections WHERE id = :id LIMIT 1');
$stmt->execute([
    'id' => $sectionId
]);

$section = $stmt->fetch();

if (!$section) {
    setFlashMessage('error', 'Section introuvable.');
    header('Location: /mangasan/admin/sections.php');
    exit;
}

$pageTitle = 'Modifier une section - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];
$extraJs = [
    '/mangasan/public/assets/js/admin-preview.js'
];

$flashMessages = getFlashMessages();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Modifier la section</h1>
                    <p>Travaille sur le contenu public sans toucher à la structure du site.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/sections.php" class="btn btn-secondary">Retour sections</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-layout-two-columns">
                <div class="admin-panel">
                    <form method="post" action="/mangasan/actions/section_update.php" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="section_id" value="<?php echo (int) $section['id']; ?>">

                        <div class="admin-form-grid">
                            <div class="admin-field">
                                <label for="section_key">Clé technique</label>
                                <input type="text" id="section_key" value="<?php echo e($section['section_key']); ?>" readonly class="admin-readonly">
                            </div>

                            <div class="admin-field">
                                <label for="section_type">Type de section</label>
                                <input type="text" id="section_type" value="<?php echo e($section['section_type']); ?>" readonly class="admin-readonly">
                            </div>
                        </div>

                        <div class="admin-field">
                            <label for="title">Titre</label>
                            <input type="text" id="title" name="title" value="<?php echo e($section['title']); ?>" required>
                        </div>

                        <div class="admin-field">
                            <label for="subtilte">Sous-titre</label>
                            <input type="text" id="subtilte" name="subtilte" value="<?php echo e($section['subtilte']); ?>">
                        </div>

                        <div class="admin-field">
                            <label for="content">Contenu</label>
                            <textarea id="content" name="content"><?php echo e($section['content']); ?></textarea>
                        </div>

                        <div class="admin-form-grid">
                            <div class="admin-field">
                                <label for="media_type">Type de média</label>
                                <select id="media_type" name="media_type">
                                    <option value="none" <?php echo $section['media_type'] === 'none' ? 'selected' : ''; ?>>Aucun</option>
                                    <option value="image" <?php echo $section['media_type'] === 'image' ? 'selected' : ''; ?>>Image</option>
                                    <option value="video" <?php echo $section['media_type'] === 'video' ? 'selected' : ''; ?>>Vidéo</option>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="display_order">Ordre d’affichage</label>
                                <input type="number" id="display_order" name="display_order" min="0" value="<?php echo (int) $section['display_order']; ?>" required>
                            </div>
                        </div>

                        <div class="admin-field">
                            <label for="media_value">Chemin / URL du média</label>
                            <input type="text" id="media_value" name="media_value" value="<?php echo e($section['media_value']); ?>">
                        </div>

                        <div class="admin-field">
                            <label for="media_file">Upload image</label>
                            <input type="file" id="media_file" name="media_file" accept=".jpg,.jpeg,.png,.webp">
                            <p class="admin-form-help">Le fichier sera converti en WEBP si la conversion est disponible.</p>
                        </div>

                        <div class="admin-field">
                            <label for="is_visible">Visibilité</label>
                            <select id="is_visible" name="is_visible">
                                <option value="1" <?php echo (int) $section['is_visible'] === 1 ? 'selected' : ''; ?>>Visible</option>
                                <option value="0" <?php echo (int) $section['is_visible'] === 0 ? 'selected' : ''; ?>>Masquée</option>
                            </select>
                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                            <a href="/mangasan/admin/sections.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>

                <aside class="admin-preview-card" id="sectionPreview">
                    <div class="admin-preview-head">
                        <h2>Aperçu</h2>
                        <span id="sectionPreviewVisibility" class="admin-badge <?php echo (int) $section['is_visible'] === 1 ? 'is-visible' : 'is-hidden'; ?>">
                            <?php echo (int) $section['is_visible'] === 1 ? 'Visible' : 'Masquée'; ?>
                        </span>
                    </div>

                    <div class="admin-preview-section">
                        <p class="admin-preview-kicker"><?php echo e($section['section_key']); ?></p>
                        <h3 id="sectionPreviewTitle"><?php echo e($section['title']); ?></h3>

                        <p id="sectionPreviewSubtitle" class="admin-preview-subtitle">
                            <?php echo e($section['subtilte']); ?>
                        </p>

                        <div id="sectionPreviewMedia" class="admin-preview-media <?php echo $section['media_type'] === 'none' ? 'is-hidden' : ''; ?>">
                            <img
                                id="sectionPreviewImage"
                                src="<?php echo $section['media_type'] === 'image' && !empty($section['media_value']) ? e($section['media_value']) : ''; ?>"
                                alt=""
                                class="<?php echo $section['media_type'] === 'image' ? '' : 'is-hidden'; ?>"
                            >
                            <div
                                id="sectionPreviewVideo"
                                class="admin-preview-video <?php echo $section['media_type'] === 'video' ? '' : 'is-hidden'; ?>"
                            >
                                <?php echo $section['media_type'] === 'video' ? e($section['media_value']) : 'Aucune vidéo'; ?>
                            </div>
                        </div>

                        <p id="sectionPreviewContent" class="admin-preview-text"><?php echo e($section['content']); ?></p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
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

function mangaStatusLabel(string $status): string
{
    return match ($status) {
        'active' => 'Actif',
        'inactive' => 'Non actif',
        default => $status
    };
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

$mangaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditMode = $mangaId !== false && $mangaId !== null;

$manga = [
    'id' => null,
    'title' => '',
    'subtitle' => '',
    'author' => '',
    'illustrator' => '',
    'publisher' => '',
    'summary' => '',
    'card_image' => '',
    'cover_image' => '',
    'video_url' => '',
    'status' => 'active',
    'created_at' => '',
    'updated_at' => ''
];

$stats = [
    'editions_count' => 0,
    'reviews_count' => 0
];

$attachments = [];
$availableEditions = [];

if ($isEditMode) {
    $stmt = $pdo->prepare(
        "SELECT
            mangas.*,
            (
                SELECT COUNT(*)
                FROM edition_mangas
                WHERE edition_mangas.manga_id = mangas.id
            ) AS editions_count,
            (
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.manga_id = mangas.id
            ) AS reviews_count
         FROM mangas
         WHERE mangas.id = :id
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $mangaId
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        setFlashMessage('error', 'Manga introuvable.');
        header('Location: /mangasan/admin/mangas.php');
        exit;
    }

    $manga = $row;
    $stats['editions_count'] = (int) $row['editions_count'];
    $stats['reviews_count'] = (int) $row['reviews_count'];

    $attachmentsStmt = $pdo->prepare(
        "SELECT
            edition_mangas.id AS relation_id,
            edition_mangas.display_order,
            edition_mangas.is_visible,
            editions.id AS edition_id,
            editions.title AS edition_title,
            editions.year AS edition_year,
            editions.status AS edition_status,
            editions.is_active AS edition_is_active
         FROM edition_mangas
         INNER JOIN editions ON editions.id = edition_mangas.edition_id
         WHERE edition_mangas.manga_id = :manga_id
         ORDER BY edition_mangas.display_order ASC, editions.year DESC, editions.id DESC"
    );
    $attachmentsStmt->execute([
        'manga_id' => $mangaId
    ]);
    $attachments = $attachmentsStmt->fetchAll();

    $availableEditionsStmt = $pdo->prepare(
        "SELECT
            editions.id,
            editions.title,
            editions.year,
            editions.status,
            editions.is_active
         FROM editions
         WHERE editions.id NOT IN (
             SELECT edition_id
             FROM edition_mangas
             WHERE manga_id = :manga_id
         )
         ORDER BY editions.is_active DESC, editions.year DESC, editions.start_date DESC, editions.id DESC"
    );
    $availableEditionsStmt->execute([
        'manga_id' => $mangaId
    ]);
    $availableEditions = $availableEditionsStmt->fetchAll();
}

$pageTitle = $isEditMode ? 'Modifier un manga - Mangasan' : 'Créer un manga - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1><?php echo $isEditMode ? 'Modifier un manga' : 'Créer un manga'; ?></h1>
                    <p>Gère la fiche globale du manga puis son rattachement aux éditions.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/mangas.php" class="btn btn-secondary">Retour mangas</a>
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
                    <form method="post" action="<?php echo $isEditMode ? '/mangasan/actions/manga_update.php' : '/mangasan/actions/manga_create.php'; ?>" enctype="multipart/form-data" class="admin-form">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="manga_id" value="<?php echo (int) $manga['id']; ?>">
                        <?php endif; ?>

                        <div class="admin-form-section">
                            <h2>Informations générales</h2>

                            <div class="admin-field">
                                <label for="title">Titre</label>
                                <input type="text" id="title" name="title" value="<?php echo e((string) $manga['title']); ?>" required>
                            </div>

                            <div class="admin-field">
                                <label for="subtitle">Sous-titre</label>
                                <input type="text" id="subtitle" name="subtitle" value="<?php echo e((string) $manga['subtitle']); ?>">
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="author">Auteur</label>
                                    <input type="text" id="author" name="author" value="<?php echo e((string) $manga['author']); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="illustrator">Illustrateur</label>
                                    <input type="text" id="illustrator" name="illustrator" value="<?php echo e((string) $manga['illustrator']); ?>">
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="publisher">Éditeur</label>
                                    <input type="text" id="publisher" name="publisher" value="<?php echo e((string) $manga['publisher']); ?>">
                                </div>

                                <div class="admin-field">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status">
                                        <option value="active" <?php echo $manga['status'] === 'active' ? 'selected' : ''; ?>>Actif</option>
                                        <option value="inactive" <?php echo $manga['status'] === 'inactive' ? 'selected' : ''; ?>>Non actif</option>
                                    </select>
                                </div>
                            </div>

                            <div class="admin-field">
                                <label for="summary">Résumé</label>
                                <textarea id="summary" name="summary"><?php echo e((string) $manga['summary']); ?></textarea>
                            </div>

                            <div class="admin-field">
                                <label for="video_url">URL vidéo</label>
                                <input type="text" id="video_url" name="video_url" value="<?php echo e((string) $manga['video_url']); ?>">
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h2>Images</h2>

                            <div class="admin-field">
                                <label for="card_image_path">Chemin / URL image card</label>
                                <input type="text" id="card_image_path" name="card_image_path" value="<?php echo e((string) $manga['card_image']); ?>">
                            </div>

                            <div class="admin-field">
                                <label for="card_image_file">Upload image card</label>
                                <input type="file" id="card_image_file" name="card_image_file" accept=".jpg,.jpeg,.png,.webp">
                            </div>

                            <div class="admin-field">
                                <label for="cover_image_path">Chemin / URL image couverture</label>
                                <input type="text" id="cover_image_path" name="cover_image_path" value="<?php echo e((string) $manga['cover_image']); ?>">
                            </div>

                            <div class="admin-field">
                                <label for="cover_image_file">Upload image couverture</label>
                                <input type="file" id="cover_image_file" name="cover_image_file" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo $isEditMode ? 'Enregistrer' : 'Créer'; ?></button>
                            <a href="/mangasan/admin/mangas.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>

                    <?php if ($isEditMode): ?>
                        <div class="admin-form-section">
                            <h2>Rattachement aux éditions</h2>

                            <?php if ($stats['editions_count'] > 0): ?>
                                <div class="alert success">
                                    Ce manga est déjà présent dans <?php echo (int) $stats['editions_count']; ?> édition(s).
                                </div>
                            <?php endif; ?>

                            <?php if ($availableEditions): ?>
                                <form method="post" action="/mangasan/actions/edition_manga_attach.php" class="admin-form">
                                    <input type="hidden" name="manga_id" value="<?php echo (int) $manga['id']; ?>">

                                    <div class="admin-form-grid">
                                        <div class="admin-field">
                                            <label for="edition_id">Édition à rattacher</label>
                                            <select id="edition_id" name="edition_id" required>
                                                <option value="">Choisir une édition</option>
                                                <?php foreach ($availableEditions as $edition): ?>
                                                    <option value="<?php echo (int) $edition['id']; ?>">
                                                        <?php echo e($edition['title']); ?> - <?php echo e((string) $edition['year']); ?> - <?php echo e(editionStatusLabel((string) $edition['status'])); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="admin-field">
                                            <label for="attach_display_order">Ordre d’affichage</label>
                                            <input type="number" id="attach_display_order" name="display_order" min="0" value="0">
                                        </div>
                                    </div>

                                    <div class="admin-form-actions">
                                        <button type="submit" class="btn btn-primary">Rattacher à l’édition</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert success">
                                    Ce manga est déjà rattaché à toutes les éditions disponibles.
                                </div>
                            <?php endif; ?>

                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Édition</th>
                                            <th>Statut</th>
                                            <th>Ordre</th>
                                            <th>Visible</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attachments as $attachment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo e($attachment['edition_title']); ?></strong>
                                                    <br>
                                                    <span class="admin-cell-muted"><?php echo e((string) $attachment['edition_year']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="admin-badge <?php echo $attachment['edition_status'] === 'active' ? 'is-visible' : 'is-hidden'; ?>">
                                                        <?php echo e(editionStatusLabel((string) $attachment['edition_status'])); ?>
                                                    </span>
                                                </td>
                                                <td colspan="3">
                                                    <div class="admin-actions-inline admin-actions-inline--stretch">
                                                        <form method="post" action="/mangasan/actions/edition_manga_update.php" class="admin-inline-form">
                                                            <input type="hidden" name="relation_id" value="<?php echo (int) $attachment['relation_id']; ?>">

                                                            <input type="number" name="display_order" min="0" value="<?php echo (int) $attachment['display_order']; ?>" class="admin-inline-input">

                                                            <select name="is_visible" class="admin-inline-input">
                                                                <option value="1" <?php echo (int) $attachment['is_visible'] === 1 ? 'selected' : ''; ?>>Visible</option>
                                                                <option value="0" <?php echo (int) $attachment['is_visible'] === 0 ? 'selected' : ''; ?>>Masquée</option>
                                                            </select>

                                                            <button type="submit" class="btn btn-primary">Mettre à jour</button>
                                                        </form>

                                                        <form method="post" action="/mangasan/actions/edition_manga_detach.php" onsubmit="return confirm('Détacher ce manga de cette édition ?');">
                                                            <input type="hidden" name="relation_id" value="<?php echo (int) $attachment['relation_id']; ?>">
                                                            <button type="submit" class="btn btn-secondary">Détacher</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php if (!$attachments): ?>
                                            <tr>
                                                <td colspan="5">Ce manga n’est rattaché à aucune édition pour le moment.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="admin-form-section">
                            <h2>Rattachement aux éditions</h2>
                            <div class="alert success">
                                Enregistre d’abord le manga pour pouvoir ensuite le rattacher à une ou plusieurs éditions.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="admin-preview-card">
                    <div class="admin-preview-head">
                        <h2>Résumé</h2>
                    </div>

                    <div class="admin-preview-section">
                        <div class="admin-summary-item">
                            <span>Mode</span>
                            <strong><?php echo $isEditMode ? 'Modification' : 'Création'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Statut</span>
                            <strong><?php echo e(mangaStatusLabel((string) $manga['status'])); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Éditions liées</span>
                            <strong><?php echo (int) $stats['editions_count']; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Reviews liées</span>
                            <strong><?php echo (int) $stats['reviews_count']; ?></strong>
                        </div>

                        <?php if ($isEditMode): ?>
                            <div class="admin-summary-item">
                                <span>Créé le</span>
                                <strong><?php echo e((string) $manga['created_at']); ?></strong>
                            </div>

                            <div class="admin-summary-item">
                                <span>Mis à jour le</span>
                                <strong><?php echo e((string) $manga['updated_at']); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="admin-thumb-stack">
                        <div class="admin-thumb-group">
                            <span class="admin-thumb-label">Image card</span>
                            <?php if (!empty($manga['card_image'])): ?>
                                <img src="<?php echo e((string) $manga['card_image']); ?>" alt="" class="admin-thumb-preview">
                            <?php else: ?>
                                <div class="admin-thumb-preview admin-thumb-placeholder">Aucune image</div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-thumb-group">
                            <span class="admin-thumb-label">Image couverture</span>
                            <?php if (!empty($manga['cover_image'])): ?>
                                <img src="<?php echo e((string) $manga['cover_image']); ?>" alt="" class="admin-thumb-preview">
                            <?php else: ?>
                                <div class="admin-thumb-preview admin-thumb-placeholder">Aucune image</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
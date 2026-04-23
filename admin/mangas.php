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

function excerpt(?string $value, int $length = 100): string
{
    $text = trim((string) $value);

    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length - 3) . '...';
}

$pageTitle = 'Gestion des mangas - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$stmt = $pdo->query(
    "SELECT
        mangas.id,
        mangas.title,
        mangas.subtitle,
        mangas.author,
        mangas.publisher,
        mangas.card_image,
        mangas.cover_image,
        mangas.status,
        mangas.updated_at,
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
     ORDER BY mangas.updated_at DESC, mangas.id DESC"
);

$mangas = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Gestion des mangas</h1>
                    <p>Crée, modifie, rattache et supprime les mangas du catalogue.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/manga_edit.php" class="btn btn-primary">Créer un manga</a>
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
                                <th>Visuel</th>
                                <th>Titre</th>
                                <th>Auteur / Éditeur</th>
                                <th>Statut</th>
                                <th>Éditions</th>
                                <th>Reviews</th>
                                <th>Mis à jour</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mangas as $manga): ?>
                                <tr>
                                    <td>
                                        <?php $thumb = $manga['card_image'] ?: $manga['cover_image']; ?>
                                        <?php if (!empty($thumb)): ?>
                                            <img src="<?php echo e($thumb); ?>" alt="" class="admin-thumb">
                                        <?php else: ?>
                                            <div class="admin-thumb admin-thumb-placeholder">Aucune image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo e($manga['title']); ?></strong>
                                        <?php if (!empty($manga['subtitle'])): ?>
                                            <br>
                                            <span class="admin-cell-muted"><?php echo e($manga['subtitle']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($manga['author'])): ?>
                                            <?php echo e($manga['author']); ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>

                                        <?php if (!empty($manga['publisher'])): ?>
                                            <br>
                                            <span class="admin-cell-muted"><?php echo e($manga['publisher']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="admin-badge <?php echo $manga['status'] === 'active' ? 'is-visible' : 'is-hidden'; ?>">
                                            <?php echo e(mangaStatusLabel((string) $manga['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo (int) $manga['editions_count']; ?></td>
                                    <td><?php echo (int) $manga['reviews_count']; ?></td>
                                    <td><?php echo e((string) $manga['updated_at']); ?></td>
                                    <td>
                                        <div class="admin-actions-inline">
                                            <a href="/mangasan/admin/manga_edit.php?id=<?php echo (int) $manga['id']; ?>" class="btn btn-primary">Modifier</a>

                                            <form method="post" action="/mangasan/actions/manga_delete.php" onsubmit="return confirm('Supprimer ce manga ? Les liaisons aux éditions et les reviews seront aussi supprimées. Cette action est irréversible.');">
                                                <input type="hidden" name="manga_id" value="<?php echo (int) $manga['id']; ?>">
                                                <button type="submit" class="btn btn-secondary">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <?php if (!empty($manga['subtitle']) || !empty($manga['author']) || !empty($manga['publisher'])): ?>
                                    <tr class="admin-table-row-note">
                                        <td colspan="8">
                                            <?php
                                            $details = [];

                                            if (!empty($manga['subtitle'])) {
                                                $details[] = excerpt((string) $manga['subtitle'], 120);
                                            }

                                            if (!empty($manga['author'])) {
                                                $details[] = 'Auteur : ' . excerpt((string) $manga['author'], 80);
                                            }

                                            if (!empty($manga['publisher'])) {
                                                $details[] = 'Éditeur : ' . excerpt((string) $manga['publisher'], 80);
                                            }

                                            echo e(implode(' • ', $details));
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if (!$mangas): ?>
                                <tr>
                                    <td colspan="8">Aucun manga trouvé.</td>
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
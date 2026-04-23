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

$editionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditMode = $editionId !== false && $editionId !== null;

$edition = [
    'id' => null,
    'title' => '',
    'year' => date('Y'),
    'description' => '',
    'status' => 'draft',
    'is_active' => 0,
    'general_ranking_visibility' => 'hidden',
    'general_ranking_access' => 'members',
    'ranking_calculation_method' => 'average',
    'score_max' => 20,
    'start_date' => '',
    'end_date' => '',
    'created_at' => '',
    'updated_at' => ''
    
];

$stats = [
    'mangas_count' => 0,
    'reviews_count' => 0
];

if ($isEditMode) {
    $stmt = $pdo->prepare(
        "SELECT
            editions.*,
            (
                SELECT COUNT(*)
                FROM edition_mangas
                WHERE edition_mangas.edition_id = editions.id
            ) AS mangas_count,
            (
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.edition_id = editions.id
            ) AS reviews_count
         FROM editions
         WHERE editions.id = :id
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $editionId
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        setFlashMessage('error', 'Édition introuvable.');
        header('Location: /mangasan/admin/editions.php');
        exit;
    }

    $edition = $row;
    $stats['mangas_count'] = (int) $row['mangas_count'];
    $stats['reviews_count'] = (int) $row['reviews_count'];
}

$pageTitle = $isEditMode ? 'Modifier une édition - Mangasan' : 'Créer une édition - Mangasan';
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
                    <h1><?php echo $isEditMode ? 'Modifier une édition' : 'Créer une édition'; ?></h1>
                    <p>Configure les informations générales, le statut et la période de l’édition.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/editions.php" class="btn btn-secondary">Retour éditions</a>
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
                    <form method="post" action="<?php echo $isEditMode ? '/mangasan/actions/edition_update.php' : '/mangasan/actions/edition_create.php'; ?>" class="admin-form">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="edition_id" value="<?php echo (int) $edition['id']; ?>">
                        <?php endif; ?>

                        <div class="admin-form-section">
                            <h2>Informations générales</h2>

                            <div class="admin-field">
                                <label for="title">Titre</label>
                                <input type="text" id="title" name="title" value="<?php echo e((string) $edition['title']); ?>" required>
                            </div>

                            <div class="admin-field">
                                <label for="year">Année</label>
                                <input type="number" id="year" name="year" min="2000" max="2100" value="<?php echo e((string) $edition['year']); ?>" required>
                            </div>

                            <div class="admin-field">
                                <label for="description">Description</label>
                                <textarea id="description" name="description"><?php echo e((string) $edition['description']); ?></textarea>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h2>Statut et classement</h2>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status" required>
                                        <option value="draft" <?php echo $edition['status'] === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                        <option value="active" <?php echo $edition['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="closed" <?php echo $edition['status'] === 'closed' ? 'selected' : ''; ?>>Clôturée</option>
                                        <option value="archived" <?php echo $edition['status'] === 'archived' ? 'selected' : ''; ?>>Archivée</option>
                                    </select>
                                </div>

                                <div class="admin-field">
                                    <label for="general_ranking_visibility">Visibilité du classement général</label>
                                    <select id="general_ranking_visibility" name="general_ranking_visibility">
                                        <option value="visible" <?php echo (string) $edition['general_ranking_visibility'] === 'visible' ? 'selected' : ''; ?>>Visible</option>
                                        <option value="hidden" <?php echo (string) $edition['general_ranking_visibility'] === 'hidden' ? 'selected' : ''; ?>>Masqué</option>
                                    </select>
                                </div>
                                <div class="admin-field">
                                    <label for="general_ranking_access">Accès au classement général</label>
                                    <select id="general_ranking_access" name="general_ranking_access">
                                        <option value="members" <?php echo (string) $edition['general_ranking_access'] === 'members' ? 'selected' : ''; ?>>Membres connectés uniquement</option>
                                        <option value="public" <?php echo (string) $edition['general_ranking_access'] === 'public' ? 'selected' : ''; ?>>Tout le monde</option>
                                    </select>
                                </div>
                            </div>
                            <div class="admin-field">
                                <label for="score_max">Barème des notes</label>
                                <select id="score_max" name="score_max" required>
                                    <option value="5" <?php echo (int) $edition['score_max'] === 5 ? 'selected' : ''; ?>>Sur 5</option>
                                    <option value="10" <?php echo (int) $edition['score_max'] === 10 ? 'selected' : ''; ?>>Sur 10</option>
                                    <option value="20" <?php echo (int) $edition['score_max'] === 20 ? 'selected' : ''; ?>>Sur 20</option>
                                </select>
                            </div>
                            <div class="admin-field">
                                <label for="ranking_calculation_method">Méthode de calcul du classement</label>
                                <select id="ranking_calculation_method" name="ranking_calculation_method">
                                    <option value="average" selected>Moyenne</option>
                                </select>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h2>Période</h2>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="start_date">Date de début</label>
                                    <input type="date" id="start_date" name="start_date" value="<?php echo e((string) $edition['start_date']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="end_date">Date de fin</label>
                                    <input type="date" id="end_date" name="end_date" value="<?php echo e((string) $edition['end_date']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo $isEditMode ? 'Enregistrer' : 'Créer'; ?></button>
                            <a href="/mangasan/admin/editions.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
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
                            <span>Statut actuel</span>
                            <strong><?php echo e(editionStatusLabel((string) $edition['status'])); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Édition active</span>
                            <strong><?php echo (int) $edition['is_active'] === 1 ? 'Oui' : 'Non'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Mangas liés</span>
                            <strong><?php echo (int) $stats['mangas_count']; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Reviews liées</span>
                            <strong><?php echo (int) $stats['reviews_count']; ?></strong>
                        </div>

                        <?php if ($isEditMode): ?>
                            <div class="admin-summary-item">
                                <span>Créée le</span>
                                <strong><?php echo e((string) $edition['created_at']); ?></strong>
                            </div>

                            <div class="admin-summary-item">
                                <span>Mise à jour le</span>
                                <strong><?php echo e((string) $edition['updated_at']); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
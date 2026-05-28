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


function reviewFormTypeLabel(?string $formType): string
{
    return match ((string) $formType) {
        'mangasan_reading_sheet_v1' => 'Fiche Manga San',
        'classic_score' => 'Fiche avec notes',
        default => (string) ($formType ?? 'classic_score')
    };
}

function rankingMethodLabel(?string $method): string
{
    return match ((string) $method) {
        'rank_points' => 'Points par rang',
        'average', 'average_score' => 'Moyenne des notes',
        default => (string) ($method ?? 'average_score')
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
    'ranking_calculation_method' => 'average_score',
    'review_form_type' => 'classic_score',
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
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="review_form_type">Type de fiche de lecture</label>
                                    <select id="review_form_type" name="review_form_type" required>
                                        <option value="classic_score" <?php echo (string) ($edition['review_form_type'] ?? 'classic_score') === 'classic_score' ? 'selected' : ''; ?>>
                                            Ancienne fiche avec notes
                                        </option>
                                        <option value="mangasan_reading_sheet_v1" <?php echo (string) ($edition['review_form_type'] ?? 'classic_score') === 'mangasan_reading_sheet_v1' ? 'selected' : ''; ?>>
                                            Fiche Manga San / Myriam
                                        </option>
                                    </select>
                                    <small class="admin-help-text">
                                        Permet de changer le modèle de fiche utilisé par les élèves pour cette édition.
                                    </small>
                                </div>

                                <div class="admin-field">
                                    <label for="ranking_calculation_method">Méthode de calcul du classement</label>
                                    <select id="ranking_calculation_method" name="ranking_calculation_method" required>
                                        <option value="average_score" <?php echo in_array((string) $edition['ranking_calculation_method'], ['average', 'average_score'], true) ? 'selected' : ''; ?>>
                                            Moyenne des notes
                                        </option>
                                        <option value="rank_points" <?php echo (string) $edition['ranking_calculation_method'] === 'rank_points' ? 'selected' : ''; ?>>
                                            Points selon le rang personnel
                                        </option>
                                    </select>
                                    <small class="admin-help-text" id="ranking_method_help">
                                        Pour Manga San, le mode conseillé est : points selon le rang personnel.
                                    </small>
                                </div>
                            </div>

                            <div class="admin-field" id="score_max_field" data-dynamic-field="score_max">
                                <label for="score_max">Barème des notes</label>
                                <select id="score_max" name="score_max" required>
                                    <option value="5" <?php echo (int) $edition['score_max'] === 5 ? 'selected' : ''; ?>>Sur 5</option>
                                    <option value="10" <?php echo (int) $edition['score_max'] === 10 ? 'selected' : ''; ?>>Sur 10</option>
                                    <option value="20" <?php echo (int) $edition['score_max'] === 20 ? 'selected' : ''; ?>>Sur 20</option>
                                </select>
                                <small class="admin-help-text" id="score_max_help">
                                    Utilisé uniquement si le classement repose sur une moyenne de notes.
                                </small>
                            </div>

                            <div class="admin-mode-advice" id="edition_mode_advice" aria-live="polite"></div>
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
                            <span>Type de fiche</span>
                            <strong><?php echo e(reviewFormTypeLabel((string) ($edition['review_form_type'] ?? 'classic_score'))); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Méthode classement</span>
                            <strong><?php echo e(rankingMethodLabel((string) $edition['ranking_calculation_method'])); ?></strong>
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


<style>
    .admin-dynamic-hidden {
        display: none !important;
    }

    .admin-mode-advice {
        margin-top: 14px;
        padding: 12px 14px;
        border: 1px solid rgba(229, 9, 20, 0.28);
        border-radius: var(--radius-sm);
        background: rgba(229, 9, 20, 0.08);
        color: var(--color-text-muted);
        font-size: 0.92rem;
        line-height: 1.5;
    }

    .admin-mode-advice strong {
        color: var(--color-text);
    }
</style>

<script>
(function () {
    const reviewFormType = document.getElementById('review_form_type');
    const rankingMethod = document.getElementById('ranking_calculation_method');
    const scoreMaxField = document.getElementById('score_max_field');
    const rankingHelp = document.getElementById('ranking_method_help');
    const advice = document.getElementById('edition_mode_advice');

    if (!reviewFormType || !rankingMethod || !scoreMaxField || !advice) {
        return;
    }

    const messages = {
        classicAverage: '<strong>Mode classique :</strong> les élèves remplissent une fiche avec notes. Le barème est utile et le classement général utilise la moyenne.',
        classicRank: '<strong>Mode mixte :</strong> fiche classique, mais classement par rang personnel. Le barème reste masqué car il ne sert pas au classement général.',
        mangaRank: '<strong>Mode Manga San conseillé :</strong> fiche de lecture complète, puis classement par rang. Le barème des notes n’est pas utilisé.',
        mangaAverage: '<strong>Attention :</strong> fiche Manga San avec moyenne des notes. C’est possible techniquement, mais moins cohérent si les élèves ne saisissent pas de notes.'
    };

    function isAverageMethod(value) {
        return value === 'average' || value === 'average_score';
    }

    function refreshEditionMode() {
        const formType = reviewFormType.value;
        const method = rankingMethod.value;
        const isMangaSan = formType === 'mangasan_reading_sheet_v1';
        const isAverage = isAverageMethod(method);

        scoreMaxField.classList.toggle('admin-dynamic-hidden', !isAverage);

        if (rankingHelp) {
            rankingHelp.textContent = isMangaSan
                ? 'Pour la fiche Manga San, le mode conseillé est : points selon le rang personnel.'
                : 'Pour l’ancienne fiche avec notes, le mode conseillé est : moyenne des notes.';
        }

        if (!isMangaSan && isAverage) {
            advice.innerHTML = messages.classicAverage;
        } else if (!isMangaSan && !isAverage) {
            advice.innerHTML = messages.classicRank;
        } else if (isMangaSan && !isAverage) {
            advice.innerHTML = messages.mangaRank;
        } else {
            advice.innerHTML = messages.mangaAverage;
        }
    }

    reviewFormType.addEventListener('change', function () {
        if (reviewFormType.value === 'mangasan_reading_sheet_v1') {
            rankingMethod.value = 'rank_points';
        } else if (reviewFormType.value === 'classic_score') {
            rankingMethod.value = 'average_score';
        }

        refreshEditionMode();
    });

    rankingMethod.addEventListener('change', refreshEditionMode);
    refreshEditionMode();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
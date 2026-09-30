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

function editionStatusLabel(string $status): string
{
    return match ($status) {
        'draft' => 'Brouillon',
        'active' => 'Active',
        'closed' => 'Clôturée',
        'archived' => 'Archivée',
        default => $status,
    };
}

function reviewFormTypeLabel(?string $formType): string
{
    return match ((string) $formType) {
        'mangasan_reading_sheet_v1' => 'Fiche Mangasan / Myriam',
        'classic_score' => 'Fiche avec notes',
        default => (string) ($formType ?? 'classic_score'),
    };
}

function rankingMethodLabel(?string $method): string
{
    return match ((string) $method) {
        'rank_points' => 'Points selon le rang',
        'average', 'average_score' => 'Moyenne des notes',
        default => (string) ($method ?? 'average_score'),
    };
}

function formatEditionDate(?string $date): string
{
    $value = trim((string) $date);

    if ($value === '') {
        return 'Non définie';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : $value;
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
    'updated_at' => '',
];

$stats = [
    'mangas_count' => 0,
    'reviews_count' => 0,
    'participants_count' => 0,
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
            ) AS reviews_count,
            (
                SELECT COUNT(DISTINCT reviews.user_id)
                FROM reviews
                WHERE reviews.edition_id = editions.id
            ) AS participants_count
         FROM editions
         WHERE editions.id = :id
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $editionId,
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
    $stats['participants_count'] = (int) $row['participants_count'];
}

$pageTitle = $isEditMode ? 'Modifier une édition - Mangasan' : 'Créer une édition - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/admin-editions.js',
    '/mangasan/public/assets/js/help-system.js',
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-edition-edit-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="editionEditHelpHeading">
                <div class="admin-page-heading">
                    <h1><?php echo $isEditMode ? 'Modifier une édition' : 'Créer une édition'; ?></h1>
                    <p><?php echo $isEditMode ? 'Modifie les règles et les paramètres de cette édition.' : 'Prépare une nouvelle édition de Mangasan avant d’y rattacher les mangas.'; ?></p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/editions.php" class="btn btn-secondary">Retour éditions</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-layout-two-columns admin-edition-edit-layout">
                <div class="admin-panel admin-edition-form-panel">
                    <form
                        method="post"
                        action="<?php echo $isEditMode ? '/mangasan/actions/edition_update.php' : '/mangasan/actions/edition_create.php'; ?>"
                        class="admin-form"
                        id="editionForm"
                        data-is-active="<?php echo (int) $edition['is_active']; ?>"
                    >
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="edition_id" value="<?php echo (int) $edition['id']; ?>">
                        <?php endif; ?>

                        <section class="admin-form-section admin-edition-form-section" id="editionEditHelpGeneral">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">1</span>
                                    <h2>Informations générales</h2>
                                </div>
                                <p>Nom et présentation de l’édition.</p>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="title">Titre</label>
                                    <input type="text" id="title" name="title" value="<?php echo e((string) $edition['title']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="year">Année</label>
                                    <input type="number" id="year" name="year" min="2000" max="2100" value="<?php echo e((string) $edition['year']); ?>" required>
                                </div>
                            </div>

                            <div class="admin-field">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"><?php echo e((string) $edition['description']); ?></textarea>
                                <small class="admin-help-text">Texte interne permettant d’identifier ou de décrire l’édition.</small>
                            </div>
                        </section>

                        <section class="admin-form-section admin-edition-form-section" id="editionEditHelpLifecycle">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">2</span>
                                    <h2>Statut et période</h2>
                                </div>
                                <p>Définit l’état de l’édition et ses dates.</p>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status" required>
                                        <option value="draft" <?php echo $edition['status'] === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                        <option value="active" <?php echo $edition['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="closed" <?php echo $edition['status'] === 'closed' ? 'selected' : ''; ?>>Clôturée</option>
                                        <option value="archived" <?php echo $edition['status'] === 'archived' ? 'selected' : ''; ?>>Archivée</option>
                                    </select>
                                    <small class="admin-help-text">Le statut Active fait de cette édition l’édition principale et désactive automatiquement l’ancienne édition active.</small>
                                </div>

                                <div class="admin-edition-active-state">
                                    <span>Édition active actuellement</span>
                                    <strong><?php echo (int) $edition['is_active'] === 1 ? 'Oui' : 'Non'; ?></strong>
                                    <small>Cette valeur est mise à jour lors de l’enregistrement.</small>
                                </div>
                            </div>

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

                            <div class="admin-mode-advice" id="edition_status_advice" aria-live="polite"></div>
                        </section>

                        <section class="admin-form-section admin-edition-form-section" id="editionEditHelpReadingMode">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">3</span>
                                    <h2>Fiche de lecture et calcul du classement</h2>
                                </div>
                                <p>Détermine ce que remplissent les élèves et comment les résultats sont calculés.</p>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="review_form_type">Type de fiche de lecture</label>
                                    <select id="review_form_type" name="review_form_type" required>
                                        <option value="classic_score" <?php echo (string) ($edition['review_form_type'] ?? 'classic_score') === 'classic_score' ? 'selected' : ''; ?>>
                                            Ancienne fiche avec notes
                                        </option>
                                        <option value="mangasan_reading_sheet_v1" <?php echo (string) ($edition['review_form_type'] ?? 'classic_score') === 'mangasan_reading_sheet_v1' ? 'selected' : ''; ?>>
                                            Fiche Mangasan / Myriam
                                        </option>
                                    </select>
                                    <small class="admin-help-text">Le modèle choisi est celui que les élèves utiliseront pour cette édition.</small>
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
                                    <small class="admin-help-text" id="ranking_method_help"></small>
                                </div>
                            </div>

                            <div class="admin-field" id="score_max_field" data-dynamic-field="score_max">
                                <label for="score_max">Barème des notes</label>
                                <select id="score_max" name="score_max" required>
                                    <option value="5" <?php echo (int) $edition['score_max'] === 5 ? 'selected' : ''; ?>>Sur 5</option>
                                    <option value="10" <?php echo (int) $edition['score_max'] === 10 ? 'selected' : ''; ?>>Sur 10</option>
                                    <option value="20" <?php echo (int) $edition['score_max'] === 20 ? 'selected' : ''; ?>>Sur 20</option>
                                </select>
                                <small class="admin-help-text">Utilisé uniquement lorsque le classement repose sur une moyenne de notes.</small>
                            </div>

                            <div class="admin-mode-advice" id="edition_mode_advice" aria-live="polite"></div>
                        </section>

                        <section class="admin-form-section admin-edition-form-section" id="editionEditHelpRankingPublication">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">4</span>
                                    <h2>Publication du classement général</h2>
                                </div>
                                <p>Contrôle si le classement est affiché et qui peut le consulter.</p>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="general_ranking_visibility">Visibilité</label>
                                    <select id="general_ranking_visibility" name="general_ranking_visibility">
                                        <option value="visible" <?php echo (string) $edition['general_ranking_visibility'] === 'visible' ? 'selected' : ''; ?>>Visible</option>
                                        <option value="hidden" <?php echo (string) $edition['general_ranking_visibility'] === 'hidden' ? 'selected' : ''; ?>>Masqué</option>
                                    </select>
                                    <small class="admin-help-text">Masqué empêche l’affichage du classement général pour cette édition.</small>
                                </div>

                                <div class="admin-field">
                                    <label for="general_ranking_access">Accès</label>
                                    <select id="general_ranking_access" name="general_ranking_access">
                                        <option value="members" <?php echo (string) $edition['general_ranking_access'] === 'members' ? 'selected' : ''; ?>>Membres connectés uniquement</option>
                                        <option value="public" <?php echo (string) $edition['general_ranking_access'] === 'public' ? 'selected' : ''; ?>>Tout le monde</option>
                                    </select>
                                    <small class="admin-help-text">Ce réglage n’a d’effet que si le classement est visible.</small>
                                </div>
                            </div>
                        </section>

                        <div class="admin-form-actions admin-edition-form-actions" id="editionEditHelpSave">
                            <button type="submit" class="btn btn-primary"><?php echo $isEditMode ? 'Enregistrer les modifications' : 'Créer l’édition'; ?></button>
                            <a href="/mangasan/admin/editions.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>

                <aside class="admin-preview-card admin-edition-summary-card" id="editionEditHelpSummary">
                    <div class="admin-preview-head">
                        <div>
                            <span class="admin-preview-kicker">Résumé</span>
                            <h2 id="editionSummaryTitle"><?php echo $edition['title'] !== '' ? e((string) $edition['title']) : 'Nouvelle édition'; ?></h2>
                        </div>
                        <?php if ((int) $edition['is_active'] === 1): ?>
                            <span class="admin-badge is-current">Active</span>
                        <?php endif; ?>
                    </div>

                    <div class="admin-preview-section">
                        <div class="admin-summary-item">
                            <span>Mode</span>
                            <strong><?php echo $isEditMode ? 'Modification' : 'Création'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Statut</span>
                            <strong id="editionSummaryStatus"><?php echo e(editionStatusLabel((string) $edition['status'])); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Période</span>
                            <strong id="editionSummaryPeriod"><?php echo e(formatEditionDate((string) $edition['start_date'])); ?> → <?php echo e(formatEditionDate((string) $edition['end_date'])); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Type de fiche</span>
                            <strong id="editionSummaryFormType"><?php echo e(reviewFormTypeLabel((string) ($edition['review_form_type'] ?? 'classic_score'))); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Calcul du classement</span>
                            <strong id="editionSummaryRankingMethod"><?php echo e(rankingMethodLabel((string) $edition['ranking_calculation_method'])); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Publication du classement</span>
                            <strong id="editionSummaryRankingPublication">
                                <?php echo (string) $edition['general_ranking_visibility'] === 'visible' ? 'Visible' : 'Masqué'; ?> ·
                                <?php echo (string) $edition['general_ranking_access'] === 'public' ? 'Public' : 'Membres'; ?>
                            </strong>
                        </div>
                    </div>

                    <div class="admin-edition-summary-stats">
                        <div>
                            <span>Mangas liés</span>
                            <strong><?php echo (int) $stats['mangas_count']; ?></strong>
                        </div>
                        <div>
                            <span>Participants</span>
                            <strong><?php echo (int) $stats['participants_count']; ?></strong>
                        </div>
                        <div>
                            <span>Fiches</span>
                            <strong><?php echo (int) $stats['reviews_count']; ?></strong>
                        </div>
                    </div>

                    <?php if ($isEditMode): ?>
                        <div class="admin-edition-summary-meta">
                            <span>Créée le <?php echo e((string) $edition['created_at']); ?></span>
                            <span>Dernière mise à jour : <?php echo e((string) $edition['updated_at']); ?></span>
                        </div>
                    <?php else: ?>
                        <p class="admin-edition-summary-note">Après création, tu pourras rattacher les mangas depuis leur fiche ou depuis la gestion groupée des mangas.</p>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php
$guideSteps = [
    [
        'target' => '#editionEditHelpHeading',
        'title' => $isEditMode ? 'Modifier une édition' : 'Créer une édition',
        'text' => $isEditMode
            ? 'Cette page permet de modifier toutes les règles de l’édition : informations générales, statut, dates, fiche de lecture et publication du classement.'
            : 'Cette page permet de préparer une nouvelle édition de Mangasan. Les mangas pourront être rattachés après la création.',
        'tip' => 'Les modifications prennent effet après l’enregistrement du formulaire.'
    ],
    [
        'target' => '#editionEditHelpGeneral',
        'title' => 'Informations générales',
        'text' => 'Renseignez le titre, l’année et éventuellement une description. Ces informations servent à identifier clairement l’édition dans l’administration.'
    ],
    [
        'target' => '#editionEditHelpLifecycle',
        'title' => 'Statut et période',
        'text' => 'Le statut décrit l’état de l’édition. Les dates indiquent sa période de déroulement. Passer une édition au statut Active la rend également édition active de Mangasan.',
        'tip' => 'Lorsqu’une édition devient active, l’édition précédemment active est automatiquement désactivée et passe au statut clôturée.'
    ],
    [
        'target' => '#editionEditHelpReadingMode',
        'title' => 'Fiche de lecture et classement',
        'text' => 'Choisissez ici le modèle de fiche rempli par les élèves et la méthode utilisée pour calculer le classement. La fiche Mangasan / Myriam est normalement associée aux points selon le rang personnel.',
        'tip' => 'Pour l’ancienne fiche avec notes, la méthode Moyenne des notes utilise aussi le barème choisi.'
    ],
    [
        'target' => '#editionEditHelpRankingPublication',
        'title' => 'Publication du classement général',
        'text' => 'Visibilité détermine si le classement général peut être affiché. Accès permet ensuite de le réserver aux membres connectés ou de l’ouvrir à tout le monde.'
    ],
    [
        'target' => '#editionEditHelpSummary',
        'title' => 'Résumé de la configuration',
        'text' => 'Ce panneau résume les choix principaux et se met à jour pendant la saisie. En modification, il rappelle aussi le nombre de mangas, de participants et de fiches déjà liés à l’édition.'
    ],
    [
        'target' => '#editionEditHelpSave',
        'title' => $isEditMode ? 'Enregistrer les modifications' : 'Créer l’édition',
        'text' => $isEditMode
            ? 'Enregistrer applique les changements. Annuler revient à la liste des éditions sans enregistrer les modifications en cours.'
            : 'Créer l’édition enregistre cette configuration. Vous pourrez ensuite ajouter les mangas à cette édition depuis la gestion des mangas.'
    ],
];

renderAdminHelpGuide([
    'id' => $isEditMode ? 'admin-edition-edit' : 'admin-edition-create',
    'title' => $isEditMode ? 'Guide — Modifier une édition' : 'Guide — Créer une édition',
    'steps' => $guideSteps,
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

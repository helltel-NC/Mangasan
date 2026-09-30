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

function editionStatusClass(string $status): string
{
    return match ($status) {
        'active' => 'is-visible',
        'draft' => 'is-neutral',
        'closed' => 'is-warning',
        'archived' => 'is-hidden',
        default => 'is-neutral',
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

$pageTitle = 'Gestion des éditions - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/help-system.js',
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$stmt = $pdo->query(
    "SELECT
        editions.id,
        editions.title,
        editions.year,
        editions.description,
        editions.status,
        editions.is_active,
        editions.general_ranking_visibility,
        editions.general_ranking_access,
        editions.ranking_calculation_method,
        editions.review_form_type,
        editions.score_max,
        editions.start_date,
        editions.end_date,
        editions.created_at,
        editions.updated_at,
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
     ORDER BY editions.is_active DESC, editions.year DESC, editions.start_date DESC, editions.id DESC"
);

$editions = $stmt->fetchAll();

$overview = [
    'editions_count' => count($editions),
    'mangas_links' => 0,
    'reviews_count' => 0,
    'participants_count' => 0,
    'active_title' => '',
    'active_year' => '',
];

foreach ($editions as $edition) {
    $overview['mangas_links'] += (int) $edition['mangas_count'];
    $overview['reviews_count'] += (int) $edition['reviews_count'];
    $overview['participants_count'] += (int) $edition['participants_count'];

    if ((int) $edition['is_active'] === 1) {
        $overview['active_title'] = (string) $edition['title'];
        $overview['active_year'] = (string) $edition['year'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-editions-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="editionsHelpHeading">
                <div class="admin-page-heading">
                    <h1>Gestion des éditions</h1>
                    <p>Crée et pilote les différentes éditions de Mangasan, leur période, leur fiche de lecture et leur classement.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/rankings.php" class="btn btn-secondary">Classements</a>
                    <a href="/mangasan/admin/edition_edit.php" class="btn btn-primary" id="editionsHelpCreate">Créer une édition</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-editions-layout">
                <section class="admin-editions-overview" id="editionsHelpOverview" aria-label="Vue d'ensemble des éditions">
                    <article class="admin-edition-stat-card">
                        <span>Éditions</span>
                        <strong><?php echo (int) $overview['editions_count']; ?></strong>
                        <small>Total enregistré</small>
                    </article>

                    <article class="admin-edition-stat-card admin-edition-stat-card--active">
                        <span>Édition active</span>
                        <?php if ($overview['active_title'] !== ''): ?>
                            <strong><?php echo e($overview['active_title']); ?></strong>
                            <small><?php echo e($overview['active_year']); ?></small>
                        <?php else: ?>
                            <strong>Aucune</strong>
                            <small>Aucune édition n’est actuellement active</small>
                        <?php endif; ?>
                    </article>

                    <article class="admin-edition-stat-card">
                        <span>Rattachements mangas</span>
                        <strong><?php echo (int) $overview['mangas_links']; ?></strong>
                        <small>Toutes éditions confondues</small>
                    </article>

                    <article class="admin-edition-stat-card">
                        <span>Fiches de lecture</span>
                        <strong><?php echo (int) $overview['reviews_count']; ?></strong>
                        <small><?php echo (int) $overview['participants_count']; ?> participation(s) cumulée(s)</small>
                    </article>
                </section>

                <section class="admin-panel admin-editions-list-panel" id="editionsHelpList" aria-label="Liste des éditions">
                    <div class="admin-editions-list-heading">
                        <div>
                            <h2>Éditions enregistrées</h2>
                            <p>Chaque carte résume la configuration et l’activité d’une édition.</p>
                        </div>
                        <span><?php echo count($editions); ?> édition<?php echo count($editions) > 1 ? 's' : ''; ?></span>
                    </div>

                    <?php if ($editions): ?>
                        <div class="admin-editions-card-grid">
                            <?php foreach ($editions as $edition): ?>
                                <?php
                                $isActive = (int) $edition['is_active'] === 1;
                                $rankingIsVisible = (string) $edition['general_ranking_visibility'] === 'visible';
                                $rankingIsPublic = (string) $edition['general_ranking_access'] === 'public';
                                $rankingMethod = (string) $edition['ranking_calculation_method'];
                                ?>
                                <article class="admin-edition-card<?php echo $isActive ? ' is-current' : ''; ?>">
                                    <div class="admin-edition-card-head">
                                        <div>
                                            <div class="admin-edition-card-badges">
                                                <span class="admin-badge <?php echo e(editionStatusClass((string) $edition['status'])); ?>">
                                                    <?php echo e(editionStatusLabel((string) $edition['status'])); ?>
                                                </span>
                                                <?php if ($isActive): ?>
                                                    <span class="admin-badge is-current">Édition active</span>
                                                <?php endif; ?>
                                            </div>

                                            <h3><?php echo e((string) $edition['title']); ?></h3>
                                            <p class="admin-edition-year"><?php echo e((string) $edition['year']); ?></p>
                                        </div>

                                        <div class="admin-edition-period">
                                            <span>Du <?php echo e(formatEditionDate((string) $edition['start_date'])); ?></span>
                                            <strong>au <?php echo e(formatEditionDate((string) $edition['end_date'])); ?></strong>
                                        </div>
                                    </div>

                                    <?php if (!empty($edition['description'])): ?>
                                        <p class="admin-edition-description">
                                            <?php echo e(mb_strimwidth((string) $edition['description'], 0, 180, '…')); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="admin-edition-activity">
                                        <div>
                                            <span>Mangas</span>
                                            <strong><?php echo (int) $edition['mangas_count']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Participants</span>
                                            <strong><?php echo (int) $edition['participants_count']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Fiches</span>
                                            <strong><?php echo (int) $edition['reviews_count']; ?></strong>
                                        </div>
                                    </div>

                                    <div class="admin-edition-config-grid">
                                        <div>
                                            <span>Fiche de lecture</span>
                                            <strong><?php echo e(reviewFormTypeLabel((string) ($edition['review_form_type'] ?? 'classic_score'))); ?></strong>
                                        </div>
                                        <div>
                                            <span>Calcul du classement</span>
                                            <strong>
                                                <?php echo e(rankingMethodLabel($rankingMethod)); ?>
                                                <?php if (in_array($rankingMethod, ['average', 'average_score'], true)): ?>
                                                    / <?php echo (int) $edition['score_max']; ?>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                        <div>
                                            <span>Classement général</span>
                                            <strong><?php echo $rankingIsVisible ? 'Visible' : 'Masqué'; ?> · <?php echo $rankingIsPublic ? 'Public' : 'Membres'; ?></strong>
                                        </div>
                                    </div>

                                    <div class="admin-edition-card-actions editions-help-actions">
                                        <a href="/mangasan/admin/edition_edit.php?id=<?php echo (int) $edition['id']; ?>" class="btn btn-primary">Modifier</a>
                                        <a href="/mangasan/admin/rankings.php?edition_id=<?php echo (int) $edition['id']; ?>" class="btn btn-secondary">Classement</a>

                                        <form method="post" action="/mangasan/actions/edition_toggle_active.php" onsubmit="return confirm('<?php echo $isActive ? 'Désactiver cette édition ? Elle passera au statut clôturée.' : 'Activer cette édition ? L\'édition actuellement active sera désactivée.'; ?>');">
                                            <input type="hidden" name="edition_id" value="<?php echo (int) $edition['id']; ?>">
                                            <button type="submit" class="btn btn-secondary">
                                                <?php echo $isActive ? 'Désactiver' : 'Activer'; ?>
                                            </button>
                                        </form>

                                        <form method="post" action="/mangasan/actions/edition_delete.php" onsubmit="return confirm('Supprimer définitivement cette édition ? Ses rattachements mangas et toutes ses fiches de lecture seront également supprimés.');">
                                            <input type="hidden" name="edition_id" value="<?php echo (int) $edition['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Supprimer</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="admin-editions-empty-state">
                            <strong>Aucune édition enregistrée.</strong>
                            <p>Crée la première édition pour commencer à organiser la sélection Mangasan.</p>
                            <a href="/mangasan/admin/edition_edit.php" class="btn btn-primary">Créer une édition</a>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </section>
</main>

<?php
renderAdminHelpGuide([
    'id' => 'admin-editions',
    'title' => 'Guide — Gestion des éditions',
    'steps' => [
        [
            'target' => '#editionsHelpHeading',
            'title' => 'Gestion des éditions',
            'text' => 'Cette page regroupe toutes les éditions de Mangasan. Une édition définit notamment une période, un type de fiche de lecture, une méthode de classement et la sélection de mangas associée.',
            'tip' => 'Une seule édition peut être active à la fois.'
        ],
        [
            'target' => '#editionsHelpOverview',
            'title' => 'Vue d’ensemble',
            'text' => 'Ces indicateurs donnent rapidement le nombre d’éditions, l’édition actuellement active, les rattachements de mangas et le nombre de fiches de lecture enregistrées.'
        ],
        [
            'target' => '#editionsHelpCreate',
            'title' => 'Créer une nouvelle édition',
            'text' => 'Ce bouton ouvre le formulaire de création. Vous pourrez définir le titre, les dates, le statut, le type de fiche utilisé par les élèves et la méthode de calcul du classement.'
        ],
        [
            'target' => '#editionsHelpList',
            'title' => 'Lire les cartes des éditions',
            'text' => 'Chaque carte présente les dates, le nombre de mangas, de participants et de fiches, ainsi que le type de fiche et le mode de classement configurés pour cette édition.',
            'tip' => 'La mention « Édition active » identifie celle actuellement utilisée comme édition principale du site.'
        ],
        [
            'target' => '.admin-edition-config-grid',
            'title' => 'Configuration d’une édition',
            'text' => 'Cette zone rappelle le modèle de fiche de lecture, la méthode de calcul du classement et les règles de visibilité du classement général sans devoir ouvrir l’édition.'
        ],
        [
            'target' => '.editions-help-actions',
            'title' => 'Actions disponibles',
            'text' => 'Modifier ouvre la configuration complète. Classement affiche les résultats de cette édition. Activer ou Désactiver change l’édition principale. Supprimer efface définitivement l’édition.',
            'tip' => 'La suppression supprime aussi les rattachements de mangas et les fiches de lecture liées à l’édition. Utilisez-la uniquement si ces données doivent réellement disparaître.'
        ],
    ],
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

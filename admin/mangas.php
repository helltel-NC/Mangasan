<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/mangas_admin.php';
require_once __DIR__ . '/../includes/help.php';

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
        default => $status,
    };
}

function editionStatusShortLabel(string $status): string
{
    return match ($status) {
        'draft' => 'Brouillon',
        'active' => 'Active',
        'closed' => 'Clôturée',
        'archived' => 'Archivée',
        default => $status,
    };
}

$pageTitle = 'Gestion des mangas - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css',
];
$extraJs = [
    '/mangasan/public/assets/js/admin-mangas.js',
    '/mangasan/public/assets/js/help-system.js',
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$filters = normalizeAdminMangaFilters($_GET);
$search = (string) $filters['search'];
$status = (string) $filters['status'];
$editionFilter = (string) $filters['edition'];

$editions = getAdminMangaEditions($pdo);
$mangas = fetchAdminMangas($pdo, $filters);
$returnQuery = buildAdminMangasQueryString($filters);
$mangaCount = count($mangas);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-mangas-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading" id="mangasHelpHeading">
                    <h1>Gestion des mangas</h1>
                    <p>Recherche, filtre, rattache et gère les mangas du catalogue.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/manga_edit.php" class="btn btn-primary" id="mangasHelpCreate">Créer un manga</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-mangas-page-layout">
                <section class="admin-panel admin-mangas-control-panel" id="mangasHelpFilters" aria-label="Filtres des mangas">
                    <form method="get" action="/mangasan/admin/mangas.php" class="admin-filter-form">
                        <div class="admin-mangas-filter-grid">
                            <div class="admin-field">
                                <label for="search">Recherche</label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    value="<?php echo e($search); ?>"
                                    placeholder="Titre, auteur, illustrateur, éditeur..."
                                >
                            </div>

                            <div class="admin-field">
                                <label for="status">Statut</label>
                                <select id="status" name="status">
                                    <option value="">Tous</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Non actif</option>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="edition">Édition</label>
                                <select id="edition" name="edition">
                                    <option value="">Toutes les éditions</option>
                                    <option value="none" <?php echo $editionFilter === 'none' ? 'selected' : ''; ?>>Aucune édition</option>
                                    <?php foreach ($editions as $edition): ?>
                                        <option value="<?php echo (int) $edition['id']; ?>" <?php echo $editionFilter === (string) $edition['id'] ? 'selected' : ''; ?>>
                                            <?php echo e((string) $edition['title'] . ' ' . (string) $edition['year']); ?>
                                            <?php echo (int) $edition['is_active'] === 1 ? ' — édition active' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="admin-mangas-filter-footer">
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                            <a href="/mangasan/admin/mangas.php" class="btn btn-secondary">Réinitialiser</a>
                            <span class="admin-mangas-result-count">
                                <?php echo $mangaCount; ?> manga<?php echo $mangaCount > 1 ? 's' : ''; ?> affiché<?php echo $mangaCount > 1 ? 's' : ''; ?>
                            </span>
                        </div>
                    </form>
                </section>

                <form method="post" action="/mangasan/actions/mangas_bulk_action.php" id="mangasBulkForm" class="admin-mangas-bulk-form">
                    <input type="hidden" name="return_query" value="<?php echo e($returnQuery); ?>">

                    <section class="admin-panel admin-mangas-bulk-panel" id="mangasHelpBulkPanel" aria-label="Actions groupées sur les mangas">
                        <div class="admin-mangas-bulk-top">
                            <div class="admin-mangas-bulk-summary">
                                <strong>Actions groupées</strong>
                                <span id="selectedMangasCount">0 manga sélectionné</span>
                            </div>

                            <div class="admin-mangas-bulk-controls">
                                <select name="bulk_action" id="mangaBulkActionSelect" aria-label="Action groupée sur les mangas">
                                    <option value="">Choisir une action...</option>
                                    <option value="deactivate">Désactiver</option>
                                    <option value="activate">Activer</option>
                                    <option value="attach_edition">Ajouter à une édition</option>
                                    <option value="detach_edition">Retirer d’une édition</option>
                                    <option value="delete">Supprimer définitivement</option>
                                </select>

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    id="mangaBulkApplyButton"
                                    data-bulk-submit="1"
                                    disabled
                                >Appliquer</button>
                            </div>
                        </div>

                        <div class="admin-mangas-bulk-extra is-hidden" id="mangaBulkEditionOptions">
                            <div class="admin-field admin-mangas-bulk-edition-field">
                                <label for="bulk_edition_id">Édition concernée</label>
                                <select name="bulk_edition_id" id="bulk_edition_id">
                                    <option value="">Choisir une édition...</option>
                                    <?php foreach ($editions as $edition): ?>
                                        <option value="<?php echo (int) $edition['id']; ?>">
                                            <?php echo e((string) $edition['title'] . ' ' . (string) $edition['year']); ?>
                                            — <?php echo e(editionStatusShortLabel((string) $edition['status'])); ?>
                                            <?php echo (int) $edition['is_active'] === 1 ? ' / active' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="admin-mangas-bulk-help" id="mangaBulkEditionHint"></p>
                        </div>

                        <div class="admin-mangas-danger-box is-hidden" id="mangaBulkDeleteWarning">
                            <div>
                                <strong>Suppression définitive :</strong>
                                les mangas sélectionnés seront supprimés avec leurs rattachements aux éditions et leurs fiches de lecture. Cette opération est irréversible.
                            </div>

                            <label class="admin-inline-checkbox">
                                <input type="checkbox" name="bulk_confirm_delete" id="bulk_confirm_delete" value="1">
                                <span>Je comprends que les fiches de lecture liées seront également supprimées.</span>
                            </label>
                        </div>
                    </section>

                    <section class="admin-panel admin-mangas-list-panel" id="mangasHelpListPanel" aria-label="Liste des mangas">
                        <div class="admin-mangas-list-heading">
                            <h2>Catalogue</h2>
                            <span>Sélectionne les mangas à traiter avec les cases de la première colonne.</span>
                        </div>

                        <div class="admin-table-wrapper">
                            <table class="admin-table admin-mangas-table">
                                <thead>
                                    <tr>
                                        <th class="admin-selection-cell">
                                            <input type="checkbox" id="selectAllMangas" aria-label="Sélectionner tous les mangas affichés">
                                        </th>
                                        <th>Visuel</th>
                                        <th>Titre</th>
                                        <th>Auteur / Éditeur</th>
                                        <th>Statut</th>
                                        <th>Éditions</th>
                                        <th>Fiches</th>
                                        <th>Mis à jour</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mangas as $manga): ?>
                                        <tr>
                                            <td class="admin-selection-cell">
                                                <input
                                                    type="checkbox"
                                                    class="manga-selection-checkbox"
                                                    name="manga_ids[]"
                                                    value="<?php echo (int) $manga['id']; ?>"
                                                    aria-label="Sélectionner <?php echo e((string) $manga['title']); ?>"
                                                >
                                            </td>

                                            <td>
                                                <?php $thumb = $manga['card_image'] ?: $manga['cover_image']; ?>
                                                <?php if (!empty($thumb)): ?>
                                                    <img src="<?php echo e((string) $thumb); ?>" alt="" class="admin-thumb">
                                                <?php else: ?>
                                                    <div class="admin-thumb admin-thumb-placeholder">Aucune image</div>
                                                <?php endif; ?>
                                            </td>

                                            <td class="admin-manga-title-cell">
                                                <strong><?php echo e((string) $manga['title']); ?></strong>
                                                <?php if (!empty($manga['subtitle'])): ?>
                                                    <br>
                                                    <span class="admin-cell-muted"><?php echo e((string) $manga['subtitle']); ?></span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($manga['author'])): ?>
                                                    <?php echo e((string) $manga['author']); ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>

                                                <?php if (!empty($manga['illustrator'])): ?>
                                                    <br>
                                                    <span class="admin-cell-muted">Illustration : <?php echo e((string) $manga['illustrator']); ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($manga['publisher'])): ?>
                                                    <br>
                                                    <span class="admin-cell-muted">Éditeur : <?php echo e((string) $manga['publisher']); ?></span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span class="admin-badge <?php echo $manga['status'] === 'active' ? 'is-visible' : 'is-hidden'; ?>">
                                                    <?php echo e(mangaStatusLabel((string) $manga['status'])); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <strong><?php echo (int) $manga['editions_count']; ?></strong>
                                                <?php if (!empty($manga['edition_names'])): ?>
                                                    <br>
                                                    <span class="admin-cell-muted admin-manga-edition-list"><?php echo e((string) $manga['edition_names']); ?></span>
                                                <?php else: ?>
                                                    <br>
                                                    <span class="admin-cell-muted">Aucun rattachement</span>
                                                <?php endif; ?>
                                            </td>

                                            <td><?php echo (int) $manga['reviews_count']; ?></td>
                                            <td><?php echo e((string) $manga['updated_at']); ?></td>

                                            <td>
                                                <div class="admin-actions-inline mangas-help-individual-actions">
                                                    <a href="/mangasan/admin/manga_edit.php?id=<?php echo (int) $manga['id']; ?>" class="btn btn-primary">Modifier</a>

                                                    <button
                                                        type="submit"
                                                        class="btn btn-secondary"
                                                        formaction="/mangasan/actions/manga_delete.php"
                                                        formmethod="post"
                                                        name="manga_id"
                                                        value="<?php echo (int) $manga['id']; ?>"
                                                        onclick="return confirm('Supprimer ce manga ? Les liaisons aux éditions et les fiches de lecture seront aussi supprimées. Cette action est irréversible.');"
                                                    >Supprimer</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$mangas): ?>
                                        <tr>
                                            <td colspan="9" class="admin-mangas-empty-state">Aucun manga ne correspond aux filtres.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </section>
</main>


<?php
renderAdminHelpGuide([
    'id' => 'admin-mangas',
    'title' => 'Guide — Gestion des mangas',
    'steps' => [
        [
            'target' => '#mangasHelpHeading',
            'title' => 'Gestion du catalogue',
            'text' => 'Cette page regroupe tous les mangas enregistrés dans Mangasan. Elle permet de rechercher un titre, consulter ses rattachements aux éditions, modifier sa fiche et effectuer des actions sur plusieurs mangas à la fois.'
        ],
        [
            'target' => '#mangasHelpFilters',
            'title' => 'Rechercher et filtrer les mangas',
            'text' => 'La recherche accepte le titre, l’auteur, l’illustrateur ou l’éditeur. Les filtres Statut et Édition permettent de limiter la liste aux mangas actifs, non actifs, rattachés à une édition précise ou sans édition.',
            'tip' => 'Réinitialiser retire tous les filtres et réaffiche le catalogue complet.'
        ],
        [
            'target' => '#mangasHelpCreate',
            'title' => 'Créer un manga',
            'text' => 'Ce bouton ouvre la fiche de création d’un nouveau manga. Les informations générales et les images sont enregistrées d’abord ; le rattachement aux éditions devient disponible après la création.'
        ],
        [
            'target' => '#mangasHelpListPanel',
            'title' => 'Lire le catalogue',
            'text' => 'Le tableau affiche le visuel, le titre, l’auteur ou l’éditeur, le statut, les éditions associées, le nombre de fiches de lecture et la date de dernière mise à jour de chaque manga.'
        ],
        [
            'target' => '#selectAllMangas',
            'title' => 'Sélectionner plusieurs mangas',
            'text' => 'Cette case sélectionne tous les mangas actuellement affichés dans le tableau. Vous pouvez également cocher uniquement les mangas souhaités dans la première colonne.',
            'tip' => 'La sélection concerne uniquement les résultats actuellement affichés après application des filtres.'
        ],
        [
            'target' => '#mangasHelpBulkPanel',
            'title' => 'Actions groupées',
            'text' => 'Après avoir sélectionné un ou plusieurs mangas, vous pouvez les activer, les désactiver, les ajouter à une édition, les retirer d’une édition ou les supprimer définitivement.',
            'tip' => 'Ajouter à une édition ignore les mangas déjà présents. Retirer un manga d’une édition conserve les fiches de lecture déjà enregistrées.'
        ],
        [
            'target' => '#mangaBulkActionSelect',
            'title' => 'Choisir puis appliquer une action',
            'text' => 'Sélectionnez l’action souhaitée. Pour un rattachement ou un retrait, un choix d’édition apparaît. Pour une suppression définitive, une confirmation supplémentaire est demandée avant de pouvoir appliquer l’action.',
            'tip' => 'La suppression définitive efface le manga, ses rattachements aux éditions et ses fiches de lecture associées.'
        ],
        [
            'target' => '.mangas-help-individual-actions',
            'title' => 'Modifier ou supprimer un seul manga',
            'text' => 'Modifier ouvre la fiche complète du manga, notamment ses informations, ses images et ses rattachements aux éditions. Supprimer efface définitivement ce manga et les données qui lui sont liées.',
            'tip' => 'Pour simplement retirer un manga d’une édition, utilisez le rattachement aux éditions plutôt que la suppression du manga.'
        ]
    ]
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/users_admin.php';
require_once __DIR__ . '/../includes/help.php';

requireAdmin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function userStatusLabel(string $status): string
{
    return match ($status) {
        'active' => 'Actif',
        'inactive' => 'Inactif',
        default => $status
    };
}

$pageTitle = 'Gestion des utilisateurs - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css'
];
$extraJs = [
    '/mangasan/public/assets/js/admin-users.js',
    '/mangasan/public/assets/js/help-system.js'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$filters = normalizeAdminUserFilters($_GET);
$search = (string) $filters['search'];
$roleId = $filters['role_id'];
$status = (string) $filters['status'];
$className = (string) $filters['class_name'];

$roles = getAdminUserRoles($pdo);
$classes = getAdminUserClasses($pdo);
$users = fetchAdminUsers($pdo, $filters);

$currentUserId = getCurrentUserId();
$returnQuery = buildAdminUsersQueryString($filters);
$printUrl = '/mangasan/admin/users_print.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading" id="usersHelpHeading">
                    <h1>Gestion des utilisateurs</h1>
                    <p>Crée, modifie, désactive et gère les comptes utilisateurs.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="<?php echo e($printUrl); ?>" class="btn btn-secondary" id="usersHelpPrint" target="_blank" rel="noopener">Imprimer la liste filtrée</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/user_edit.php" class="btn btn-primary" id="usersHelpCreate">Créer un utilisateur</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-panel" id="usersHelpFilters">
                <form method="get" action="/mangasan/admin/users.php" class="admin-filter-form">
                    <div class="admin-form-grid">
                        <div class="admin-field">
                            <label for="search">Recherche</label>
                            <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="Nom, prénom, pseudo, classe...">
                        </div>

                        <div class="admin-field">
                            <label for="role_id">Rôle</label>
                            <select id="role_id" name="role_id">
                                <option value="">Tous</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo (int) $role['id']; ?>" <?php echo $roleId === (int) $role['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($role['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="status">Statut</label>
                            <select id="status" name="status">
                                <option value="">Tous</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="class_name">Classe / groupe</label>
                            <select id="class_name" name="class_name">
                                <option value="">Toutes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo e($class['class_name']); ?>" <?php echo $className === (string) $class['class_name'] ? 'selected' : ''; ?>>
                                        <?php echo e($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="admin-filter-actions">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="/mangasan/admin/users.php" class="btn btn-secondary">Réinitialiser</a>
                        <span class="admin-filter-count"><?php echo count($users); ?> utilisateur(s) affiché(s)</span>
                    </div>
                </form>
            </div>

            <form method="post" action="/mangasan/actions/users_bulk_action.php" id="usersBulkForm" class="admin-users-bulk-form">
                <input type="hidden" name="return_query" value="<?php echo e($returnQuery); ?>">

                <div class="admin-panel admin-bulk-panel" id="usersHelpBulkPanel">
                    <div class="admin-bulk-head">
                        <div>
                            <strong>Actions groupées</strong>
                            <span id="selectedUsersCount" class="admin-cell-muted">0 utilisateur sélectionné</span>
                        </div>

                        <div class="admin-bulk-main-controls">
                            <select name="bulk_action" id="bulkActionSelect" aria-label="Action groupée">
                                <option value="">Choisir une action...</option>
                                <option value="deactivate">Désactiver</option>
                                <option value="activate">Réactiver</option>
                                <option value="reset_password">Réinitialiser le mot de passe</option>
                                <option value="update">Modification groupée</option>
                                <option value="delete">Supprimer</option>
                            </select>

                            <button type="submit" class="btn btn-primary" id="bulkApplyButton" data-bulk-submit="1" disabled>Appliquer</button>
                        </div>
                    </div>

                    <div class="admin-bulk-options is-hidden" id="bulkPasswordOptions">
                        <div class="admin-field">
                            <label for="bulk_password">Mot de passe temporaire commun</label>
                            <input type="password" id="bulk_password" name="bulk_password" minlength="6" autocomplete="new-password" placeholder="6 caractères minimum">
                        </div>

                        <label class="admin-inline-checkbox">
                            <input type="checkbox" name="bulk_force_change" value="1" checked>
                            <span>Forcer le changement du mot de passe à la prochaine connexion</span>
                        </label>
                    </div>

                    <div class="admin-bulk-options is-hidden" id="bulkUpdateOptions">
                        <div class="admin-form-grid">
                            <div class="admin-field">
                                <label for="bulk_role_id">Rôle</label>
                                <select id="bulk_role_id" name="bulk_role_id">
                                    <option value="">Ne pas modifier</option>
                                    <?php foreach ($roles as $role): ?>
                                        <?php if (in_array((string) $role['name'], ['member', 'admin'], true)): ?>
                                            <option value="<?php echo (int) $role['id']; ?>"><?php echo e($role['label']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="admin-field">
                                <label for="bulk_must_change_password">Changement de mot de passe obligatoire</label>
                                <select id="bulk_must_change_password" name="bulk_must_change_password">
                                    <option value="">Ne pas modifier</option>
                                    <option value="1">Oui</option>
                                    <option value="0">Non</option>
                                </select>
                            </div>
                        </div>

                        <div class="admin-bulk-class-row">
                            <label class="admin-inline-checkbox">
                                <input type="checkbox" name="bulk_update_class" id="bulk_update_class" value="1">
                                <span>Modifier la classe / le groupe</span>
                            </label>

                            <input type="text" name="bulk_class_name" id="bulk_class_name" placeholder="Nouvelle classe / groupe" disabled>
                        </div>
                    </div>

                    <div class="admin-bulk-warning is-hidden" id="bulkDeleteWarning">
                        <strong>Suppression sécurisée :</strong>
                        les comptes ayant déjà des fiches de lecture ne seront pas supprimés. Ils peuvent être désactivés pour conserver l’historique.
                    </div>
                </div>

                <div class="admin-panel" id="usersHelpTablePanel">
                    <div class="admin-table-wrapper">
                        <table class="admin-table admin-users-table">
                            <thead>
                                <tr>
                                    <th class="admin-selection-cell">
                                        <input type="checkbox" id="selectAllUsers" data-help-select-all="1" aria-label="Sélectionner tous les utilisateurs affichés">
                                    </th>
                                    <th>Utilisateur</th>
                                    <th>Identité</th>
                                    <th>Rôle</th>
                                    <th>Classe / groupe</th>
                                    <th>Statut</th>
                                    <th>Mot de passe</th>
                                    <th>Dernière connexion</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php $isSelf = $currentUserId === (int) $user['id']; ?>
                                    <tr>
                                        <td class="admin-selection-cell">
                                            <input
                                                type="checkbox"
                                                class="user-selection-checkbox"
                                                name="user_ids[]"
                                                value="<?php echo (int) $user['id']; ?>"
                                                aria-label="Sélectionner <?php echo e((string) $user['username']); ?>"
                                                <?php echo $isSelf ? 'disabled title="Le compte connecté est protégé des actions groupées"' : ''; ?>
                                            >
                                        </td>

                                        <td>
                                            <strong><?php echo e($user['username']); ?></strong>
                                            <?php if ($isSelf): ?>
                                                <br>
                                                <span class="admin-cell-muted">Compte connecté</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php echo e(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])); ?>
                                            <?php if (!empty($user['display_name'])): ?>
                                                <br>
                                                <span class="admin-cell-muted"><?php echo e($user['display_name']); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td><?php echo e($user['role_label']); ?></td>

                                        <td><?php echo !empty($user['class_name']) ? e($user['class_name']) : '—'; ?></td>

                                        <td>
                                            <span class="admin-badge <?php echo $user['status'] === 'active' ? 'is-visible' : 'is-hidden'; ?>">
                                                <?php echo e(userStatusLabel((string) $user['status'])); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php echo (int) $user['must_change_password'] === 1 ? 'Doit changer' : 'Normal'; ?>
                                        </td>

                                        <td>
                                            <?php echo !empty($user['last_login_at']) ? e((string) $user['last_login_at']) : 'Jamais'; ?>
                                        </td>

                                        <td>
                                            <div class="admin-actions-inline users-help-individual-actions">
                                                <a href="/mangasan/admin/user_edit.php?id=<?php echo (int) $user['id']; ?>" class="btn btn-primary">Modifier</a>

                                                <button
                                                    type="submit"
                                                    class="btn btn-secondary"
                                                    formaction="/mangasan/actions/user_toggle_status.php"
                                                    formmethod="post"
                                                    formnovalidate
                                                    name="user_id"
                                                    value="<?php echo (int) $user['id']; ?>"
                                                    <?php echo $isSelf ? 'disabled' : ''; ?>
                                                >
                                                    <?php echo $user['status'] === 'active' ? 'Désactiver' : 'Réactiver'; ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$users): ?>
                                    <tr>
                                        <td colspan="9">Aucun utilisateur trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </section>
</main>

<?php
renderAdminHelpGuide([
    'id' => 'admin-users',
    'title' => 'Guide — Gestion des utilisateurs',
    'steps' => [
        [
            'target' => '#usersHelpHeading',
            'title' => 'Gestion des utilisateurs',
            'text' => 'Cette page centralise les comptes Mangasan. Elle permet de rechercher un utilisateur, créer ou modifier un compte, gérer son statut et effectuer des actions sur plusieurs comptes à la fois.',
            'tip' => 'Le compte administrateur actuellement connecté est protégé contre les actions groupées sensibles.'
        ],
        [
            'target' => '#usersHelpFilters',
            'title' => 'Rechercher et filtrer les comptes',
            'text' => 'Utilisez la recherche ou les filtres Rôle, Statut et Classe / groupe pour réduire la liste. Le bouton Filtrer applique les critères et Réinitialiser revient à la liste complète.',
            'tip' => 'L’impression et les actions de la page utilisent ensuite la liste correspondant à ces filtres.'
        ],
        [
            'target' => '#usersHelpPrint',
            'title' => 'Imprimer la liste filtrée',
            'text' => 'Ce bouton ouvre une version imprimable de la liste actuellement affichée. Elle reprend les critères de recherche et de filtrage en cours.',
            'tip' => 'Les mots de passe ne sont jamais imprimés en clair : Mangasan ne conserve que leur empreinte sécurisée.'
        ],
        [
            'target' => '#usersHelpCreate',
            'title' => 'Créer un utilisateur',
            'text' => 'Ouvre le formulaire de création d’un compte. Vous pourrez renseigner son identifiant, son identité, sa classe ou son groupe, son rôle et son mot de passe temporaire.'
        ],
        [
            'target' => '#usersHelpTablePanel',
            'title' => 'Lire la liste des utilisateurs',
            'text' => 'Le tableau présente les comptes correspondant aux filtres : identifiant, identité, rôle, classe, statut, état du mot de passe et dernière connexion. Les boutons de la dernière colonne agissent sur un seul compte.'
        ],
        [
            'target' => '#selectAllUsers',
            'title' => 'Sélectionner plusieurs utilisateurs',
            'text' => 'Cochez cette case pour sélectionner tous les comptes actuellement affichés et disponibles pour les actions groupées. Vous pouvez aussi sélectionner les comptes un par un avec les cases de la première colonne.',
            'tip' => 'Votre propre compte administrateur n’est pas sélectionnable afin d’éviter une désactivation ou une suppression accidentelle.'
        ],
        [
            'target' => '#usersHelpBulkPanel',
            'title' => 'Actions groupées',
            'text' => 'Après avoir sélectionné un ou plusieurs comptes, choisissez ici l’action à appliquer : désactiver, réactiver, réinitialiser le mot de passe, modifier plusieurs comptes ou supprimer.',
            'tip' => 'Des options supplémentaires apparaissent automatiquement pour la réinitialisation des mots de passe et la modification groupée.'
        ],
        [
            'target' => '#bulkActionSelect',
            'title' => 'Choisir puis appliquer une action',
            'text' => 'Sélectionnez l’opération souhaitée dans cette liste. Le bouton Appliquer devient disponible dès qu’une action et au moins un utilisateur sont sélectionnés. Une confirmation est demandée pour les opérations sensibles.'
        ],
        [
            'target' => '.users-help-individual-actions',
            'title' => 'Actions sur un compte',
            'text' => 'Le bouton Modifier ouvre la fiche complète de l’utilisateur. Le second bouton permet de désactiver ou réactiver rapidement le compte sans le supprimer.',
            'tip' => 'Désactiver un compte conserve son historique et ses fiches de lecture.'
        ]
    ]
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

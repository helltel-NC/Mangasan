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

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditMode = $userId !== false && $userId !== null;

$rolesStmt = $pdo->query(
    "SELECT id, name, label
     FROM roles
     WHERE name IN ('member', 'admin')
     ORDER BY CASE WHEN name = 'member' THEN 1 WHEN name = 'admin' THEN 2 ELSE 3 END, label ASC"
);
$assignableRoles = $rolesStmt->fetchAll();

$defaultRoleId = null;
foreach ($assignableRoles as $role) {
    if ((string) $role['name'] === 'member') {
        $defaultRoleId = (int) $role['id'];
        break;
    }
}
if ($defaultRoleId === null && !empty($assignableRoles)) {
    $defaultRoleId = (int) $assignableRoles[0]['id'];
}

$user = [
    'id' => null,
    'role_id' => $defaultRoleId,
    'role_name' => 'member',
    'role_label' => 'Membre',
    'username' => '',
    'first_name' => '',
    'last_name' => '',
    'display_name' => '',
    'class_name' => '',
    'status' => 'active',
    'must_change_password' => 1,
    'created_at' => '',
    'updated_at' => '',
    'last_login_at' => ''
];

if ($isEditMode) {
    $stmt = $pdo->prepare(
        "SELECT
            users.*,
            roles.name AS role_name,
            roles.label AS role_label
         FROM users
         INNER JOIN roles ON roles.id = users.role_id
         WHERE users.id = :id
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $userId
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        setFlashMessage('error', 'Utilisateur introuvable.');
        header('Location: /mangasan/admin/users.php');
        exit;
    }

    $user = $row;
}

$pageTitle = $isEditMode ? 'Modifier un utilisateur - Mangasan' : 'Créer un utilisateur - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/admin.css',
    '/mangasan/public/assets/css/help-system.css'
];
$extraJs = [
    '/mangasan/public/assets/js/admin-user-edit.js',
    '/mangasan/public/assets/js/help-system.js'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$currentUserId = getCurrentUserId();
$isSelf = $isEditMode && $currentUserId === (int) $user['id'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page admin-user-edit-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar" id="userEditHelpHeading">
                <div class="admin-page-heading">
                    <h1><?php echo $isEditMode ? 'Modifier un utilisateur' : 'Créer un utilisateur'; ?></h1>
                    <p><?php echo $isEditMode ? 'Modifie le compte, l’identité et les accès de cet utilisateur.' : 'Crée un nouveau compte membre ou administrateur.'; ?></p>
                </div>

                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-secondary admin-help-launch" data-admin-help-open>Aide</button>
                    <a href="/mangasan/admin/users.php" class="btn btn-secondary">Retour utilisateurs</a>
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Dashboard</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-layout-two-columns admin-user-edit-layout">
                <div class="admin-panel admin-user-edit-form-panel">
                    <form
                        method="post"
                        action="<?php echo $isEditMode ? '/mangasan/actions/user_update.php' : '/mangasan/actions/user_create.php'; ?>"
                        class="admin-form"
                        id="userEditForm"
                    >
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                        <?php endif; ?>

                        <section class="admin-form-section admin-user-edit-section" id="userEditHelpAccount">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">1</span>
                                    <h2>Compte et accès</h2>
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="username">Nom d’utilisateur</label>
                                    <input type="text" id="username" name="username" value="<?php echo e((string) $user['username']); ?>" required autocomplete="off">
                                </div>

                                <div class="admin-field" id="userEditHelpRole">
                                    <label for="role_id">Rôle</label>
                                    <select id="role_id" name="role_id" required>
                                        <?php foreach ($assignableRoles as $role): ?>
                                            <option
                                                value="<?php echo (int) $role['id']; ?>"
                                                data-role-name="<?php echo e((string) $role['name']); ?>"
                                                <?php echo (int) $user['role_id'] === (int) $role['id'] ? 'selected' : ''; ?>
                                            >
                                                <?php echo e((string) $role['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status">
                                        <option value="active" <?php echo (string) $user['status'] === 'active' ? 'selected' : ''; ?>>Actif</option>
                                        <option value="inactive" <?php echo (string) $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                    </select>
                                </div>

                                <div class="admin-field" id="userEditHelpPasswordFlag">
                                    <label for="must_change_password">Changement de mot de passe demandé</label>
                                    <select id="must_change_password" name="must_change_password">
                                        <option value="1" <?php echo (int) $user['must_change_password'] === 1 ? 'selected' : ''; ?>>Oui</option>
                                        <option value="0" <?php echo (int) $user['must_change_password'] === 0 ? 'selected' : ''; ?>>Non</option>
                                    </select>
                                </div>
                            </div>

                            <?php if (!$isEditMode): ?>
                                <div class="admin-field" id="userEditHelpInitialPassword">
                                    <label for="password">Mot de passe temporaire</label>
                                    <input type="password" id="password" name="password" minlength="6" required autocomplete="new-password">
                                    <small class="admin-help-text">6 caractères minimum.</small>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="admin-form-section admin-user-edit-section" id="userEditHelpIdentity">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">2</span>
                                    <h2>Identité</h2>
                                </div>
                            </div>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="first_name">Prénom</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo e((string) $user['first_name']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="last_name">Nom</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo e((string) $user['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="admin-field">
                                <label for="display_name">Nom affiché <span class="admin-field-optional">(facultatif)</span></label>
                                <input type="text" id="display_name" name="display_name" value="<?php echo e((string) $user['display_name']); ?>">
                            </div>

                            <div class="admin-field" id="classNameField">
                                <label for="class_name">Classe / groupe</label>
                                <input type="text" id="class_name" name="class_name" value="<?php echo e((string) $user['class_name']); ?>">
                                <p class="admin-form-help">Obligatoire pour un membre.</p>
                            </div>
                        </section>

                        <div class="admin-form-actions admin-user-edit-form-actions" id="userEditHelpSave">
                            <button type="submit" class="btn btn-primary"><?php echo $isEditMode ? 'Enregistrer' : 'Créer'; ?></button>
                            <a href="/mangasan/admin/users.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>

                    <?php if ($isEditMode): ?>
                        <section class="admin-form-section admin-user-edit-section admin-user-password-section" id="userEditHelpPasswordReset">
                            <div class="admin-form-section-heading">
                                <div>
                                    <span class="admin-form-section-kicker">3</span>
                                    <h2>Réinitialiser le mot de passe</h2>
                                </div>
                            </div>

                            <form method="post" action="/mangasan/actions/user_reset_password.php" class="admin-form" id="userResetPasswordForm">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">

                                <div class="admin-form-grid">
                                    <div class="admin-field">
                                        <label for="new_password">Nouveau mot de passe temporaire</label>
                                        <input type="password" id="new_password" name="new_password" minlength="6" required autocomplete="new-password">
                                        <small class="admin-help-text">6 caractères minimum.</small>
                                    </div>

                                    <div class="admin-field">
                                        <label for="reset_must_change_password">Demander son changement</label>
                                        <select id="reset_must_change_password" name="must_change_password">
                                            <option value="1">Oui</option>
                                            <option value="0">Non</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="admin-form-actions">
                                    <button type="submit" class="btn btn-secondary">Réinitialiser le mot de passe</button>
                                </div>
                            </form>
                        </section>
                    <?php endif; ?>
                </div>

                <aside class="admin-preview-card admin-user-summary-card" id="userEditHelpSummary">
                    <div class="admin-preview-head">
                        <h2>Résumé</h2>
                    </div>

                    <div class="admin-preview-section">
                        <div class="admin-summary-item">
                            <span>Mode</span>
                            <strong><?php echo $isEditMode ? 'Modification' : 'Création'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Compte</span>
                            <strong id="userSummaryUsername"><?php echo e((string) $user['username']) !== '' ? e((string) $user['username']) : 'Nouveau compte'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Rôle</span>
                            <strong id="userSummaryRole"><?php echo e((string) $user['role_label']); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Statut</span>
                            <strong id="userSummaryStatus"><?php echo (string) $user['status'] === 'active' ? 'Actif' : 'Inactif'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Classe / groupe</span>
                            <strong id="userSummaryClass"><?php echo !empty($user['class_name']) ? e((string) $user['class_name']) : '—'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Mot de passe</span>
                            <strong id="userSummaryPasswordState"><?php echo (int) $user['must_change_password'] === 1 ? 'Changement demandé' : 'Personnel'; ?></strong>
                        </div>

                        <?php if ($isEditMode): ?>
                            <div class="admin-summary-item">
                                <span>Dernière connexion</span>
                                <strong><?php echo !empty($user['last_login_at']) ? e((string) $user['last_login_at']) : 'Jamais'; ?></strong>
                            </div>

                            <div class="admin-summary-item">
                                <span>Créé le</span>
                                <strong><?php echo e((string) $user['created_at']); ?></strong>
                            </div>

                            <div class="admin-summary-item">
                                <span>Mise à jour le</span>
                                <strong><?php echo e((string) $user['updated_at']); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($isSelf): ?>
                            <div class="alert error">
                                Ce compte est le tien : Mangasan interdit de le désactiver ou de lui retirer son rôle administrateur.
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php
$userEditHelpSteps = [
    [
        'target' => '#userEditHelpHeading',
        'title' => $isEditMode ? 'Modifier un utilisateur' : 'Créer un utilisateur',
        'text' => $isEditMode
            ? 'Cette page permet de modifier le compte, l’identité, le rôle et les accès de l’utilisateur. La réinitialisation de son mot de passe se trouve plus bas sur la même page.'
            : 'Cette page permet de créer un nouveau compte Mangasan et de définir dès le départ son rôle, son identité et son mot de passe temporaire.'
    ],
    [
        'target' => '#userEditHelpAccount',
        'title' => 'Compte et accès',
        'text' => 'Le nom d’utilisateur sert à la connexion. Le statut Actif autorise la connexion, tandis qu’un compte Inactif reste enregistré mais ne peut plus se connecter.'
    ],
    [
        'target' => '#userEditHelpRole',
        'title' => 'Choisir le rôle',
        'text' => 'Le rôle Membre correspond aux élèves ou utilisateurs ordinaires. Le rôle Administrateur donne accès à la console d’administration.',
        'tip' => 'La classe ou le groupe est obligatoire pour un membre et n’est pas utilisé pour un administrateur.'
    ],
    [
        'target' => '#userEditHelpPasswordFlag',
        'title' => 'Demander le changement du mot de passe',
        'text' => 'Si cette option est réglée sur Oui, Mangasan indique à l’utilisateur qu’il utilise encore un mot de passe temporaire et lui demande de le remplacer depuis son compte.',
        'tip' => 'Le site ne conserve jamais les mots de passe en clair : il enregistre uniquement leur hash sécurisé.'
    ],
];

if (!$isEditMode) {
    $userEditHelpSteps[] = [
        'target' => '#userEditHelpInitialPassword',
        'title' => 'Mot de passe temporaire',
        'text' => 'Saisissez le mot de passe initial communiqué à l’utilisateur. Il doit contenir au moins 6 caractères.',
        'tip' => 'Pour un compte élève, il est préférable de laisser le changement du mot de passe demandé afin qu’il choisisse ensuite son propre mot de passe.'
    ];
}

$userEditHelpSteps[] = [
    'target' => '#userEditHelpIdentity',
    'title' => 'Identité et classe',
    'text' => 'Renseignez le prénom et le nom de l’utilisateur. Le nom affiché est facultatif. Pour un membre, la classe ou le groupe est obligatoire et sert notamment aux recherches et filtres dans l’administration.'
];

$userEditHelpSteps[] = [
    'target' => '#userEditHelpSave',
    'title' => $isEditMode ? 'Enregistrer les modifications' : 'Créer le compte',
    'text' => $isEditMode
        ? 'Enregistrer applique les changements du compte et de l’identité. Annuler revient à la liste des utilisateurs sans enregistrer les modifications en cours.'
        : 'Créer enregistre le nouveau compte. Annuler revient à la liste des utilisateurs sans le créer.'
];

if ($isEditMode) {
    $userEditHelpSteps[] = [
        'target' => '#userEditHelpPasswordReset',
        'title' => 'Réinitialiser le mot de passe',
        'text' => 'Utilisez cette section lorsqu’un utilisateur a oublié son mot de passe ou qu’un nouveau mot de passe temporaire doit lui être attribué. Cette action ne modifie pas les autres informations du compte.',
        'tip' => 'Le nouveau mot de passe remplace immédiatement l’ancien. Vous pouvez demander à l’utilisateur de le changer ensuite.'
    ];
}

$userEditHelpSteps[] = [
    'target' => '#userEditHelpSummary',
    'title' => 'Résumé du compte',
    'text' => $isEditMode
        ? 'Ce panneau rappelle l’état du compte et se met à jour pendant la saisie pour le nom d’utilisateur, le rôle, le statut, la classe et l’état du mot de passe. Il affiche aussi les dates enregistrées et la dernière connexion.'
        : 'Ce panneau résume le compte en cours de création et se met à jour pendant la saisie.'
];

renderAdminHelpGuide([
    'id' => $isEditMode ? 'admin-user-edit' : 'admin-user-create',
    'title' => $isEditMode ? 'Guide — Modifier un utilisateur' : 'Guide — Créer un utilisateur',
    'steps' => $userEditHelpSteps
]);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

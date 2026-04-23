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
    '/mangasan/public/assets/css/admin.css'
];
$extraJs = [
    '/mangasan/public/assets/js/admin-users.js'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$currentUserId = getCurrentUserId();
$isSelf = $isEditMode && $currentUserId === (int) $user['id'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1><?php echo $isEditMode ? 'Modifier un utilisateur' : 'Créer un utilisateur'; ?></h1>
                    <p>Gestion des comptes membres et administrateurs.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/users.php" class="btn btn-secondary">Retour utilisateurs</a>
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
                    <form method="post" action="<?php echo $isEditMode ? '/mangasan/actions/user_update.php' : '/mangasan/actions/user_create.php'; ?>" class="admin-form">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                        <?php endif; ?>

                        <div class="admin-form-section">
                            <h2>Compte</h2>

                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="username">Nom d’utilisateur</label>
                                    <input type="text" id="username" name="username" value="<?php echo e((string) $user['username']); ?>" required>
                                </div>

                                <div class="admin-field">
                                    <label for="role_id">Rôle</label>
                                    <select id="role_id" name="role_id" required>
                                        <?php foreach ($assignableRoles as $role): ?>
                                            <option value="<?php echo (int) $role['id']; ?>" data-role-name="<?php echo e((string) $role['name']); ?>" <?php echo (int) $user['role_id'] === (int) $role['id'] ? 'selected' : ''; ?>>
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

                                <div class="admin-field">
                                    <label for="must_change_password">Changement de mot de passe obligatoire</label>
                                    <select id="must_change_password" name="must_change_password">
                                        <option value="1" <?php echo (int) $user['must_change_password'] === 1 ? 'selected' : ''; ?>>Oui</option>
                                        <option value="0" <?php echo (int) $user['must_change_password'] === 0 ? 'selected' : ''; ?>>Non</option>
                                    </select>
                                </div>
                            </div>

                            <?php if (!$isEditMode): ?>
                                <div class="admin-field">
                                    <label for="password">Mot de passe</label>
                                    <input type="password" id="password" name="password" required>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-form-section">
                            <h2>Identité</h2>

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
                                <label for="display_name">Nom affiché</label>
                                <input type="text" id="display_name" name="display_name" value="<?php echo e((string) $user['display_name']); ?>">
                            </div>

                            <div class="admin-field" id="classNameField">
                                <label for="class_name">Classe / groupe</label>
                                <input type="text" id="class_name" name="class_name" value="<?php echo e((string) $user['class_name']); ?>">
                                <p class="admin-form-help">Obligatoire pour un membre. Inutile pour un administrateur.</p>
                            </div>
                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo $isEditMode ? 'Enregistrer' : 'Créer'; ?></button>
                            <a href="/mangasan/admin/users.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>

                    <?php if ($isEditMode): ?>
                        <div class="admin-form-section">
                            <h2>Réinitialiser le mot de passe</h2>

                            <form method="post" action="/mangasan/actions/user_reset_password.php" class="admin-form">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">

                                <div class="admin-field">
                                    <label for="new_password">Nouveau mot de passe</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>

                                <div class="admin-field">
                                    <label for="reset_must_change_password">Forcer le changement de mot de passe</label>
                                    <select id="reset_must_change_password" name="must_change_password">
                                        <option value="1">Oui</option>
                                        <option value="0">Non</option>
                                    </select>
                                </div>

                                <div class="admin-form-actions">
                                    <button type="submit" class="btn btn-secondary">Réinitialiser le mot de passe</button>
                                </div>
                            </form>
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
                            <span>Rôle</span>
                            <strong><?php echo e((string) $user['role_label']); ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Statut</span>
                            <strong><?php echo (string) $user['status'] === 'active' ? 'Actif' : 'Inactif'; ?></strong>
                        </div>

                        <div class="admin-summary-item">
                            <span>Classe / groupe</span>
                            <strong><?php echo !empty($user['class_name']) ? e((string) $user['class_name']) : '—'; ?></strong>
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
                                Tu ne peux pas te désactiver ni te retirer ton propre rôle admin.
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
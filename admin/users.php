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
    '/mangasan/public/assets/css/admin.css'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

$search = trim((string) ($_GET['search'] ?? ''));
$roleId = filter_input(INPUT_GET, 'role_id', FILTER_VALIDATE_INT);
$status = trim((string) ($_GET['status'] ?? ''));
$className = trim((string) ($_GET['class_name'] ?? ''));

if (!in_array($status, ['', 'active', 'inactive'], true)) {
    $status = '';
}

$rolesStmt = $pdo->query(
    "SELECT id, name, label
     FROM roles
     ORDER BY label ASC, name ASC"
);
$roles = $rolesStmt->fetchAll();

$classesStmt = $pdo->query(
    "SELECT DISTINCT class_name
     FROM users
     WHERE class_name IS NOT NULL
       AND class_name <> ''
     ORDER BY class_name ASC"
);
$classes = $classesStmt->fetchAll();

$sql = "
    SELECT
        users.id,
        users.role_id,
        users.username,
        users.first_name,
        users.last_name,
        users.display_name,
        users.class_name,
        users.status,
        users.must_change_password,
        users.created_at,
        users.updated_at,
        users.last_login_at,
        roles.name AS role_name,
        roles.label AS role_label
    FROM users
    INNER JOIN roles ON roles.id = users.role_id
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            users.username LIKE :search
            OR users.first_name LIKE :search
            OR users.last_name LIKE :search
            OR users.display_name LIKE :search
            OR users.class_name LIKE :search
        )
    ";
    $params['search'] = '%' . $search . '%';
}

if ($roleId) {
    $sql .= " AND users.role_id = :role_id";
    $params['role_id'] = $roleId;
}

if ($status !== '') {
    $sql .= " AND users.status = :status";
    $params['status'] = $status;
}

if ($className !== '') {
    $sql .= " AND users.class_name = :class_name";
    $params['class_name'] = $className;
}

$sql .= " ORDER BY users.last_name ASC, users.first_name ASC, users.username ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$currentUserId = getCurrentUserId();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard-page">
    <section class="home-section">
        <div class="container">
            <div class="admin-toolbar">
                <div class="admin-page-heading">
                    <h1>Gestion des utilisateurs</h1>
                    <p>Crée, modifie, désactive et réinitialise les comptes utilisateurs.</p>
                </div>

                <div class="admin-toolbar-actions">
                    <a href="/mangasan/admin/index.php" class="btn btn-secondary">Retour dashboard</a>
                    <a href="/mangasan/admin/user_edit.php" class="btn btn-primary">Créer un utilisateur</a>
                </div>
            </div>

            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="admin-panel">
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
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
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
                                        <div class="admin-actions-inline">
                                            <a href="/mangasan/admin/user_edit.php?id=<?php echo (int) $user['id']; ?>" class="btn btn-primary">Modifier</a>

                                            <form method="post" action="/mangasan/actions/user_toggle_status.php">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                <button type="submit" class="btn btn-secondary" <?php echo $isSelf ? 'disabled' : ''; ?>>
                                                    <?php echo $user['status'] === 'active' ? 'Désactiver' : 'Réactiver'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$users): ?>
                                <tr>
                                    <td colspan="8">Aucun utilisateur trouvé.</td>
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
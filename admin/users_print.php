<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/users_admin.php';

requireAdmin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$filters = normalizeAdminUserFilters($_GET);
$users = fetchAdminUsers($pdo, $filters);
$roles = getAdminUserRoles($pdo);

$roleLabels = [];
foreach ($roles as $role) {
    $roleLabels[(int) $role['id']] = (string) $role['label'];
}

$filterLabels = [];
if (($filters['search'] ?? '') !== '') {
    $filterLabels[] = 'Recherche : ' . (string) $filters['search'];
}
if (!empty($filters['role_id'])) {
    $filterLabels[] = 'Rôle : ' . ($roleLabels[(int) $filters['role_id']] ?? ('#' . (int) $filters['role_id']));
}
if (($filters['status'] ?? '') !== '') {
    $filterLabels[] = 'Statut : ' . (($filters['status'] === 'active') ? 'Actif' : 'Inactif');
}
if (($filters['class_name'] ?? '') !== '') {
    $filterLabels[] = 'Classe / groupe : ' . (string) $filters['class_name'];
}

$returnQuery = buildAdminUsersQueryString($filters);
$returnUrl = '/mangasan/admin/users.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des utilisateurs - Mangasan</title>
    <style>
        :root {
            font-family: Arial, sans-serif;
            color: #111;
            background: #f3f3f3;
        }

        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #f3f3f3; }
        .print-sheet {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,.12);
        }
        .print-toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }
        .print-toolbar button,
        .print-toolbar a {
            border: 0;
            border-radius: 7px;
            padding: 10px 16px;
            background: #222;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .print-toolbar button { background: #c83b00; }
        .print-head {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 3px solid #111;
        }
        .print-head h1 { margin: 0 0 6px; font-size: 26px; }
        .print-head p { margin: 0; color: #555; }
        .print-meta { text-align: right; font-size: 12px; line-height: 1.5; }
        .print-filters {
            margin: 0 0 18px;
            padding: 12px 14px;
            border: 1px solid #bbb;
            background: #f8f8f8;
            font-size: 12px;
        }
        .print-note {
            margin: 0 0 16px;
            font-size: 12px;
            color: #444;
        }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #999; padding: 8px 9px; text-align: left; vertical-align: top; }
        th { background: #e9e9e9; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        tbody tr:nth-child(even) { background: #fafafa; }
        .username { font-weight: 700; font-size: 13px; }
        .muted { color: #666; }
        .status { font-weight: 700; }

        @page { size: A4 landscape; margin: 10mm; }
        @media print {
            :root, body { background: #fff; }
            body { padding: 0; }
            .print-sheet { max-width: none; padding: 0; box-shadow: none; }
            .print-toolbar { display: none !important; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <main class="print-sheet">
        <div class="print-toolbar">
            <a href="<?php echo e($returnUrl); ?>">Retour à la gestion</a>
            <button type="button" onclick="window.print()">Imprimer</button>
        </div>

        <header class="print-head">
            <div>
                <h1>Mangasan — Liste des utilisateurs</h1>
                <p><?php echo count($users); ?> utilisateur(s) dans la liste filtrée</p>
            </div>

            <div class="print-meta">
                <strong>Lycée Jules Garnier</strong><br>
                Généré le <?php echo e(date('d/m/Y à H:i')); ?>
            </div>
        </header>

        <?php if ($filterLabels): ?>
            <div class="print-filters">
                <strong>Filtres appliqués :</strong> <?php echo e(implode(' — ', $filterLabels)); ?>
            </div>
        <?php endif; ?>

        <p class="print-note">
            Cette liste contient les identifiants de connexion, mais jamais les mots de passe. Les mots de passe sont stockés sous forme de hash et ne sont pas récupérables en clair.
        </p>

        <table>
            <thead>
                <tr>
                    <th>Identifiant</th>
                    <th>Prénom</th>
                    <th>Nom</th>
                    <th>Nom affiché</th>
                    <th>Classe / groupe</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Mot de passe</th>
                    <th>Dernière connexion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="username"><?php echo e((string) $user['username']); ?></td>
                        <td><?php echo e((string) $user['first_name']); ?></td>
                        <td><?php echo e((string) $user['last_name']); ?></td>
                        <td><?php echo !empty($user['display_name']) ? e((string) $user['display_name']) : '—'; ?></td>
                        <td><?php echo !empty($user['class_name']) ? e((string) $user['class_name']) : '—'; ?></td>
                        <td><?php echo e((string) $user['role_label']); ?></td>
                        <td class="status"><?php echo (string) $user['status'] === 'active' ? 'Actif' : 'Inactif'; ?></td>
                        <td><?php echo (int) $user['must_change_password'] === 1 ? 'À changer' : 'Personnel'; ?></td>
                        <td><?php echo !empty($user['last_login_at']) ? e((string) $user['last_login_at']) : 'Jamais'; ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$users): ?>
                    <tr>
                        <td colspan="9">Aucun utilisateur ne correspond aux filtres.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>
</html>

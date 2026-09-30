<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

function redirectToUsersList(string $query = ''): never
{
    $query = str_replace(["\r", "\n"], '', $query);
    $location = '/mangasan/admin/users.php';

    if ($query !== '') {
        $location .= '?' . ltrim($query, '?');
    }

    header('Location: ' . $location);
    exit;
}

function normalizeSelectedUserIds(mixed $rawIds): array
{
    if (!is_array($rawIds)) {
        return [];
    }

    $ids = [];

    foreach ($rawIds as $rawId) {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return array_values($ids);
}

function buildInPlaceholders(array $ids, string $prefix): array
{
    $placeholders = [];
    $params = [];

    foreach (array_values($ids) as $index => $id) {
        $name = $prefix . $index;
        $placeholders[] = ':' . $name;
        $params[$name] = (int) $id;
    }

    return [$placeholders, $params];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToUsersList();
}

$returnQuery = trim((string) ($_POST['return_query'] ?? ''));
$action = trim((string) ($_POST['bulk_action'] ?? ''));
$userIds = normalizeSelectedUserIds($_POST['user_ids'] ?? []);
$currentUserId = getCurrentUserId();

$allowedActions = ['activate', 'deactivate', 'reset_password', 'update', 'delete'];

if (!in_array($action, $allowedActions, true)) {
    setFlashMessage('error', 'Action groupée invalide.');
    redirectToUsersList($returnQuery);
}

if (!$userIds) {
    setFlashMessage('error', 'Sélectionne au moins un utilisateur.');
    redirectToUsersList($returnQuery);
}

if (count($userIds) > 500) {
    setFlashMessage('error', 'La sélection est trop importante. Procède par groupes de 500 utilisateurs maximum.');
    redirectToUsersList($returnQuery);
}

// Protection supplémentaire : le compte administrateur connecté n'est jamais traité en masse.
if ($currentUserId !== null) {
    $userIds = array_values(array_filter(
        $userIds,
        static fn (int $id): bool => $id !== $currentUserId
    ));
}

if (!$userIds) {
    setFlashMessage('error', 'Aucun utilisateur modifiable dans la sélection.');
    redirectToUsersList($returnQuery);
}

[$placeholders, $idParams] = buildInPlaceholders($userIds, 'user_');
$selectedStmt = $pdo->prepare(
    "SELECT
        users.id,
        users.username,
        users.role_id,
        users.class_name,
        users.status,
        users.must_change_password,
        roles.name AS role_name,
        roles.label AS role_label
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id IN (" . implode(', ', $placeholders) . ")
     ORDER BY users.id ASC"
);
$selectedStmt->execute($idParams);
$selectedUsers = $selectedStmt->fetchAll();

if (!$selectedUsers) {
    setFlashMessage('error', 'Aucun utilisateur valide trouvé dans la sélection.');
    redirectToUsersList($returnQuery);
}

$actorId = getCurrentUserId();
$successCount = 0;
$skippedCount = 0;
$messages = [];

try {
    $pdo->beginTransaction();

    if ($action === 'activate' || $action === 'deactivate') {
        $newStatus = $action === 'activate' ? 'active' : 'inactive';
        $statusStmt = $pdo->prepare(
            "UPDATE users
             SET status = :status
             WHERE id = :id"
        );

        foreach ($selectedUsers as $user) {
            $statusStmt->execute([
                'status' => $newStatus,
                'id' => (int) $user['id'],
            ]);

            $successCount++;

            logAction(
                $pdo,
                $actorId,
                $newStatus === 'active' ? 'user_bulk_activate' : 'user_bulk_deactivate',
                'user',
                (int) $user['id'],
                ($newStatus === 'active' ? 'Réactivation' : 'Désactivation') . ' groupée de l’utilisateur "' . (string) $user['username'] . '".'
            );
        }
    }

    if ($action === 'reset_password') {
        $password = (string) ($_POST['bulk_password'] ?? '');
        $mustChangePassword = isset($_POST['bulk_force_change']) ? 1 : 0;

        if (mb_strlen($password) < 6) {
            throw new RuntimeException('Le mot de passe temporaire doit contenir au moins 6 caractères.');
        }

        $passwordStmt = $pdo->prepare(
            "UPDATE users
             SET password_hash = :password_hash,
                 must_change_password = :must_change_password
             WHERE id = :id"
        );

        foreach ($selectedUsers as $user) {
            // Un hash distinct par utilisateur, même si le mot de passe temporaire est commun.
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $passwordStmt->execute([
                'password_hash' => $passwordHash,
                'must_change_password' => $mustChangePassword,
                'id' => (int) $user['id'],
            ]);

            $successCount++;

            logAction(
                $pdo,
                $actorId,
                'user_bulk_reset_password',
                'user',
                (int) $user['id'],
                'Réinitialisation groupée du mot de passe de l’utilisateur "' . (string) $user['username'] . '".'
            );
        }
    }

    if ($action === 'update') {
        $bulkRoleId = filter_var($_POST['bulk_role_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $bulkMustChangeRaw = (string) ($_POST['bulk_must_change_password'] ?? '');
        $updateClass = isset($_POST['bulk_update_class']);
        $bulkClassName = trim((string) ($_POST['bulk_class_name'] ?? ''));

        if (!in_array($bulkMustChangeRaw, ['', '0', '1'], true)) {
            throw new RuntimeException('Valeur de changement de mot de passe invalide.');
        }

        $targetRole = null;

        if ($bulkRoleId) {
            $roleStmt = $pdo->prepare(
                "SELECT id, name, label
                 FROM roles
                 WHERE id = :id
                   AND name IN ('member', 'admin')
                 LIMIT 1"
            );
            $roleStmt->execute(['id' => (int) $bulkRoleId]);
            $targetRole = $roleStmt->fetch();

            if (!$targetRole) {
                throw new RuntimeException('Rôle groupé invalide.');
            }

            if ((string) $targetRole['name'] === 'member' && (!$updateClass || $bulkClassName === '')) {
                throw new RuntimeException('Pour appliquer le rôle Membre en groupe, renseigne également une classe / un groupe.');
            }
        }

        if (!$bulkRoleId && $bulkMustChangeRaw === '' && !$updateClass) {
            throw new RuntimeException('Aucune modification groupée n’a été définie.');
        }

        foreach ($selectedUsers as $user) {
            $fields = [];
            $params = ['id' => (int) $user['id']];
            $resultingRoleName = (string) $user['role_name'];

            if ($targetRole) {
                $fields[] = 'role_id = :role_id';
                $params['role_id'] = (int) $targetRole['id'];
                $resultingRoleName = (string) $targetRole['name'];
            }

            if ($bulkMustChangeRaw !== '') {
                $fields[] = 'must_change_password = :must_change_password';
                $params['must_change_password'] = (int) $bulkMustChangeRaw;
            }

            if ($resultingRoleName === 'admin') {
                // Même règle que l'édition individuelle : un administrateur n'a pas de classe.
                $fields[] = 'class_name = NULL';
            } elseif ($updateClass) {
                if ($bulkClassName === '') {
                    $skippedCount++;
                    continue;
                }

                $fields[] = 'class_name = :class_name';
                $params['class_name'] = $bulkClassName;
            }

            if (!$fields) {
                $skippedCount++;
                continue;
            }

            $updateStmt = $pdo->prepare(
                'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id'
            );
            $updateStmt->execute($params);
            $successCount++;

            logAction(
                $pdo,
                $actorId,
                'user_bulk_update',
                'user',
                (int) $user['id'],
                'Modification groupée de l’utilisateur "' . (string) $user['username'] . '".'
            );
        }
    }

    if ($action === 'delete') {
        [$reviewPlaceholders, $reviewParams] = buildInPlaceholders(
            array_map(static fn (array $user): int => (int) $user['id'], $selectedUsers),
            'review_user_'
        );

        $reviewStmt = $pdo->prepare(
            "SELECT user_id, COUNT(*) AS review_count
             FROM reviews
             WHERE user_id IN (" . implode(', ', $reviewPlaceholders) . ")
             GROUP BY user_id"
        );
        $reviewStmt->execute($reviewParams);

        $usersWithReviews = [];
        foreach ($reviewStmt->fetchAll() as $row) {
            $usersWithReviews[(int) $row['user_id']] = (int) $row['review_count'];
        }

        $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');

        foreach ($selectedUsers as $user) {
            $userId = (int) $user['id'];

            if (isset($usersWithReviews[$userId])) {
                $skippedCount++;
                continue;
            }

            $deleteStmt->execute(['id' => $userId]);

            if ($deleteStmt->rowCount() === 1) {
                $successCount++;

                logAction(
                    $pdo,
                    $actorId,
                    'user_bulk_delete',
                    'user',
                    $userId,
                    'Suppression groupée de l’utilisateur "' . (string) $user['username'] . '".'
                );
            } else {
                $skippedCount++;
            }
        }

        if ($skippedCount > 0) {
            $messages[] = $skippedCount . ' compte(s) conservé(s), notamment parce qu’ils possèdent déjà des fiches de lecture.';
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', $e->getMessage());
    redirectToUsersList($returnQuery);
}

if ($successCount > 0) {
    $message = $successCount . ' utilisateur(s) traité(s) avec succès.';
    if ($messages) {
        $message .= ' ' . implode(' ', $messages);
    }

    setFlashMessage('success', $message);
} else {
    $message = 'Aucun utilisateur n’a été modifié.';
    if ($messages) {
        $message .= ' ' . implode(' ', $messages);
    }

    setFlashMessage('error', $message);
}

redirectToUsersList($returnQuery);

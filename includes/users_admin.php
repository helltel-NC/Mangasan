<?php

declare(strict_types=1);

function normalizeAdminUserFilters(array $source): array
{
    $search = trim((string) ($source['search'] ?? ''));
    $status = trim((string) ($source['status'] ?? ''));
    $className = trim((string) ($source['class_name'] ?? ''));

    $roleId = filter_var($source['role_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if (!in_array($status, ['', 'active', 'inactive'], true)) {
        $status = '';
    }

    return [
        'search' => $search,
        'role_id' => $roleId ?: null,
        'status' => $status,
        'class_name' => $className,
    ];
}

function getAdminUserRoles(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, name, label
         FROM roles
         ORDER BY label ASC, name ASC"
    );

    return $stmt->fetchAll();
}

function getAdminUserClasses(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT DISTINCT class_name
         FROM users
         WHERE class_name IS NOT NULL
           AND class_name <> ''
         ORDER BY class_name ASC"
    );

    return $stmt->fetchAll();
}

function fetchAdminUsers(PDO $pdo, array $filters): array
{
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

    if (($filters['search'] ?? '') !== '') {
        $searchLike = '%' . $filters['search'] . '%';

        // Un placeholder par occurrence : PDO natif est utilisé dans Mangasan.
        $sql .= "
            AND (
                users.username LIKE :search_username
                OR users.first_name LIKE :search_first_name
                OR users.last_name LIKE :search_last_name
                OR users.display_name LIKE :search_display_name
                OR users.class_name LIKE :search_class_name
            )
        ";

        $params['search_username'] = $searchLike;
        $params['search_first_name'] = $searchLike;
        $params['search_last_name'] = $searchLike;
        $params['search_display_name'] = $searchLike;
        $params['search_class_name'] = $searchLike;
    }

    if (!empty($filters['role_id'])) {
        $sql .= " AND users.role_id = :role_id";
        $params['role_id'] = (int) $filters['role_id'];
    }

    if (($filters['status'] ?? '') !== '') {
        $sql .= " AND users.status = :status";
        $params['status'] = (string) $filters['status'];
    }

    if (($filters['class_name'] ?? '') !== '') {
        $sql .= " AND users.class_name = :class_name";
        $params['class_name'] = (string) $filters['class_name'];
    }

    $sql .= " ORDER BY users.last_name ASC, users.first_name ASC, users.username ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function buildAdminUsersQueryString(array $filters): string
{
    $query = [];

    foreach (['search', 'role_id', 'status', 'class_name'] as $key) {
        if (!array_key_exists($key, $filters)) {
            continue;
        }

        $value = $filters[$key];

        if ($value === null || $value === '') {
            continue;
        }

        $query[$key] = $value;
    }

    return http_build_query($query);
}

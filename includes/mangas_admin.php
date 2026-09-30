<?php

declare(strict_types=1);

function normalizeAdminMangaFilters(array $source): array
{
    $search = trim((string) ($source['search'] ?? ''));
    $status = trim((string) ($source['status'] ?? ''));
    $editionRaw = trim((string) ($source['edition'] ?? ''));

    if (!in_array($status, ['', 'active', 'inactive'], true)) {
        $status = '';
    }

    $edition = '';
    if ($editionRaw === 'none') {
        $edition = 'none';
    } elseif ($editionRaw !== '') {
        $editionId = filter_var($editionRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($editionId) {
            $edition = (string) $editionId;
        }
    }

    return [
        'search' => $search,
        'status' => $status,
        'edition' => $edition,
    ];
}

function getAdminMangaEditions(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, title, year, status, is_active
         FROM editions
         ORDER BY is_active DESC, year DESC, title ASC, id DESC"
    );

    return $stmt->fetchAll();
}

function fetchAdminMangas(PDO $pdo, array $filters): array
{
    $sql = "
        SELECT
            mangas.id,
            mangas.title,
            mangas.subtitle,
            mangas.author,
            mangas.illustrator,
            mangas.publisher,
            mangas.card_image,
            mangas.cover_image,
            mangas.status,
            mangas.updated_at,
            (
                SELECT COUNT(*)
                FROM edition_mangas
                WHERE edition_mangas.manga_id = mangas.id
            ) AS editions_count,
            (
                SELECT GROUP_CONCAT(
                    CONCAT(editions.title, ' ', editions.year)
                    ORDER BY editions.is_active DESC, editions.year DESC, editions.title ASC
                    SEPARATOR ' • '
                )
                FROM edition_mangas
                INNER JOIN editions ON editions.id = edition_mangas.edition_id
                WHERE edition_mangas.manga_id = mangas.id
            ) AS edition_names,
            (
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.manga_id = mangas.id
            ) AS reviews_count
        FROM mangas
        WHERE 1 = 1
    ";

    $params = [];

    if (($filters['search'] ?? '') !== '') {
        $searchLike = '%' . (string) $filters['search'] . '%';
        $sql .= "
            AND (
                mangas.title LIKE :search_title
                OR mangas.subtitle LIKE :search_subtitle
                OR mangas.author LIKE :search_author
                OR mangas.illustrator LIKE :search_illustrator
                OR mangas.publisher LIKE :search_publisher
            )
        ";
        $params['search_title'] = $searchLike;
        $params['search_subtitle'] = $searchLike;
        $params['search_author'] = $searchLike;
        $params['search_illustrator'] = $searchLike;
        $params['search_publisher'] = $searchLike;
    }

    if (($filters['status'] ?? '') !== '') {
        $sql .= " AND mangas.status = :status";
        $params['status'] = (string) $filters['status'];
    }

    $edition = (string) ($filters['edition'] ?? '');
    if ($edition === 'none') {
        $sql .= "
            AND NOT EXISTS (
                SELECT 1
                FROM edition_mangas em_none
                WHERE em_none.manga_id = mangas.id
            )
        ";
    } elseif ($edition !== '') {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM edition_mangas em_filter
                WHERE em_filter.manga_id = mangas.id
                  AND em_filter.edition_id = :edition_id
            )
        ";
        $params['edition_id'] = (int) $edition;
    }

    $sql .= " ORDER BY mangas.title ASC, mangas.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function buildAdminMangasQueryString(array $filters): string
{
    $query = [];

    foreach (['search', 'status', 'edition'] as $key) {
        $value = $filters[$key] ?? '';
        if ($value === '') {
            continue;
        }

        $query[$key] = $value;
    }

    return http_build_query($query);
}

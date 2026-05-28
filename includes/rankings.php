<?php

declare(strict_types=1);

function canDisplayEditionRanking(array $edition, bool $isLoggedIn): bool
{
    if ((string) ($edition['general_ranking_visibility'] ?? 'hidden') !== 'visible') {
        return false;
    }

    $access = (string) ($edition['general_ranking_access'] ?? 'members');

    if ($access === 'public') {
        return true;
    }

    return $isLoggedIn;
}

function getEditionRanking(PDO $pdo, int $editionId): array
{
    $editionStmt = $pdo->prepare(
        "SELECT ranking_calculation_method
         FROM editions
         WHERE id = :edition_id
         LIMIT 1"
    );

    $editionStmt->execute([
        'edition_id' => $editionId
    ]);

    $edition = $editionStmt->fetch();
    $method = (string) ($edition['ranking_calculation_method'] ?? 'average_score');

    if ($method === 'rank_points') {
        return getEditionRankingByRankPoints($pdo, $editionId);
    }

    return getEditionRankingByAverageScore($pdo, $editionId);
}

function getEditionRankingByAverageScore(PDO $pdo, int $editionId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            mangas.id,
            mangas.title,
            mangas.subtitle,
            mangas.card_image,
            mangas.cover_image,
            COUNT(reviews.id) AS reviews_count,
            ROUND(AVG(reviews.score), 2) AS average_score,
            NULL AS total_points,
            NULL AS average_rank,
            'average_score' AS ranking_method
         FROM edition_mangas
         INNER JOIN mangas ON mangas.id = edition_mangas.manga_id
         LEFT JOIN reviews
            ON reviews.edition_id = edition_mangas.edition_id
           AND reviews.manga_id = edition_mangas.manga_id
         WHERE edition_mangas.edition_id = :edition_id
           AND edition_mangas.is_visible = 1
           AND mangas.status = 'active'
         GROUP BY
            mangas.id,
            mangas.title,
            mangas.subtitle,
            mangas.card_image,
            mangas.cover_image,
            edition_mangas.display_order
         ORDER BY
            CASE WHEN COUNT(reviews.id) > 0 THEN 0 ELSE 1 END ASC,
            AVG(reviews.score) DESC,
            COUNT(reviews.id) DESC,
            mangas.title ASC"
    );

    $stmt->execute([
        'edition_id' => $editionId
    ]);

    return normalizeRankingRows($stmt->fetchAll());
}

function getEditionRankingByRankPoints(PDO $pdo, int $editionId): array
{
    $totalMangasStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_mangas
         FROM edition_mangas
         INNER JOIN mangas ON mangas.id = edition_mangas.manga_id
         WHERE edition_mangas.edition_id = :edition_id
           AND edition_mangas.is_visible = 1
           AND mangas.status = 'active'"
    );

    $totalMangasStmt->execute([
        'edition_id' => $editionId
    ]);

    $totalMangas = (int) ($totalMangasStmt->fetch()['total_mangas'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT
            mangas.id,
            mangas.title,
            mangas.subtitle,
            mangas.card_image,
            mangas.cover_image,
            COUNT(reviews.id) AS reviews_count,
            ROUND(AVG(reviews.score), 2) AS average_score,
            SUM(
                CASE
                    WHEN reviews.id IS NOT NULL
                     AND reviews.personal_rank >= 1
                     AND reviews.personal_rank <= :total_mangas_limit_1
                    THEN (:total_mangas_points - reviews.personal_rank + 1)
                    ELSE 0
                END
            ) AS total_points,
            ROUND(AVG(
                CASE
                    WHEN reviews.id IS NOT NULL
                     AND reviews.personal_rank >= 1
                     AND reviews.personal_rank <= :total_mangas_limit_2
                    THEN reviews.personal_rank
                    ELSE NULL
                END
            ), 2) AS average_rank,
            'rank_points' AS ranking_method
         FROM edition_mangas
         INNER JOIN mangas ON mangas.id = edition_mangas.manga_id
         LEFT JOIN reviews
            ON reviews.edition_id = edition_mangas.edition_id
           AND reviews.manga_id = edition_mangas.manga_id
         WHERE edition_mangas.edition_id = :edition_id
           AND edition_mangas.is_visible = 1
           AND mangas.status = 'active'
         GROUP BY
            mangas.id,
            mangas.title,
            mangas.subtitle,
            mangas.card_image,
            mangas.cover_image,
            edition_mangas.display_order
         ORDER BY
            CASE WHEN COUNT(reviews.id) > 0 THEN 0 ELSE 1 END ASC,
            total_points DESC,
            average_rank ASC,
            COUNT(reviews.id) DESC,
            mangas.title ASC"
    );

    $stmt->execute([
        'edition_id' => $editionId,
        'total_mangas_limit_1' => $totalMangas,
        'total_mangas_points' => $totalMangas,
        'total_mangas_limit_2' => $totalMangas
    ]);

    $rows = normalizeRankingRows($stmt->fetchAll());

    foreach ($rows as &$row) {
        $row['total_mangas'] = $totalMangas;
    }

    unset($row);

    return $rows;
}

function normalizeRankingRows(array $rows): array
{
    $rank = 0;

    foreach ($rows as &$row) {
        $row['reviews_count'] = (int) ($row['reviews_count'] ?? 0);
        $row['average_score'] = $row['average_score'] !== null ? (float) $row['average_score'] : null;
        $row['total_points'] = $row['total_points'] !== null ? (int) $row['total_points'] : null;
        $row['average_rank'] = $row['average_rank'] !== null ? (float) $row['average_rank'] : null;
        $row['ranking_method'] = (string) ($row['ranking_method'] ?? 'average_score');

        if ($row['reviews_count'] > 0) {
            $rank++;
            $row['display_rank'] = $rank;
        } else {
            $row['display_rank'] = null;
        }
    }

    unset($row);

    return $rows;
}

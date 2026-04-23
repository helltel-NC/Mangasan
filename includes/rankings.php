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
    $stmt = $pdo->prepare(
        "SELECT
            mangas.id,
            mangas.title,
            mangas.subtitle,
            mangas.card_image,
            mangas.cover_image,
            COUNT(reviews.id) AS reviews_count,
            ROUND(AVG(reviews.score), 2) AS average_score
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

    $rows = $stmt->fetchAll();

    $rank = 0;

    foreach ($rows as &$row) {
        $row['reviews_count'] = (int) $row['reviews_count'];
        $row['average_score'] = $row['average_score'] !== null ? (float) $row['average_score'] : null;

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
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

header('Content-Type: application/json; charset=utf-8');
requireAdmin();

function layoutRestoreResponse(bool $ok, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    layoutRestoreResponse(false, 'Méthode non autorisée.', [], 405);
}

if (!pageLayoutTableAvailable($pdo)) {
    layoutRestoreResponse(false, 'La table page_layouts est absente. Exécutez la migration SQL de l’éditeur.', [], 409);
}

try {
    $userId = getCurrentUserId();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT published_layout, previous_layout
         FROM page_layouts
         WHERE page_key = 'home'
         FOR UPDATE"
    );
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row || empty($row['previous_layout'])) {
        $pdo->rollBack();
        layoutRestoreResponse(false, 'Aucune disposition précédente n’est disponible.', [], 409);
    }

    $restored = (string) $row['previous_layout'];
    $currentPublished = $row['published_layout'] ?? null;

    $stmt = $pdo->prepare(
        "UPDATE page_layouts
         SET published_layout = :restored_layout,
             draft_layout = :restored_layout,
             previous_layout = :previous_layout,
             updated_by = :updated_by,
             published_by = :published_by,
             published_at = CURRENT_TIMESTAMP,
             updated_at = CURRENT_TIMESTAMP
         WHERE page_key = 'home'"
    );
    $stmt->execute([
        'restored_layout' => $restored,
        'previous_layout' => $currentPublished,
        'updated_by' => $userId,
        'published_by' => $userId,
    ]);

    $pdo->commit();

    layoutRestoreResponse(true, 'Disposition précédente restaurée.', [
        'layout' => decodePageLayout($restored),
        'has_previous' => $currentPublished !== null && trim((string) $currentPublished) !== '',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    layoutRestoreResponse(false, 'Impossible de restaurer la disposition précédente.', [], 500);
}

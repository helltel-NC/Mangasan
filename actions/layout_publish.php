<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

header('Content-Type: application/json; charset=utf-8');
requireAdmin();

function layoutPublishResponse(bool $ok, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    layoutPublishResponse(false, 'Méthode non autorisée.', [], 405);
}

if (!pageLayoutTableAvailable($pdo)) {
    layoutPublishResponse(false, 'La table page_layouts est absente. Exécutez la migration SQL de l’éditeur.', [], 409);
}

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload) || !isset($payload['layout']) || !is_array($payload['layout'])) {
    layoutPublishResponse(false, 'Disposition invalide.', [], 422);
}

try {
    $layoutJson = encodeLayoutForDatabase($payload['layout']);
    $userId = getCurrentUserId();

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT published_layout FROM page_layouts WHERE page_key = 'home' FOR UPDATE");
    $stmt->execute();
    $row = $stmt->fetch();
    $previousPublished = $row ? ($row['published_layout'] ?? null) : null;

    if ($row) {
        $stmt = $pdo->prepare(
            "UPDATE page_layouts
             SET previous_layout = :previous_layout,
                 draft_layout = :draft_layout,
                 published_layout = :published_layout,
                 updated_by = :updated_by,
                 published_by = :published_by,
                 published_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE page_key = 'home'"
        );
        $stmt->execute([
            'previous_layout' => $previousPublished,
            'draft_layout' => $layoutJson,
            'published_layout' => $layoutJson,
            'updated_by' => $userId,
            'published_by' => $userId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO page_layouts (
                page_key, draft_layout, published_layout, previous_layout,
                updated_by, published_by, published_at
             ) VALUES (
                'home', :draft_layout, :published_layout, NULL,
                :updated_by, :published_by, CURRENT_TIMESTAMP
             )"
        );
        $stmt->execute([
            'draft_layout' => $layoutJson,
            'published_layout' => $layoutJson,
            'updated_by' => $userId,
            'published_by' => $userId,
        ]);
    }

    $pdo->commit();

    layoutPublishResponse(true, 'Disposition publiée sur le site public.', [
        'layout' => json_decode($layoutJson, true),
        'has_previous' => $previousPublished !== null && trim((string) $previousPublished) !== '',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    layoutPublishResponse(false, 'Impossible de publier la disposition.', [], 500);
}

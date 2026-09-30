<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

header('Content-Type: application/json; charset=utf-8');
requireAdmin();

function layoutJsonResponse(bool $ok, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    layoutJsonResponse(false, 'Méthode non autorisée.', [], 405);
}

if (!pageLayoutTableAvailable($pdo)) {
    layoutJsonResponse(false, 'La table page_layouts est absente. Exécutez la migration SQL de l’éditeur.', [], 409);
}

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload) || !isset($payload['layout']) || !is_array($payload['layout'])) {
    layoutJsonResponse(false, 'Disposition invalide.', [], 422);
}

try {
    $layoutJson = encodeLayoutForDatabase($payload['layout']);

    $stmt = $pdo->prepare(
        "INSERT INTO page_layouts (page_key, draft_layout, updated_by)
         VALUES ('home', :draft_layout, :updated_by)
         ON DUPLICATE KEY UPDATE
             draft_layout = VALUES(draft_layout),
             updated_by = VALUES(updated_by),
             updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        'draft_layout' => $layoutJson,
        'updated_by' => getCurrentUserId(),
    ]);

    layoutJsonResponse(true, 'Brouillon enregistré.', [
        'layout' => json_decode($layoutJson, true),
    ]);
} catch (Throwable $e) {
    layoutJsonResponse(false, 'Impossible d’enregistrer le brouillon.', [], 500);
}

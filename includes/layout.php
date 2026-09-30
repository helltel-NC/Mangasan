<?php

declare(strict_types=1);

/**
 * Gestion de la mise en page visuelle de la page d'accueil.
 *
 * Le stockage est volontairement découplé des sections :
 * - site_sections décide quelles sections existent et sont visibles ;
 * - page_layouts décide uniquement où et comment les blocs visibles sont placés.
 */

function pageLayoutTableAvailable(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'page_layouts'");
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function fetchPageLayoutRow(PDO $pdo, string $pageKey = 'home'): ?array
{
    if (!pageLayoutTableAvailable($pdo)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, page_key, draft_layout, published_layout, previous_layout,
                    updated_at, updated_by, published_at, published_by
             FROM page_layouts
             WHERE page_key = :page_key
             LIMIT 1'
        );
        $stmt->execute(['page_key' => $pageKey]);
        $row = $stmt->fetch();

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function decodePageLayout(?string $json): ?array
{
    $json = trim((string) $json);

    if ($json === '') {
        return null;
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : null;
}

function clampLayoutInt(mixed $value, int $min, int $max, int $default): int
{
    if (!is_numeric($value)) {
        return $default;
    }

    $value = (int) $value;

    return max($min, min($max, $value));
}

function normalizeHomeLayoutPayload(array $layout): array
{
    $normalized = [
        'version' => 1,
        'gap' => clampLayoutInt($layout['gap'] ?? 18, 0, 48, 18),
        'blocks' => [],
        'components' => [],
    ];

    $blocks = isset($layout['blocks']) && is_array($layout['blocks'])
        ? $layout['blocks']
        : [];

    $blockCount = 0;

    foreach ($blocks as $key => $block) {
        if ($blockCount >= 50 || !is_string($key) || !is_array($block)) {
            continue;
        }

        if (!preg_match('/^(hero|ranking|section:\\d+)$/', $key)) {
            continue;
        }

        $x = clampLayoutInt($block['x'] ?? 1, 1, 12, 1);
        $w = clampLayoutInt($block['w'] ?? 12, 1, 12, 12);

        if ($x + $w - 1 > 12) {
            $w = 13 - $x;
        }

        $normalized['blocks'][$key] = [
            'x' => $x,
            'w' => max(1, $w),
            'order' => clampLayoutInt($block['order'] ?? (($blockCount + 1) * 10), 1, 10000, ($blockCount + 1) * 10),
            'minHeight' => clampLayoutInt($block['minHeight'] ?? 0, 0, 1600, 0),
        ];

        $blockCount++;
    }

    $components = isset($layout['components']) && is_array($layout['components'])
        ? $layout['components']
        : [];

    if (isset($components['hero_login']) && is_array($components['hero_login'])) {
        $component = $components['hero_login'];
        $x = clampLayoutInt($component['x'] ?? 9, 1, 12, 9);
        $w = clampLayoutInt($component['w'] ?? 4, 2, 12, 4);

        if ($x + $w - 1 > 12) {
            $w = max(2, 13 - $x);
            if ($x + $w - 1 > 12) {
                $x = max(1, 13 - $w);
            }
        }

        $h = clampLayoutInt($component['h'] ?? 4, 2, 8, 4);
        $y = clampLayoutInt($component['y'] ?? 1, 1, 8, 1);

        if ($y + $h - 1 > 8) {
            $h = max(2, 9 - $y);
            if ($y + $h - 1 > 8) {
                $y = max(1, 9 - $h);
            }
        }

        $normalized['components']['hero_login'] = [
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
        ];
    }

    return $normalized;
}

function buildDefaultHomeLayout(array $blockDefinitions, string $heroLoginPosition = 'right'): array
{
    $layout = [
        'version' => 1,
        'gap' => 18,
        'blocks' => [],
        'components' => [
            'hero_login' => [
                'x' => $heroLoginPosition === 'left' ? 1 : 9,
                'y' => 1,
                'w' => 4,
                'h' => 4,
            ],
        ],
    ];

    $order = 10;

    foreach ($blockDefinitions as $definition) {
        if (!is_array($definition) || empty($definition['key'])) {
            continue;
        }

        $key = (string) $definition['key'];
        $x = clampLayoutInt($definition['x'] ?? 1, 1, 12, 1);
        $w = clampLayoutInt($definition['w'] ?? 12, 1, 12, 12);

        if ($x + $w - 1 > 12) {
            $w = 13 - $x;
        }

        $layout['blocks'][$key] = [
            'x' => $x,
            'w' => max(1, $w),
            'order' => $order,
            'minHeight' => clampLayoutInt($definition['minHeight'] ?? 0, 0, 1600, 0),
        ];

        $order += 10;
    }

    return normalizeHomeLayoutPayload($layout);
}

function mergeHomeLayoutWithDefinitions(array $layout, array $blockDefinitions, string $heroLoginPosition = 'right'): array
{
    $layout = normalizeHomeLayoutPayload($layout);
    $defaults = buildDefaultHomeLayout($blockDefinitions, $heroLoginPosition);
    $savedBlocks = $layout['blocks'];
    $mergedBlocks = [];
    $maxOrder = 0;

    foreach ($savedBlocks as $block) {
        $maxOrder = max($maxOrder, (int) ($block['order'] ?? 0));
    }

    $hasSavedBlocks = $savedBlocks !== [];

    foreach ($blockDefinitions as $definition) {
        if (!is_array($definition) || empty($definition['key'])) {
            continue;
        }

        $key = (string) $definition['key'];

        if (isset($savedBlocks[$key])) {
            $mergedBlocks[$key] = $savedBlocks[$key];
            continue;
        }

        $block = $defaults['blocks'][$key] ?? ['x' => 1, 'w' => 12, 'order' => 10, 'minHeight' => 0];

        if ($hasSavedBlocks) {
            $maxOrder += 10;
            $block['order'] = $maxOrder;
        }

        $mergedBlocks[$key] = $block;
    }

    // Les sections masquées ou supprimées ne restent pas dans le rendu effectif.
    $layout['blocks'] = $mergedBlocks;

    if (empty($layout['components']['hero_login'])) {
        $layout['components']['hero_login'] = $defaults['components']['hero_login'];
    }

    return normalizeHomeLayoutPayload($layout);
}

function getHomeLayoutState(
    PDO $pdo,
    array $blockDefinitions,
    string $heroLoginPosition = 'right',
    bool $editorMode = false
): array {
    $default = buildDefaultHomeLayout($blockDefinitions, $heroLoginPosition);
    $storageReady = pageLayoutTableAvailable($pdo);
    $row = $storageReady ? fetchPageLayoutRow($pdo, 'home') : null;

    $published = $row ? decodePageLayout($row['published_layout'] ?? null) : null;
    $draft = $row ? decodePageLayout($row['draft_layout'] ?? null) : null;
    $previous = $row ? decodePageLayout($row['previous_layout'] ?? null) : null;

    $source = $editorMode
        ? ($draft ?? $published ?? $default)
        : ($published ?? $default);

    return [
        'layout' => mergeHomeLayoutWithDefinitions($source, $blockDefinitions, $heroLoginPosition),
        'default' => $default,
        'has_published' => $published !== null,
        'has_draft' => $draft !== null,
        'has_previous' => $previous !== null,
        'storage_ready' => $storageReady,
        'updated_at' => $row['updated_at'] ?? null,
        'published_at' => $row['published_at'] ?? null,
    ];
}

function homeLayoutBlockStyle(array $layout, string $key): string
{
    $block = $layout['blocks'][$key] ?? ['x' => 1, 'w' => 12, 'order' => 10, 'minHeight' => 0];

    return sprintf(
        '--layout-x:%d;--layout-w:%d;--layout-order:%d;--layout-min-height:%dpx;',
        (int) ($block['x'] ?? 1),
        (int) ($block['w'] ?? 12),
        (int) ($block['order'] ?? 10),
        (int) ($block['minHeight'] ?? 0)
    );
}

function heroLoginLayoutStyle(array $layout): string
{
    $component = $layout['components']['hero_login'] ?? ['x' => 9, 'y' => 1, 'w' => 4, 'h' => 4];

    return sprintf(
        '--hero-login-x:%d;--hero-login-y:%d;--hero-login-w:%d;--hero-login-h:%d;',
        (int) ($component['x'] ?? 9),
        (int) ($component['y'] ?? 1),
        (int) ($component['w'] ?? 4),
        (int) ($component['h'] ?? 4)
    );
}

function encodeLayoutForDatabase(array $layout): string
{
    return json_encode(
        normalizeHomeLayoutPayload($layout),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}

<?php

declare(strict_types=1);

function normalizeThemeColor(?string $value, string $default): string
{
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
        ? strtolower($value)
        : $default;
}

function getSiteThemeSettings(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT
            site_title,
            site_title_type,
            primary_color,
            secondary_color,
            background_color,
            text_color,
            accent_color,
            logo_path,
            hero_background_type,
            hero_background_value,
            homepage_intro,
            hero_text_color,
            hero_login_position
         FROM site_settings
         ORDER BY id ASC
         LIMIT 1'
    );

    $settings = $stmt->fetch() ?: [];

    return [
        'site_title' => trim((string) ($settings['site_title'] ?? 'Mangasan')) ?: 'Mangasan',
        'site_title_type' => (string) ($settings['site_title_type'] ?? 'text'),
        'primary_color' => normalizeThemeColor($settings['primary_color'] ?? null, '#e50914'),
        'secondary_color' => normalizeThemeColor($settings['secondary_color'] ?? null, '#333333'),
        'background_color' => normalizeThemeColor($settings['background_color'] ?? null, '#0f0f0f'),
        'text_color' => normalizeThemeColor($settings['text_color'] ?? null, '#ffffff'),
        'accent_color' => normalizeThemeColor($settings['accent_color'] ?? null, '#c1121f'),
        'hero_text_color' => normalizeThemeColor($settings['hero_text_color'] ?? null, '#ffffff'),
        'logo_path' => trim((string) ($settings['logo_path'] ?? '')),
        'hero_background_type' => (string) ($settings['hero_background_type'] ?? 'image'),
        'hero_background_value' => trim((string) ($settings['hero_background_value'] ?? '')),
        'homepage_intro' => trim((string) ($settings['homepage_intro'] ?? '')),
        'hero_login_position' => in_array(($settings['hero_login_position'] ?? 'right'), ['left', 'right'], true)
            ? (string) $settings['hero_login_position']
            : 'right'
    ];
}

function buildThemeStyleTag(array $theme): string
{
    $primaryColor = htmlspecialchars($theme['primary_color'], ENT_QUOTES, 'UTF-8');
    $secondaryColor = htmlspecialchars($theme['secondary_color'], ENT_QUOTES, 'UTF-8');
    $backgroundColor = htmlspecialchars($theme['background_color'], ENT_QUOTES, 'UTF-8');
    $textColor = htmlspecialchars($theme['text_color'], ENT_QUOTES, 'UTF-8');
    $accentColor = htmlspecialchars($theme['accent_color'], ENT_QUOTES, 'UTF-8');
    $heroTextColor = htmlspecialchars($theme['hero_text_color'], ENT_QUOTES, 'UTF-8');

    return <<<HTML
<style>
    :root {
        --color-primary: {$primaryColor};
        --color-primary-hover: {$primaryColor};
        --color-secondary: {$secondaryColor};
        --color-background: {$backgroundColor};
        --color-text: {$textColor};
        --color-accent: {$accentColor};
    }

    .hero-title,
    .hero-description {
        color: {$heroTextColor};
    }
</style>
HTML;
}
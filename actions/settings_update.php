<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/upload.php';

requireAdmin();

function normalizeColorValue(?string $value, string $fieldName): ?string
{
    $color = trim((string) $value);

    if ($color === '') {
        return null;
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        throw new RuntimeException('Couleur invalide pour "' . $fieldName . '".');
    }

    return strtolower($color);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/setting.php');
    exit;
}

$settingsId = filter_input(INPUT_POST, 'settings_id', FILTER_VALIDATE_INT);

if (!$settingsId) {
    setFlashMessage('error', 'Paramètres invalides.');
    header('Location: /mangasan/admin/setting.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM site_settings WHERE id = :id LIMIT 1');
$stmt->execute([
    'id' => $settingsId
]);

$currentSettings = $stmt->fetch();

if (!$currentSettings) {
    setFlashMessage('error', 'Paramètres introuvables.');
    header('Location: /mangasan/admin/setting.php');
    exit;
}

$siteTitle = trim((string) ($_POST['site_title'] ?? ''));
$siteTitleType = trim((string) ($_POST['site_title_type'] ?? 'text'));
$primaryColor = null;
$secondaryColor = null;
$backgroundColor = null;
$textColor = null;
$accentColor = null;
$heroTextColor = null;
$logoPath = trim((string) ($_POST['logo_path'] ?? ''));
$heroBackgroundType = trim((string) ($_POST['hero_background_type'] ?? 'image'));
$heroBackgroundValue = trim((string) ($_POST['hero_background_value'] ?? ''));
$homepageIntro = trim((string) ($_POST['homepage_intro'] ?? ''));
$heroLoginPosition = trim((string) ($_POST['hero_login_position'] ?? 'right'));

if ($siteTitle === '') {
    setFlashMessage('error', 'Le titre du site est obligatoire.');
    header('Location: /mangasan/admin/setting.php');
    exit;
}

if (!in_array($siteTitleType, ['text', 'image', 'text_image', 'none'], true)) {
    setFlashMessage('error', 'Type de titre invalide.');
    header('Location: /mangasan/admin/setting.php');
    exit;
}

if (!in_array($heroBackgroundType, ['color', 'image', 'video'], true)) {
    setFlashMessage('error', 'Type de fond hero invalide.');
    header('Location: /mangasan/admin/setting.php');
    exit;
}

if (!in_array($heroLoginPosition, ['left', 'right'], true)) {
    setFlashMessage('error', 'Position du formulaire invalide.');
    header('Location: /mangasan/admin/setting.php');
    exit;
}

try {
    $primaryColor = normalizeColorValue($_POST['primary_color'] ?? null, 'primary_color');
    $secondaryColor = normalizeColorValue($_POST['secondary_color'] ?? null, 'secondary_color');
    $backgroundColor = normalizeColorValue($_POST['background_color'] ?? null, 'background_color');
    $textColor = normalizeColorValue($_POST['text_color'] ?? null, 'text_color');
    $accentColor = normalizeColorValue($_POST['accent_color'] ?? null, 'accent_color');
    $heroTextColor = normalizeColorValue($_POST['hero_text_color'] ?? null, 'hero_text_color');

    $currentLogoPath = $currentSettings['logo_path'] ?? null;
    $currentHeroBackgroundType = (string) $currentSettings['hero_background_type'];
    $currentHeroBackgroundValue = $currentSettings['hero_background_value'] ?? null;

    $newLogoPath = $logoPath !== '' ? $logoPath : null;
    $newHeroBackgroundValue = $heroBackgroundValue !== '' ? $heroBackgroundValue : null;

    $hasLogoUpload = isset($_FILES['logo_file']) && (int) ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasHeroUpload = isset($_FILES['hero_background_file']) && (int) ($_FILES['hero_background_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasLogoUpload) {
        $uploadedLogoPath = saveUploadedImageAsWebp($_FILES['logo_file'], 'settings');
        $newLogoPath = $uploadedLogoPath;

        if ($currentLogoPath !== null && $currentLogoPath !== $uploadedLogoPath) {
            deleteManagedUpload($currentLogoPath);
        }
    }

    if ($heroBackgroundType === 'color') {
        $newHeroBackgroundValue = null;

        if ($currentHeroBackgroundType === 'image') {
            deleteManagedUpload($currentHeroBackgroundValue);
        }
    }

    if ($heroBackgroundType === 'image') {
        if ($hasHeroUpload) {
            $uploadedHeroPath = saveUploadedImageAsWebp($_FILES['hero_background_file'], 'settings');
            $newHeroBackgroundValue = $uploadedHeroPath;

            if ($currentHeroBackgroundType === 'image' && $currentHeroBackgroundValue !== null && $currentHeroBackgroundValue !== $uploadedHeroPath) {
                deleteManagedUpload($currentHeroBackgroundValue);
            }
        }
    }

    if ($heroBackgroundType === 'video' && $currentHeroBackgroundType === 'image' && $currentHeroBackgroundValue !== $newHeroBackgroundValue) {
        deleteManagedUpload($currentHeroBackgroundValue);
    }

    $updateStmt = $pdo->prepare(
        'UPDATE site_settings
         SET site_title = :site_title,
             site_title_type = :site_title_type,
             primary_color = :primary_color,
             secondary_color = :secondary_color,
             background_color = :background_color,
             text_color = :text_color,
             accent_color = :accent_color,
             logo_path = :logo_path,
             hero_background_type = :hero_background_type,
             hero_background_value = :hero_background_value,
             homepage_intro = :homepage_intro,
             hero_text_color = :hero_text_color,
             hero_login_position = :hero_login_position,
             updated_by = :updated_by
         WHERE id = :id'
    );

    $updateStmt->execute([
        'site_title' => $siteTitle,
        'site_title_type' => $siteTitleType,
        'primary_color' => $primaryColor,
        'secondary_color' => $secondaryColor,
        'background_color' => $backgroundColor,
        'text_color' => $textColor,
        'accent_color' => $accentColor,
        'logo_path' => $newLogoPath,
        'hero_background_type' => $heroBackgroundType,
        'hero_background_value' => $newHeroBackgroundValue,
        'homepage_intro' => $homepageIntro !== '' ? $homepageIntro : null,
        'hero_text_color' => $heroTextColor,
        'hero_login_position' => $heroLoginPosition,
        'updated_by' => getCurrentUserId(),
        'id' => $settingsId
    ]);

    setFlashMessage('success', 'Les paramètres du site ont été mis à jour.');

    logAction(
        $pdo,
        getCurrentUserId(),
        'settings_update',
        'site_settings',
        (int) $settingsId,
        'Mise à jour des paramètres globaux du site.'
    );
} catch (Throwable $e) {
    setFlashMessage('error', $e->getMessage());
}

header('Location: /mangasan/admin/setting.php');
exit;
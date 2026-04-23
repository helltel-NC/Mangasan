<?php

declare(strict_types=1);

function ensureUploadDirectory(string $subDirectory): string
{
    $cleanDirectory = trim(str_replace(['..', '\\'], ['', '/'], $subDirectory), '/');

    if ($cleanDirectory === '') {
        throw new RuntimeException('Répertoire d’upload invalide.');
    }

    $absoluteDirectory = dirname(__DIR__) . '/public/uploads/' . $cleanDirectory;

    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('Impossible de créer le répertoire d’upload.');
    }

    return $absoluteDirectory;
}

function detectUploadedMimeType(string $tmpFile): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo !== false) {
            $mime = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    $imageInfo = @getimagesize($tmpFile);

    if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
        return (string) $imageInfo['mime'];
    }

    throw new RuntimeException('Impossible de déterminer le type du fichier envoyé.');
}

function createImageResource(string $tmpFile, string $mime)
{
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpFile) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpFile) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpFile) : false,
        default => false
    };
}

function getExtensionFromMime(string $mime): string
{
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => throw new RuntimeException('Format d’image non pris en charge.')
    };
}

function canConvertImageToWebp(string $mime): bool
{
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        return false;
    }

    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg'),
        'image/png' => function_exists('imagecreatefrompng'),
        'image/webp' => function_exists('imagecreatefromwebp'),
        default => false
    };
}

function saveUploadedImageAsWebp(array $file, string $subDirectory): string
{
    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload image invalide.');
    }

    $tmpFile = $file['tmp_name'] ?? '';

    if (!is_string($tmpFile) || $tmpFile === '' || !is_uploaded_file($tmpFile)) {
        throw new RuntimeException('Fichier temporaire introuvable.');
    }

    $mime = detectUploadedMimeType($tmpFile);
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowedMimeTypes, true)) {
        throw new RuntimeException('Format d’image non autorisé. Utilise JPG, PNG ou WEBP.');
    }

    $uploadDirectory = ensureUploadDirectory($subDirectory);
    $baseName = date('YmdHis') . '_' . bin2hex(random_bytes(8));

    if (canConvertImageToWebp($mime)) {
        $absolutePath = $uploadDirectory . '/' . $baseName . '.webp';
        $publicPath = '/mangasan/public/uploads/' . trim($subDirectory, '/') . '/' . $baseName . '.webp';

        $image = createImageResource($tmpFile, $mime);

        if ($image === false) {
            throw new RuntimeException('Impossible de lire l’image envoyée.');
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }

        @imagealphablending($image, true);
        @imagesavealpha($image, true);

        $saved = @imagewebp($image, $absolutePath, 85);

        if (is_object($image) || is_resource($image)) {
            imagedestroy($image);
        }

        if (!$saved) {
            throw new RuntimeException('Échec de la conversion de l’image en WEBP.');
        }

        return $publicPath;
    }

    $extension = getExtensionFromMime($mime);
    $absolutePath = $uploadDirectory . '/' . $baseName . '.' . $extension;
    $publicPath = '/mangasan/public/uploads/' . trim($subDirectory, '/') . '/' . $baseName . '.' . $extension;

    if (!move_uploaded_file($tmpFile, $absolutePath)) {
        throw new RuntimeException('Impossible d’enregistrer l’image envoyée.');
    }

    return $publicPath;
}

function isManagedUploadPath(?string $publicPath): bool
{
    return is_string($publicPath)
        && $publicPath !== ''
        && str_starts_with($publicPath, '/mangasan/public/uploads/');
}

function deleteManagedUpload(?string $publicPath): void
{
    if (!isManagedUploadPath($publicPath)) {
        return;
    }

    $relativePath = substr($publicPath, strlen('/mangasan/public/uploads/'));
    $absolutePath = dirname(__DIR__) . '/public/uploads/' . $relativePath;

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| En-tête commun du site
|--------------------------------------------------------------------------
| Variables possibles avant inclusion :
| - $pageTitle
| - $extraCss
*/


if (!isset($pageTitle) || trim((string)$pageTitle) === '') {
    $pageTitle = 'Mangasan';
}

if (!isset($extraCss) || !is_array($extraCss)) {
    $extraCss = [];
}

if (!isset($headHtml) || !is_string($headHtml)) {
    $headHtml = '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet" href="/mangasan/public/assets/css/style.css">

    <?php foreach ($extraCss as $cssPath): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>

    <?php echo $headHtml; ?>
</head>
<body>
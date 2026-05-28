<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pied de page commun du site
|--------------------------------------------------------------------------
| Variables possibles avant inclusion :
| - $extraJs
*/

if (!isset($extraJs) || !is_array($extraJs)) {
    $extraJs = [];
}

$globalJs = [
    '/mangasan/public/assets/js/page-transition.js',
];

$jsFiles = array_values(array_unique(array_merge($globalJs, $extraJs)));
?>

<footer class="footer">
    <div class="container">
        <p>© Mangasan - Lycée Jules Garnier</p>
    </div>
</footer>

<div class="manga-page-transition" id="mangaPageTransition" aria-hidden="true">
    <div class="manga-transition-backdrop"></div>
    <div class="manga-transition-light"></div>

    <div class="manga-transition-door" id="mangaTransitionDoor">
        <img
            src=""
            alt=""
            class="manga-transition-logo-image"
            id="mangaTransitionLogo"
        >
        <span class="manga-transition-logo-text">漫画</span>
    </div>

    <div class="manga-transition-caption">
        Ouverture de la fiche
    </div>
</div>

<?php foreach ($jsFiles as $jsPath): ?>
    <script src="<?php echo htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>
</body>
</html>

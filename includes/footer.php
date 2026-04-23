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
?>

<?php foreach ($extraJs as $jsPath): ?>
    <script src="<?php echo htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>
<footer class="footer">
    <div class="container">
        <p>© Mangasan - Lycée Jules Garnier</p>
    </div>
</footer>
</body>
</html>
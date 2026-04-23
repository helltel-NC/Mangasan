<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/theme.php';

requireLogin();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare(
    "SELECT
        users.username,
        users.first_name,
        users.last_name,
        users.display_name,
        users.class_name,
        roles.label AS role_label
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id = :id
     LIMIT 1"
);
$stmt->execute([
    'id' => getCurrentUserId()
]);

$user = $stmt->fetch();

if (!$user) {
    header('Location: /mangasan/actions/logout.php');
    exit;
}

$pageTitle = 'Mon profil - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/home.css',
    '/mangasan/public/assets/css/account.css'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="account-page">
    <header class="site-header">
        <div class="container header-inner">
            <div class="logo"><?php echo e($theme['site_title']); ?></div>
            <nav class="nav">
                <a href="/mangasan/public/index.php">Retour au site</a>
            </nav>
        </div>
    </header>

    <section class="hero account-hero">
        <div class="container hero-content">
            <div class="hero-text">
                <p class="hero-kicker">Mon compte</p>
                <h1 class="hero-title"><?php echo e($user['username']); ?></h1>
                <p class="hero-description">Modification limitée au nom affiché.</p>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="container">
            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="account-layout">
                <div class="account-panel">
                    <form method="post" action="/mangasan/actions/profile_update.php" class="account-form">
                        <div class="account-field">
                            <label>Pseudo</label>
                            <input type="text" value="<?php echo e($user['username']); ?>" readonly>
                        </div>

                        <div class="account-field">
                            <label>Nom</label>
                            <input type="text" value="<?php echo e(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])); ?>" readonly>
                        </div>

                        <div class="account-field">
                            <label>Rôle</label>
                            <input type="text" value="<?php echo e($user['role_label']); ?>" readonly>
                        </div>

                        <div class="account-field">
                            <label>Classe / groupe</label>
                            <input type="text" value="<?php echo !empty($user['class_name']) ? e($user['class_name']) : '—'; ?>" readonly>
                        </div>

                        <div class="account-field">
                            <label for="display_name">Nom affiché</label>
                            <input type="text" id="display_name" name="display_name" value="<?php echo e((string) $user['display_name']); ?>">
                        </div>

                        <div class="account-actions">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                            <a href="/mangasan/public/index.php" class="btn btn-secondary">Retour</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
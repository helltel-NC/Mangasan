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

function accountExcerpt(?string $value, int $length = 130): string
{
    $text = trim((string) $value);

    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length - 3) . '...';
}

function accountTabUrl(string $tab): string
{
    return '/mangasan/public/account.php?tab=' . rawurlencode($tab);
}

$allowedTabs = ['fiches', 'classement', 'profil', 'password'];
$activeTab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'fiches';

if (isset($_GET['password_required']) && (string) $_GET['password_required'] === '1') {
    $activeTab = 'password';
}

if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'fiches';
}

$userId = getCurrentUserId();

$stmt = $pdo->prepare(
    "SELECT
        users.username,
        users.first_name,
        users.last_name,
        users.display_name,
        users.class_name,
        users.must_change_password,
        roles.label AS role_label
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id = :id
     LIMIT 1"
);
$stmt->execute([
    'id' => $userId
]);

$user = $stmt->fetch();

if (!$user) {
    header('Location: /mangasan/actions/logout.php');
    exit;
}

$activeEditionStmt = $pdo->query(
    "SELECT *
     FROM editions
     WHERE is_active = 1
       AND status = 'active'
     ORDER BY start_date DESC, id DESC
     LIMIT 1"
);
$activeEdition = $activeEditionStmt->fetch() ?: null;

$activeEditionMangas = [];
$userReviewsByManga = [];
$userHubStats = [
    'total' => 0,
    'started' => 0,
    'locked' => 0,
    'remaining' => 0,
    'progress' => 0,
];
$userHubUsedRanks = [];
$userHubRemainingRanks = [];

if ($activeEdition) {
    $activeEditionMangasStmt = $pdo->prepare(
        "SELECT
            mangas.id,
            mangas.title,
            mangas.subtitle,
            mangas.author,
            mangas.illustrator,
            mangas.publisher,
            mangas.summary,
            mangas.card_image,
            mangas.cover_image,
            mangas.status,
            edition_mangas.display_order,
            edition_mangas.is_visible
         FROM edition_mangas
         INNER JOIN mangas ON mangas.id = edition_mangas.manga_id
         WHERE edition_mangas.edition_id = :edition_id
           AND edition_mangas.is_visible = 1
           AND mangas.status = 'active'
         ORDER BY edition_mangas.display_order ASC, mangas.title ASC"
    );
    $activeEditionMangasStmt->execute([
        'edition_id' => (int) $activeEdition['id']
    ]);
    $activeEditionMangas = $activeEditionMangasStmt->fetchAll();

    $userReviewsStmt = $pdo->prepare(
        "SELECT
            id,
            manga_id,
            status,
            is_locked,
            personal_rank,
            score,
            review_text,
            created_at,
            updated_at
         FROM reviews
         WHERE user_id = :user_id
           AND edition_id = :edition_id"
    );
    $userReviewsStmt->execute([
        'user_id' => $userId,
        'edition_id' => (int) $activeEdition['id']
    ]);

    foreach ($userReviewsStmt->fetchAll() as $userReview) {
        $userReviewsByManga[(int) $userReview['manga_id']] = $userReview;
    }

    $userHubStats['total'] = count($activeEditionMangas);

    foreach ($activeEditionMangas as $hubManga) {
        $hubReview = $userReviewsByManga[(int) $hubManga['id']] ?? null;

        if (!$hubReview) {
            continue;
        }

        $userHubStats['started']++;

        if ((string) $hubReview['status'] === 'locked' || (int) $hubReview['is_locked'] === 1) {
            $userHubStats['locked']++;
        }

        $personalRank = (int) ($hubReview['personal_rank'] ?? 0);
        if ($personalRank > 0) {
            $userHubUsedRanks[] = $personalRank;
        }
    }

    $userHubStats['remaining'] = max(0, $userHubStats['total'] - $userHubStats['started']);
    $userHubStats['progress'] = $userHubStats['total'] > 0
        ? (int) round(($userHubStats['started'] / $userHubStats['total']) * 100)
        : 0;

    $userHubUsedRanks = array_values(array_unique($userHubUsedRanks));
    sort($userHubUsedRanks);

    if ($userHubStats['total'] > 0) {
        $allRanks = range(1, $userHubStats['total']);
        $userHubRemainingRanks = array_values(array_diff($allRanks, $userHubUsedRanks));
    }
}

$accountReviewItems = [];
$accountRankedItems = [];
$accountPrimaryActionUrl = null;
$accountPrimaryActionLabel = null;

if ($activeEdition && $activeEditionMangas) {
    foreach ($activeEditionMangas as $hubManga) {
        $hubReview = $userReviewsByManga[(int) $hubManga['id']] ?? null;
        $hubLocked = $hubReview && ((string) $hubReview['status'] === 'locked' || (int) $hubReview['is_locked'] === 1);
        $hubStarted = $hubReview !== null;
        $hubReviewUrl = '/mangasan/public/review_edit.php?edition_id=' . (int) $activeEdition['id'] . '&manga_id=' . (int) $hubManga['id'];
        $hubReviewLabel = !$hubStarted
            ? 'Remplir ma fiche'
            : ($hubLocked ? 'Consulter' : 'Modifier');
        $hubStatusLabel = !$hubStarted
            ? 'Non commencée'
            : ($hubLocked ? 'Verrouillée' : 'Modifiable');
        $hubStatusClass = !$hubStarted
            ? 'is-todo'
            : ($hubLocked ? 'is-locked' : 'is-editable');
        $hubImage = (string) ($hubManga['card_image'] ?: $hubManga['cover_image']);
        $hubRank = $hubReview && (int) ($hubReview['personal_rank'] ?? 0) > 0
            ? (int) $hubReview['personal_rank']
            : null;

        $accountReviewItems[] = [
            'manga' => $hubManga,
            'review' => $hubReview,
            'locked' => $hubLocked,
            'started' => $hubStarted,
            'url' => $hubReviewUrl,
            'label' => $hubReviewLabel,
            'status_label' => $hubStatusLabel,
            'status_class' => $hubStatusClass,
            'image' => $hubImage,
            'rank' => $hubRank,
        ];

        if ($hubRank !== null) {
            $accountRankedItems[] = [
                'rank' => $hubRank,
                'title' => (string) $hubManga['title'],
                'subtitle' => (string) ($hubManga['subtitle'] ?? ''),
                'url' => $hubReviewUrl,
                'locked' => $hubLocked,
            ];
        }

        if ($accountPrimaryActionUrl === null && (!$hubStarted || !$hubLocked)) {
            $accountPrimaryActionUrl = $hubReviewUrl;
            $accountPrimaryActionLabel = !$hubStarted
                ? 'Remplir une fiche'
                : 'Continuer une fiche';
        }
    }

    usort($accountRankedItems, static function (array $a, array $b): int {
        return $a['rank'] <=> $b['rank'];
    });

    if ($accountPrimaryActionUrl === null && $accountReviewItems) {
        $accountPrimaryActionUrl = $accountReviewItems[0]['url'];
        $accountPrimaryActionLabel = 'Consulter mes fiches';
    }
}

$pageTitle = 'Mon espace - Mangasan';
$extraCss = [
    '/mangasan/public/assets/css/home.css',
    '/mangasan/public/assets/css/account.css'
];

$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$flashMessages = getFlashMessages();
$displayName = trim((string) ($user['display_name'] ?: $user['username']));
$fullName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="account-page">
    <header class="site-header account-topbar">
        <div class="container header-inner">
            <div class="logo"><?php echo e($theme['site_title']); ?></div>
            <nav class="nav">
                <a href="/mangasan/public/index.php">Retour au site</a>
                <a href="/mangasan/actions/logout.php">Déconnexion</a>
            </nav>
        </div>
    </header>

    <section class="hero account-hero">
        <div class="container account-hero-inner">
            <div class="account-hero-text">
                <p class="hero-kicker">Mon espace Manga San</p>
                <h1 class="hero-title"><?php echo e($displayName); ?></h1>
                <p class="hero-description">
                    Retrouvez vos fiches de lecture, votre classement personnel et vos informations de compte.
                </p>
            </div>

            <aside class="account-identity-card" aria-label="Résumé du compte">
                <div>
                    <span>Utilisateur</span>
                    <strong><?php echo e((string) $user['username']); ?></strong>
                </div>
                <div>
                    <span>Rôle</span>
                    <strong><?php echo e((string) $user['role_label']); ?></strong>
                </div>
                <div>
                    <span>Classe / groupe</span>
                    <strong><?php echo !empty($user['class_name']) ? e((string) $user['class_name']) : '—'; ?></strong>
                </div>
            </aside>
        </div>
    </section>

    <nav class="account-tabs-shell" aria-label="Navigation de l’espace utilisateur">
        <div class="container account-tabs">
            <a href="<?php echo e(accountTabUrl('fiches')); ?>" class="account-tab <?php echo $activeTab === 'fiches' ? 'is-current' : ''; ?>">Mes fiches</a>
            <a href="<?php echo e(accountTabUrl('classement')); ?>" class="account-tab <?php echo $activeTab === 'classement' ? 'is-current' : ''; ?>">Mon classement</a>
            <a href="<?php echo e(accountTabUrl('profil')); ?>" class="account-tab <?php echo $activeTab === 'profil' ? 'is-current' : ''; ?>">Mon profil</a>
            <a href="<?php echo e(accountTabUrl('password')); ?>" class="account-tab <?php echo $activeTab === 'password' ? 'is-current' : ''; ?>">Mot de passe</a>
        </div>
    </nav>

    <section class="section account-tabs-content">
        <div class="container">
            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="alert <?php echo e($flashMessage['type']); ?>">
                    <?php echo e($flashMessage['message']); ?>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($user['must_change_password'])): ?>
                <div class="alert error">
                    Tu utilises encore un mot de passe temporaire. Change ton mot de passe pour sécuriser ton compte.
                </div>
            <?php endif; ?>

            <section class="account-tab-panel <?php echo $activeTab === 'fiches' ? 'is-active' : 'is-hidden'; ?>" id="account-tab-fiches">
                <div class="section-heading account-section-heading">
                    <div>
                        <p class="section-kicker">Tableau de bord</p>
                        <h2>Mes fiches de lecture</h2>
                    </div>

                    <?php if ($accountPrimaryActionUrl !== null): ?>
                        <a href="<?php echo e($accountPrimaryActionUrl); ?>" class="section-link js-manga-transition"><?php echo e($accountPrimaryActionLabel); ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($activeEdition && $activeEditionMangas): ?>
                    <article class="account-dashboard-card card-surface">
                        <div class="account-dashboard-title">
                            <p>Édition active</p>
                            <h3><?php echo e((string) $activeEdition['title']); ?></h3>
                            <span><?php echo (int) $userHubStats['started']; ?> fiche(s) commencée(s) sur <?php echo (int) $userHubStats['total']; ?> manga(s).</span>
                        </div>

                        <div class="account-progress-card">
                            <div class="account-progress-head">
                                <strong><?php echo (int) $userHubStats['started']; ?> / <?php echo (int) $userHubStats['total']; ?></strong>
                                <span><?php echo (int) $userHubStats['progress']; ?>%</span>
                            </div>
                            <div class="account-progress-bar">
                                <span style="width: <?php echo (int) $userHubStats['progress']; ?>%;"></span>
                            </div>
                            <p><?php echo (int) $userHubStats['remaining']; ?> fiche(s) restante(s).</p>
                        </div>

                        <div class="account-rank-card">
                            <span>Rangs utilisés</span>
                            <strong><?php echo $userHubUsedRanks ? e(implode(', ', $userHubUsedRanks)) : '—'; ?></strong>
                            <span>Rangs restants</span>
                            <strong><?php echo $userHubRemainingRanks ? e(implode(', ', $userHubRemainingRanks)) : '—'; ?></strong>
                        </div>
                    </article>

                    <div class="account-review-grid">
                        <?php foreach ($accountReviewItems as $item): ?>
                            <?php $manga = $item['manga']; ?>
                            <article class="account-review-card <?php echo e($item['status_class']); ?>">
                                <div class="account-review-cover">
                                    <?php if ($item['image'] !== ''): ?>
                                        <img src="<?php echo e($item['image']); ?>" alt="<?php echo e((string) $manga['title']); ?>">
                                    <?php else: ?>
                                        <span>Aucune image</span>
                                    <?php endif; ?>
                                </div>

                                <div class="account-review-content">
                                    <div class="account-review-meta">
                                        <span class="account-status <?php echo e($item['status_class']); ?>"><?php echo e($item['status_label']); ?></span>
                                        <span class="account-rank">Rang : <?php echo $item['rank'] !== null ? (int) $item['rank'] : '—'; ?></span>
                                    </div>

                                    <h3><?php echo e((string) $manga['title']); ?></h3>

                                    <?php if (!empty($manga['summary'])): ?>
                                        <p><?php echo e(accountExcerpt((string) $manga['summary'], 140)); ?></p>
                                    <?php elseif (!empty($manga['author'])): ?>
                                        <p>Auteur : <?php echo e((string) $manga['author']); ?></p>
                                    <?php endif; ?>

                                    <a href="<?php echo e($item['url']); ?>" class="btn btn-primary js-manga-transition"><?php echo e($item['label']); ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($activeEdition): ?>
                    <div class="alert success">
                        L’édition active existe, mais aucun manga visible n’y est encore rattaché.
                    </div>
                <?php else: ?>
                    <div class="alert error">
                        Aucune édition active n’est disponible pour le moment.
                    </div>
                <?php endif; ?>
            </section>

            <section class="account-tab-panel <?php echo $activeTab === 'classement' ? 'is-active' : 'is-hidden'; ?>" id="account-tab-classement">
                <div class="section-heading account-section-heading">
                    <div>
                        <p class="section-kicker">Classement personnel</p>
                        <h2>Mon classement</h2>
                    </div>
                </div>

                <?php if ($activeEdition && $activeEditionMangas): ?>
                    <div class="account-ranking-layout">
                        <article class="account-side-card card-surface">
                            <p class="section-kicker">Résumé</p>
                            <h3><?php echo e((string) $activeEdition['title']); ?></h3>
                            <ul class="account-info-list">
                                <li><?php echo (int) $userHubStats['started']; ?> fiche(s) commencée(s).</li>
                                <li><?php echo (int) $userHubStats['remaining']; ?> fiche(s) restante(s).</li>
                                <li><?php echo (int) $userHubStats['locked']; ?> fiche(s) verrouillée(s).</li>
                            </ul>
                        </article>

                        <article class="account-side-card card-surface">
                            <p class="section-kicker">Ordre actuel</p>
                            <?php if ($accountRankedItems): ?>
                                <ol class="account-ranking-list">
                                    <?php foreach ($accountRankedItems as $rankedItem): ?>
                                        <li>
                                            <span>#<?php echo (int) $rankedItem['rank']; ?></span>
                                            <a href="<?php echo e($rankedItem['url']); ?>" class="js-manga-transition">
                                                <?php echo e($rankedItem['title']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php else: ?>
                                <p class="account-empty-text">Aucun rang n’a encore été choisi.</p>
                            <?php endif; ?>
                        </article>

                        <article class="account-side-card card-surface">
                            <p class="section-kicker">Rangs restants</p>
                            <h3><?php echo $userHubRemainingRanks ? e(implode(', ', $userHubRemainingRanks)) : 'Aucun'; ?></h3>
                            <p class="account-empty-text">Chaque rang doit être utilisé une seule fois dans l’édition.</p>
                        </article>
                    </div>
                <?php else: ?>
                    <div class="alert error">
                        Aucun classement personnel n’est disponible pour le moment.
                    </div>
                <?php endif; ?>
            </section>

            <section class="account-tab-panel <?php echo $activeTab === 'profil' ? 'is-active' : 'is-hidden'; ?>" id="account-tab-profil">
                <div class="section-heading account-section-heading">
                    <div>
                        <p class="section-kicker">Profil</p>
                        <h2>Mes informations</h2>
                    </div>
                </div>

                <div class="account-layout">
                    <div class="account-panel">
                        <form method="post" action="/mangasan/actions/profile_update.php" class="account-form">
                            <div class="account-field">
                                <label>Pseudo</label>
                                <input type="text" value="<?php echo e((string) $user['username']); ?>" readonly>
                            </div>

                            <div class="account-field">
                                <label>Nom</label>
                                <input type="text" value="<?php echo e($fullName !== '' ? $fullName : '—'); ?>" readonly>
                            </div>

                            <div class="account-field">
                                <label>Rôle</label>
                                <input type="text" value="<?php echo e((string) $user['role_label']); ?>" readonly>
                            </div>

                            <div class="account-field">
                                <label>Classe / groupe</label>
                                <input type="text" value="<?php echo !empty($user['class_name']) ? e((string) $user['class_name']) : '—'; ?>" readonly>
                            </div>

                            <div class="account-field account-field-wide">
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
            </section>

            <section class="account-tab-panel <?php echo $activeTab === 'password' ? 'is-active' : 'is-hidden'; ?>" id="account-tab-password">
                <div class="section-heading account-section-heading">
                    <div>
                        <p class="section-kicker">Sécurité</p>
                        <h2>Changer mon mot de passe</h2>
                    </div>
                </div>

                <div class="account-layout">
                    <div class="account-panel">
                        <form method="post" action="/mangasan/actions/password_update.php" class="account-form" autocomplete="off">
                            <div class="account-field account-field-wide">
                                <p class="account-empty-text">
                                    Choisis un mot de passe personnel. Il doit contenir au moins 8 caractères.
                                </p>
                            </div>

                            <div class="account-field account-field-wide">
                                <label for="current_password">Mot de passe actuel</label>
                                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                            </div>

                            <div class="account-field">
                                <label for="new_password">Nouveau mot de passe</label>
                                <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                            </div>

                            <div class="account-field">
                                <label for="new_password_confirm">Confirmer le nouveau mot de passe</label>
                                <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" minlength="8" required>
                            </div>

                            <div class="account-actions">
                                <button type="submit" class="btn btn-primary">Changer mon mot de passe</button>
                                <a href="/mangasan/public/account.php?tab=profil" class="btn btn-secondary">Retour au profil</a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

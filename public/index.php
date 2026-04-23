<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/rankings.php';


function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalizeColor(?string $value, string $default): string
{
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
}

function renderMultilineText(?string $value): string
{
    $text = trim((string) $value);

    if ($text === '') {
        return '';
    }

    return nl2br(e($text));
}

function buildSectionsMap(array $sections): array
{
    $map = [];

    foreach ($sections as $section) {
        if (!isset($section['section_key'])) {
            continue;
        }

        $map[(string) $section['section_key']] = $section;
    }

    return $map;
}

function findSection(array $sectionsByKey, array $keys): ?array
{
    foreach ($keys as $key) {
        if (isset($sectionsByKey[$key])) {
            return $sectionsByKey[$key];
        }
    }

    return null;
}

function isVideoFilePath(?string $value): bool
{
    $path = strtolower((string) $value);

    return str_ends_with($path, '.mp4') || str_ends_with($path, '.webm') || str_ends_with($path, '.ogg');
}

function renderSectionMedia(?array $section, string $wrapperClass = 'section-media'): void
{
    if (!$section || empty($section['media_type']) || $section['media_type'] === 'none' || empty($section['media_value'])) {
        return;
    }

    $mediaType = (string) $section['media_type'];
    $mediaValue = (string) $section['media_value'];

    echo '<div class="' . e($wrapperClass) . '">';

    if ($mediaType === 'image') {
        echo '<img src="' . e($mediaValue) . '" alt="' . e($section['title'] ?? '') . '">';
    } elseif ($mediaType === 'video') {
        if (isVideoFilePath($mediaValue)) {
            echo '<video controls preload="metadata">';
            echo '<source src="' . e($mediaValue) . '">';
            echo '</video>';
        } else {
            echo '<a href="' . e($mediaValue) . '" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Voir le média vidéo</a>';
        }
    }

    echo '</div>';
}

$siteSettingsStmt = $pdo->query(
    'SELECT *
     FROM site_settings
     ORDER BY id ASC
     LIMIT 1'
);
$siteSettings = $siteSettingsStmt->fetch() ?: [];

$sectionsStmt = $pdo->query(
    'SELECT *
     FROM site_sections
     WHERE is_visible = 1
     ORDER BY display_order ASC, id ASC'
);
$sections = $sectionsStmt->fetchAll();
$sectionsByKey = buildSectionsMap($sections);
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
$activeEditionMangasCount = 0;
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
            mangas.video_url,
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
    $activeEditionMangasCount = count($activeEditionMangas);
}

$userReviewsByManga = [];

if (isLoggedIn() && $activeEdition && $activeEditionMangas) {
    $userReviewsStmt = $pdo->prepare(
        "SELECT manga_id, status, is_locked
         FROM reviews
         WHERE user_id = :user_id
           AND edition_id = :edition_id"
    );
    $userReviewsStmt->execute([
        'user_id' => getCurrentUserId(),
        'edition_id' => (int) $activeEdition['id']
    ]);

    foreach ($userReviewsStmt->fetchAll() as $userReview) {
        $userReviewsByManga[(int) $userReview['manga_id']] = $userReview;
    }
}

if ($activeEdition) {
    $archivedEditionsStmt = $pdo->prepare(
        "SELECT
            id,
            title,
            year,
            description,
            status,
            start_date,
            end_date
         FROM editions
         WHERE status IN ('closed', 'archived')
           AND id <> :active_id
         ORDER BY year DESC, start_date DESC, id DESC"
    );

    $archivedEditionsStmt->execute([
        'active_id' => (int) $activeEdition['id']
    ]);
} else {
    $archivedEditionsStmt = $pdo->prepare(
        "SELECT
            id,
            title,
            year,
            description,
            status,
            start_date,
            end_date
         FROM editions
         WHERE status IN ('closed', 'archived')
         ORDER BY year DESC, start_date DESC, id DESC"
    );

    $archivedEditionsStmt->execute();
}

$archivedEditions = $archivedEditionsStmt->fetchAll();
$activeEditionRanking = [];
$canDisplayActiveEditionRanking = false;

if ($activeEdition) {
    $canDisplayActiveEditionRanking = canDisplayEditionRanking($activeEdition, isLoggedIn());

    if ($canDisplayActiveEditionRanking) {
        $activeEditionRanking = getEditionRanking($pdo, (int) $activeEdition['id']);
    }
}
$heroSection = findSection($sectionsByKey, ['hero', 'homepage_hero', 'home_hero']);
$editionSection = findSection($sectionsByKey, ['edition', 'current_edition', 'featured', 'featured_edition']);
$archivesSection = findSection($sectionsByKey, ['archives', 'past_editions']);
$contestSection = findSection($sectionsByKey, ['concours', 'drawing_contest', 'contest']);
$videosSection = findSection($sectionsByKey, ['videos', 'video']);
$rulesSection = findSection($sectionsByKey, ['reglement', 'rules', 'regulation']);

$siteTitle = trim((string) ($siteSettings['site_title'] ?? 'Mangasan'));
$siteTitle = $siteTitle !== '' ? $siteTitle : 'Mangasan';

$pageTitle = $siteTitle;

$extraCss = [
    '/mangasan/public/assets/css/home.css'
];

$primaryColor = normalizeColor($siteSettings['primary_color'] ?? null, '#e50914');
$secondaryColor = normalizeColor($siteSettings['secondary_color'] ?? null, '#333333');
$backgroundColor = normalizeColor($siteSettings['background_color'] ?? null, '#0f0f0f');
$textColor = normalizeColor($siteSettings['text_color'] ?? null, '#ffffff');
$accentColor = normalizeColor($siteSettings['accent_color'] ?? null, '#c1121f');
$heroTextColor = normalizeColor($siteSettings['hero_text_color'] ?? null, '#ffffff');

$siteTitleType = (string) ($siteSettings['site_title_type'] ?? 'text');
$logoPath = trim((string) ($siteSettings['logo_path'] ?? ''));
$heroBackgroundType = (string) ($siteSettings['hero_background_type'] ?? 'image');
$heroBackgroundValue = trim((string) ($siteSettings['hero_background_value'] ?? ''));
$heroLoginPosition = (string) ($siteSettings['hero_login_position'] ?? 'right');
$heroLoginPosition = in_array($heroLoginPosition, ['left', 'right'], true) ? $heroLoginPosition : 'right';
$homepageIntro = trim((string) ($siteSettings['homepage_intro'] ?? ''));

$heroTitle = !empty($heroSection['title']) ? (string) $heroSection['title'] : $siteTitle;
$heroKicker = !empty($heroSection['subtilte']) ? (string) $heroSection['subtilte'] : 'Mangasan';
$heroDescription = !empty($heroSection['content']) ? (string) $heroSection['content'] : $homepageIntro;
$theme = getSiteThemeSettings($pdo);
$headHtml = buildThemeStyleTag($theme);
$heroStyle = '';
if ($heroBackgroundType === 'image' && $heroBackgroundValue !== '') {
    $heroStyle = 'background-image: url(\'' . e($heroBackgroundValue) . '\'); background-size: cover; background-position: center; background-repeat: no-repeat;';
} elseif ($heroBackgroundType === 'color') {
    $heroStyle = 'background: linear-gradient(135deg, ' . e($secondaryColor) . ', ' . e($backgroundColor) . ');';
}
function excerpt(?string $value, int $length = 140): string
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

function formatEditionPeriod(?string $startDate, ?string $endDate): string
{
    $start = trim((string) $startDate);
    $end = trim((string) $endDate);

    if ($start === '' && $end === '') {
        return '';
    }

    if ($start !== '' && $end !== '') {
        return $start . ' → ' . $end;
    }

    return $start !== '' ? $start : $end;
}
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --color-primary: <?php echo e($primaryColor); ?>;
        --color-primary-hover: <?php echo e($primaryColor); ?>;
        --color-secondary: <?php echo e($secondaryColor); ?>;
        --color-background: <?php echo e($backgroundColor); ?>;
        --color-text: <?php echo e($textColor); ?>;
        --color-accent: <?php echo e($accentColor); ?>;
    }

    .page-home .hero-title,
    .page-home .hero-description {
        color: <?php echo e($heroTextColor); ?>;
    }
</style>

<main class="page-home">
    <header class="site-header">
        <div class="container header-inner">
        <div class="logo">
            <?php if ($siteTitleType === 'text'): ?>
                <span class="site-logo-text"><?php echo e($siteTitle); ?></span>

            <?php elseif ($siteTitleType === 'image'): ?>
                <?php if ($logoPath !== ''): ?>
                    <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($siteTitle); ?>" class="site-logo-image">
                <?php endif; ?>

            <?php elseif ($siteTitleType === 'text_image'): ?>
                <div class="site-logo-group">
                    <?php if ($logoPath !== ''): ?>
                        <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($siteTitle); ?>" class="site-logo-image">
                    <?php endif; ?>

                    <?php if ($siteTitle !== ''): ?>
                        <span class="site-logo-text"><?php echo e($siteTitle); ?></span>
                    <?php endif; ?>
                </div>

            <?php elseif ($siteTitleType === 'none'): ?>
                <div class="site-logo-group is-empty"></div>
            <?php endif; ?>
        </div>

            <nav class="nav">
                <?php if ($editionSection): ?>
                    <a href="#edition">Édition</a>
                <?php endif; ?>

                <?php if ($archivesSection): ?>
                    <a href="#archives">Archives</a>
                <?php endif; ?>

                <?php if ($contestSection): ?>
                    <a href="#concours">Concours</a>
                <?php endif; ?>

                <?php if ($videosSection): ?>
                    <a href="#videos">Vidéos</a>
                <?php endif; ?>

                <?php if ($rulesSection): ?>
                    <a href="#reglement">Règlement</a>
                <?php endif; ?>
                <?php if ($canDisplayActiveEditionRanking): ?>
                    <a href="#ranking">Classement</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <section class="hero" style="<?php echo $heroStyle; ?>">
        <?php if ($heroBackgroundType === 'video' && $heroBackgroundValue !== '' && isVideoFilePath($heroBackgroundValue)): ?>
            <video class="hero-bg-video" autoplay muted loop playsinline>
                <source src="<?php echo e($heroBackgroundValue); ?>">
            </video>
        <?php endif; ?>

        <div class="container hero-content hero-content--login-<?php echo e($heroLoginPosition); ?>">
            <div class="hero-text">
                <p class="hero-kicker"><?php echo e($heroKicker); ?></p>
                <h1 class="hero-title"><?php echo e($heroTitle); ?></h1>

                <?php if ($heroDescription !== ''): ?>
                    <p class="hero-description"><?php echo renderMultilineText($heroDescription); ?></p>
                <?php endif; ?>
            </div>

            <div class="hero-visual">
                <div class="hero-login">
                    <?php if (!isLoggedIn()): ?>
                        <?php if (!empty($_SESSION['login_error'])): ?>
                            <div class="alert error">
                                <?php echo e($_SESSION['login_error']); ?>
                            </div>
                            <?php unset($_SESSION['login_error']); ?>
                        <?php endif; ?>

                        <form method="post" action="/mangasan/actions/login.php" class="login-form">
                            <input type="text" name="username" placeholder="Utilisateur" required>
                            <input type="password" name="password" placeholder="Mot de passe" required>
                            <button type="submit" class="btn btn-primary">Connexion</button>
                        </form>
                    <?php else: ?>
                        <div class="user-box">
                            <p>Connecté : <?php echo e(getCurrentUsername() ?? ''); ?></p>

                            <?php if (isAdmin()): ?>
                                <a href="/mangasan/admin/index.php" class="btn btn-primary">Administration</a>
                            <?php else: ?>
                                <a href="/mangasan/public/index.php" class="btn btn-primary">Espace membre</a>
                            <?php endif; ?>

                            <a href="/mangasan/actions/logout.php" class="btn btn-secondary">Déconnexion</a>
                            <a href="/mangasan/public/account.php" class="btn btn-secondary">Mon profil</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($editionSection): ?>
        <section class="section featured-section" id="edition">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <?php if (!empty($editionSection['subtilte'])): ?>
                            <p class="section-kicker"><?php echo e($editionSection['subtilte']); ?></p>
                        <?php endif; ?>
                        <h2><?php echo e($editionSection['title']); ?></h2>
                    </div>

                    <?php if ($archivesSection): ?>
                        <a href="#archives" class="section-link">Anciennes éditions</a>
                    <?php endif; ?>
                </div>

                <?php renderSectionMedia($editionSection); ?>

                <?php if (!empty($editionSection['content'])): ?>
                    <div class="section-intro"><?php echo renderMultilineText($editionSection['content']); ?></div>
                <?php endif; ?>
<?php if ($activeEdition): ?>
    <?php
    $editionPeriod = formatEditionPeriod(
        $activeEdition['start_date'] ?? null,
        $activeEdition['end_date'] ?? null
    );
    ?>

    <div class="edition-meta-card">
        <div class="edition-meta-top">
            <div>
                <p class="edition-meta-label">Édition en cours</p>
                <h3><?php echo e($activeEdition['title']); ?></h3>
            </div>

            <?php if (!empty($activeEdition['year'])): ?>
                <span class="edition-meta-year"><?php echo e((string) $activeEdition['year']); ?></span>
            <?php endif; ?>
        </div>

        <div class="edition-meta-grid">
            <?php if ($editionPeriod !== ''): ?>
                <div class="edition-meta-item">
                    <span>Période</span>
                    <strong><?php echo e($editionPeriod); ?></strong>
                </div>
            <?php endif; ?>

            <div class="edition-meta-item">
                <span>Mangas visibles</span>
                <strong><?php echo (int) $activeEditionMangasCount; ?></strong>
            </div>
        </div>

        <?php if (!empty($activeEdition['description'])): ?>
            <p class="edition-meta-description">
                <?php echo renderMultilineText((string) $activeEdition['description']); ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="carousel-shell<?php echo $activeEditionMangasCount <= 1 ? ' is-single' : ''; ?>">
    <?php if ($activeEditionMangasCount > 1): ?>
        <button class="carousel-btn prev" type="button" aria-label="Précédent">‹</button>
    <?php endif; ?>

    <div class="featured-track<?php echo $activeEditionMangasCount <= 1 ? ' is-single' : ''; ?>" id="featuredTrack">
    <?php if ($activeEdition && $activeEditionMangas): ?>
        <?php foreach ($activeEditionMangas as $manga): ?>
            <?php
            $reviewUrl = '';
            $reviewLabel = '';

            if (isLoggedIn() && $activeEdition) {
                $reviewUrl = '/mangasan/public/review_edit.php?edition_id=' . (int) $activeEdition['id'] . '&manga_id=' . (int) $manga['id'];

                $existingUserReview = $userReviewsByManga[(int) $manga['id']] ?? null;

                if ($existingUserReview) {
                    $reviewLabel = ((string) $existingUserReview['status'] === 'locked' || (int) $existingUserReview['is_locked'] === 1)
                        ? 'Consulter ma fiche de lecture'
                        : 'Modifier ma fiche de lecture';
                } else {
                    $reviewLabel = 'Créer ma fiche de lecture';
                }
            }
            ?>
            <article
                class="featured-card"
                data-review-url="<?php echo e($reviewUrl); ?>"
                data-review-label="<?php echo e($reviewLabel); ?>"
                data-title="<?php echo e($manga['title']); ?>"
                data-description="<?php echo e((string) ($manga['summary'] ?? '')); ?>"
                data-extra="<?php echo e(trim(
                    'Auteur : ' . ((string) ($manga['author'] ?? '') !== '' ? (string) $manga['author'] : '—') .
                    ' | Éditeur : ' . ((string) ($manga['publisher'] ?? '') !== '' ? (string) $manga['publisher'] : '—')
                )); ?>"
                data-cover="<?php echo e((string) ($manga['cover_image'] ?: $manga['card_image'])); ?>"
            >
                <div class="manga-img">
                    <?php if (!empty($manga['card_image']) || !empty($manga['cover_image'])): ?>
                        <img
                            src="<?php echo e((string) ($manga['card_image'] ?: $manga['cover_image'])); ?>"
                            alt="<?php echo e($manga['title']); ?>"
                            class="manga-card-image"
                        >
                    <?php endif; ?>
                </div>

                <div class="manga-body">
                    <?php if (!empty($manga['subtitle'])): ?>
                        <span class="manga-tag"><?php echo e($manga['subtitle']); ?></span>
                    <?php endif; ?>

                    <h3><?php echo e($manga['title']); ?></h3>
                    <p><?php echo e(excerpt((string) ($manga['summary'] ?? ''), 140)); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    <?php elseif ($activeEdition): ?>
        <div class="alert success">
            L’édition active existe, mais aucun manga visible n’y est encore rattaché.
        </div>
    <?php else: ?>
        <div class="alert error">
            Aucune édition active n’est disponible pour le moment.
        </div>
    <?php endif; ?>
    </div>

    <?php if ($activeEditionMangasCount > 1): ?>
        <button class="carousel-btn next" type="button" aria-label="Suivant">›</button>
    <?php endif; ?>
</div>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($canDisplayActiveEditionRanking && $activeEdition): ?>
        <section class="section alt" id="ranking">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">Résultats</p>
                        <h2>Classement général</h2>
                    </div>
                </div>

                <div class="ranking-board">
                    <?php foreach ($activeEditionRanking as $rankingItem): ?>
                        <article class="ranking-card<?php echo $rankingItem['reviews_count'] === 0 ? ' is-unrated' : ''; ?>">
                            <div class="ranking-position">
                                <?php if ($rankingItem['display_rank'] !== null): ?>
                                    #<?php echo (int) $rankingItem['display_rank']; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </div>

                            <div class="ranking-cover">
                                <?php $rankingImage = $rankingItem['card_image'] ?: $rankingItem['cover_image']; ?>
                                <?php if (!empty($rankingImage)): ?>
                                    <img src="<?php echo e((string) $rankingImage); ?>" alt="<?php echo e((string) $rankingItem['title']); ?>">
                                <?php else: ?>
                                    <div class="ranking-cover-placeholder">Aucune image</div>
                                <?php endif; ?>
                            </div>

                            <div class="ranking-content">
                                <?php if (!empty($rankingItem['subtitle'])): ?>
                                    <p class="ranking-kicker"><?php echo e((string) $rankingItem['subtitle']); ?></p>
                                <?php endif; ?>

                                <h3><?php echo e((string) $rankingItem['title']); ?></h3>

                                <div class="ranking-stats">
                                    <?php if ($rankingItem['reviews_count'] > 0): ?>
                                        <div class="ranking-stat">
                                            <span>Moyenne</span>
                                            <strong><?php echo e(number_format((float) $rankingItem['average_score'], 2, '.', '')); ?> / <?php echo e((string) $activeEdition['score_max']); ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="ranking-stat">
                                            <span>État</span>
                                            <strong>Sans note</strong>
                                        </div>
                                    <?php endif; ?>

                                    <div class="ranking-stat">
                                        <span>Reviews</span>
                                        <strong><?php echo (int) $rankingItem['reviews_count']; ?></strong>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <?php if (!$activeEditionRanking): ?>
                        <div class="alert success">
                            Aucun manga n’est encore classé pour cette édition.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($archivesSection): ?>
        <section class="section archives-section" id="archives">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <?php if (!empty($archivesSection['subtilte'])): ?>
                            <p class="section-kicker"><?php echo e($archivesSection['subtilte']); ?></p>
                        <?php endif; ?>
                        <h2><?php echo e($archivesSection['title']); ?></h2>
                    </div>
                </div>

                <?php renderSectionMedia($archivesSection); ?>

                <?php if (!empty($archivesSection['content'])): ?>
                    <div class="section-intro"><?php echo renderMultilineText($archivesSection['content']); ?></div>
                <?php endif; ?>

                <div class="archives-grid">
    <?php if ($archivedEditions): ?>
        <?php foreach ($archivedEditions as $edition): ?>
            <article class="archive-card">
                <span class="archive-year"><?php echo e((string) $edition['year']); ?></span>
                <h3><?php echo e($edition['title']); ?></h3>

                <?php
                $archivePeriod = formatEditionPeriod(
                    $edition['start_date'] ?? null,
                    $edition['end_date'] ?? null
                );
                ?>

                <?php if ($archivePeriod !== ''): ?>
                    <p><?php echo e($archivePeriod); ?></p>
                <?php endif; ?>

                <?php if (!empty($edition['description'])): ?>
                    <p><?php echo e(excerpt((string) $edition['description'], 150)); ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert success">
            Aucune ancienne édition n’est disponible pour le moment.
        </div>
    <?php endif; ?>
</div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($contestSection): ?>
        <section class="section alt" id="concours">
            <div class="container">
                <h2><?php echo e($contestSection['title']); ?></h2>

                <?php renderSectionMedia($contestSection); ?>

                <?php if (!empty($contestSection['subtilte'])): ?>
                    <div class="section-intro"><?php echo renderMultilineText($contestSection['subtilte']); ?></div>
                <?php endif; ?>

                <div class="section-content">
                    <?php echo renderMultilineText($contestSection['content']); ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($videosSection): ?>
        <section class="section" id="videos">
            <div class="container">
                <h2><?php echo e($videosSection['title']); ?></h2>

                <?php renderSectionMedia($videosSection); ?>

                <?php if (!empty($videosSection['subtilte'])): ?>
                    <div class="section-intro"><?php echo renderMultilineText($videosSection['subtilte']); ?></div>
                <?php endif; ?>

                <?php if (!empty($videosSection['content'])): ?>
                    <div class="section-content"><?php echo renderMultilineText($videosSection['content']); ?></div>
                <?php endif; ?>

                <div class="video-grid"></div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($rulesSection): ?>
        <section class="section alt" id="reglement">
            <div class="container">
                <h2><?php echo e($rulesSection['title']); ?></h2>

                <?php renderSectionMedia($rulesSection); ?>

                <?php if (!empty($rulesSection['subtilte'])): ?>
                    <div class="section-intro"><?php echo renderMultilineText($rulesSection['subtilte']); ?></div>
                <?php endif; ?>

                <div class="section-content">
                    <?php echo renderMultilineText($rulesSection['content']); ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="detail-overlay" id="detailOverlay">
        <div class="detail-panel" id="detailPanel">
            <button class="detail-close" id="detailClose" type="button" aria-label="Fermer">×</button>

            <div class="detail-visual" id="detailVisual">
                <div class="detail-cover" id="detailCover">Couverture</div>
            </div>

            <div class="detail-content" id="detailContent">
                <div class="detail-text">
                    <p class="detail-kicker">Sélection Mangasan</p>
                    <h2 id="detailTitle"></h2>
                    <p id="detailDesc"></p>
                    <p id="detailExtra"></p>

                    <?php if (isLoggedIn()): ?>
                        <div class="detail-actions">
                            <a id="detailReviewLink" href="#" class="btn btn-primary">Ma fiche de lecture</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
$extraJs = [
    '/mangasan/public/assets/js/home.js',
    '/mangasan/public/assets/js/public-reviews.js'
];

require_once __DIR__ . '/../includes/footer.php';
?>
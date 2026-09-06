<?php
require_once __DIR__ . '/../config/app.php';

// Site-wide metadata
$site_name = SITE_NAME;
$site_tagline = "Find Your Perfect Budget Watch";
$site_description = "Explore and compare budget watches with detailed specs and prices from Amazon, Flipkart, Myntra, Casio, and more.";

// Page title fallback
$page_title = $page_title ?? $site_name;

// Default metadata
$page_description = $page_description ?? $site_description;
$page_robots = $page_robots ?? 'index, follow';
$page_canonical = $page_canonical ?? SITE_URL . '/';

$og_type = $og_type ?? 'website';
$og_title = $og_title ?? $page_title . ' | ' . $site_name;
$og_description = $og_description ?? $page_description;
$og_url = $og_url ?? $page_canonical;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script>
        (() => {
            let theme = 'light';

            try {
                const savedTheme = localStorage.getItem('watchshelf-theme');

                if (savedTheme === 'light' || savedTheme === 'dark') {
                    theme = savedTheme;
                } else if (
                    window.matchMedia &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches
                ) {
                    theme = 'dark';
                }
            } catch (error) {
                // Fall back to the light theme if storage is unavailable.
            }

            document.documentElement.dataset.theme = theme;
        })();
    </script>

    <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars($site_name) ?></title>

    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="robots" content="<?= htmlspecialchars($page_robots) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($page_canonical) ?>">

    <meta property="og:type" content="<?= htmlspecialchars($og_type) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>">

    <?php if (!empty($og_image)): ?>
        <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
    <?php endif; ?>

    <?php if (!empty($productSchema)): ?>
        <script type="application/ld+json">
            <?= json_encode(
                $productSchema,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ) ?>
        </script>
    <?php endif; ?>

    <?php if (!empty($breadcrumbSchema)): ?>
        <script type="application/ld+json">
            <?= json_encode(
                $breadcrumbSchema,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ) ?>
        </script>
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <script src="https://kit.fontawesome.com/9dd0cb4077.js" crossorigin="anonymous"></script>
    <script src="<?= JS_URL ?>/theme.js" defer></script>

    <link rel="icon" href="<?= SITE_URL ?>/assets/images/logo.svg" sizes="any">

    <link rel="stylesheet" href="<?= CSS_URL ?>/variables.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/style.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/theme-dark.css">
</head>

<body>
    <header class="site-header">
        <div class="site-container">

            <form class="search-form" action="<?= BASE_URL ?>/index.php" method="GET">
                <input type="text" name="search" placeholder="Search watches..."
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

                <button type="submit" name="search_submit" value="1">Search</button>
            </form>

            <div class="site-brand">
                <a href="<?= BASE_URL ?>/index.php">
                    <h2><?= htmlspecialchars($site_name) ?></h2>
                </a>

                <p><?= htmlspecialchars($site_tagline) ?></p>
            </div>

            <nav class="site-nav">
                <ul class="site-nav-list">
                    <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                    <li><a href="<?= BASE_URL ?>/about.php">About</a></li>
                    <li><a href="<?= BASE_URL ?>/contact.php">Contact</a></li>

                    <li class="theme-toggle-item">
                        <button type="button" class="theme-toggle" aria-label="Switch to dark theme"
                            title="Switch to dark theme" aria-pressed="false">

                            <i class="fa-solid fa-moon theme-toggle-dark-icon" aria-hidden="true"></i>

                            <i class="fa-solid fa-sun theme-toggle-light-icon" aria-hidden="true"></i>
                        </button>
                    </li>
                </ul>
            </nav>

        </div>
    </header>
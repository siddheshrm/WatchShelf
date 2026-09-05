<?php
require_once __DIR__ . '/config/app.php';

http_response_code(404);

$page_title = "Page Not Found";
$page_description = "The page you're looking for doesn't exist or may have been moved.";
$page_robots = 'noindex, follow';
$page_canonical = SITE_URL . '/';

$og_type = 'website';
$og_title = 'Page Not Found | WatchShelf';
$og_description = $page_description;
$og_url = SITE_URL . '/';

include __DIR__ . '/includes/header.php';
?>

<main class="site-main">
    <section class="page-hero">
        <div class="site-container">
            <h1>Page Not Found</h1>
            <p>The page you're looking for doesn't exist or may have been moved.</p>
        </div>
    </section>

    <div class="site-container">
        <section class="error-page">
            <div class="empty-state">
                <h2>404</h2>

                <p>
                    We couldn't find the page you requested.
                    You can return to the WatchShelf catalogue and continue browsing watches.
                </p>

                <a href="<?= BASE_URL ?>/" class="error-home-link">
                    Back to WatchShelf
                </a>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
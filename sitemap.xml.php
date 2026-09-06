<?php

require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/app.php';

header('Content-Type: application/xml; charset=UTF-8');

function xmlEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- Homepage / primary catalogue landing page. -->

    <!-- Paginated catalogue URLs such as ?page=2 are intentionally excluded from the sitemap.
    They remain indexable, self-canonical pages and are discoverable through the catalogue's crawlable pagination links. -->

    <url>
        <loc><?= xmlEscape(SITE_URL . '/') ?></loc>
    </url>

    <?php
    /*
     * Static public pages.
     *
     * These pages are canonical, indexable pages that provide useful site-level information.
     */
    $staticPages = [
        'about.php',
        'contact.php',
        'disclaimer.php',
        'privacy-policy.php',
    ];

    foreach ($staticPages as $page):
    ?>
        <url>
            <loc><?= xmlEscape(SITE_URL . '/' . $page) ?></loc>
        </url>
    <?php endforeach; ?>

    <?php
    /*
     * Quick Links.
     *
     * These are curated, indexable SEO landing pages rather than arbitrary filter combinations, so their primary URLs are intentionally included.
     *
     * Paginated Quick Link URLs such as
     * ?quick_link=example&page=2 are intentionally excluded.
     * They remain indexable, self-canonical pages and are discoverable through crawlable pagination links.
     */
    $quickLinks = require __DIR__ . '/config/quick-links.php';

    foreach ($quickLinks as $slug => $quickLink):
    ?>
        <url>
            <loc><?= xmlEscape(
                        SITE_URL . '/?quick_link=' . urlencode($slug)
                    ) ?></loc>
        </url>
    <?php endforeach; ?>

    <?php
    /*
     * Active watch detail pages.
     *
     * watches.created_at represents when the product was added to
     * WatchShelf and is intentionally used as the sitemap lastmod.
     *
     * Retailer price/availability changes in watch_variants do not
     * represent a significant modification to the watch page itself.
     */
    $watchQuery = "SELECT id, brand, model_name, created_at
                                FROM watches
                                WHERE is_active = 1
                                ORDER BY id ASC";

    $watchResult = $conn->query($watchQuery);

    if ($watchResult):
        while ($watch = $watchResult->fetch_assoc()):
            $watchUrl = getWatchUrl((int) $watch['id'], $watch['brand'], $watch['model_name']);

            $createdAt = $watch['created_at'] ? date('c', strtotime($watch['created_at'])) : null;
    ?>
            <url>
                <loc><?= xmlEscape($watchUrl) ?></loc>

                <?php if ($createdAt): ?>
                    <lastmod><?= xmlEscape($createdAt) ?></lastmod>
                <?php endif; ?>
            </url>
    <?php
        endwhile;
    endif;
    ?>

</urlset>
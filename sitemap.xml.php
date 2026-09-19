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
        'developers-note.php',
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
    * The most recent watch_variants.last_checked value is currently used
    * as the sitemap lastmod because retailer price and availability data
    * are dynamic parts of the watch detail page.
    *
    * Note: last_checked represents when a retailer variant was scraped,
    * not necessarily when its stored data changed.
    */
    $watchQuery = "SELECT w.id, w.brand, w.model_name, MAX(wr.last_checked) AS last_modified
                                FROM watches w
                                LEFT JOIN watch_variants wr
                                ON wr.watch_id = w.id
                                WHERE w.is_active = 1
                                GROUP BY w.id, w.brand, w.model_name
                                ORDER BY w.id ASC";

    $watchResult = $conn->query($watchQuery);

    if ($watchResult):
        while ($watch = $watchResult->fetch_assoc()):
            $watchUrl = getWatchUrl(
                (int) $watch['id'],
                $watch['brand'],
                $watch['model_name']
            );

            $lastModified = $watch['last_modified']
                ? date('c', strtotime($watch['last_modified']))
                : null;
    ?>
            <url>
                <loc><?= xmlEscape($watchUrl) ?></loc>

                <?php if ($lastModified): ?>
                    <lastmod><?= xmlEscape($lastModified) ?></lastmod>
                <?php endif; ?>
            </url>
    <?php
        endwhile;
    endif;
    ?>

</urlset>
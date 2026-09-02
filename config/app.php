<?php

$envFile = dirname(__DIR__) . '/.env';

if (is_file($envFile)) {
    $lines = file(
        $envFile,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separatorPosition = strpos($line, '=');

        if ($separatorPosition === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separatorPosition));
        $value = trim(substr($line, $separatorPosition + 1));

        if ($name === '') {
            continue;
        }

        // Remove optional surrounding quotes.
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
    }
}

// Application information
define('SITE_NAME', 'WatchShelf');
define('CONTACT_EMAIL', 'support@watchshelf.in');

// Environment-specific configuration
define('BASE_URL', '/WatchShelf');      // Local
// define('BASE_URL', '');              // Production

// Site URL used for canonical URLs, sitemap, Open Graph, structured data, etc.
define('SITE_URL', 'http://localhost/WatchShelf');       // Local
// define('SITE_URL', 'https://watchshelf.in');          // Production

// Assets
define('CSS_URL', BASE_URL . '/assets/css');
define('JS_URL', BASE_URL . '/assets/js');

// Images
define('WATCH_IMAGE_DIR', __DIR__ . '/../assets/images/watches');
define('WATCH_IMAGE_URL', BASE_URL . '/assets/images/watches');
define('DEFAULT_WATCH_IMAGE', WATCH_IMAGE_URL . '/no-image.webp');

// Owner status values
define('OWNER_STATUS_OWNED', 'owned');
define('OWNER_STATUS_INTERESTED', 'interested');

// Owner status labels
define('OWNER_COLLECTION_LABEL', "From Developer's Collection");
define('OWNER_WISHLIST_LABEL', "On Developer's Wishlist");
define('FEATURED_LABEL', "Featured");

// Application defaults
define('DEFAULT_CURRENCY', '₹');
define('ITEMS_PER_PAGE', 32);
define('MAX_WATCH_IMAGES', 5);

// Exchange Rate API Key
define('EXCHANGE_RATE_API_KEY', $_ENV['EXCHANGE_RATE_API_KEY'] ?? '');

// Scraper API
define('SCRAPER_API_KEY', $_ENV['SCRAPER_API_KEY'] ?? '');

// Watch URL helpers
function createWatchSlug(string $brand, string $model): string
{
    $slug = trim($brand . ' ' . $model);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    return trim($slug, '-');
}

function getWatchPath(int $id, string $brand, string $model): string
{
    $slug = createWatchSlug($brand, $model);

    return BASE_URL . '/watch/' . $slug . '-' . $id;
}

function getWatchUrl(int $id, string $brand, string $model): string
{
    $slug = createWatchSlug($brand, $model);

    return SITE_URL . '/watch/' . $slug . '-' . $id;
}

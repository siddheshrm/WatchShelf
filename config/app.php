<?php
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
define('OWNER_COLLECTION_LABEL', "⌚ From Developer's Collection");
define('OWNER_WISHLIST_LABEL', "👀 On Developer's Wishlist");
define('FEATURED_LABEL', '⭐ Featured');

// Application defaults
define('DEFAULT_CURRENCY', '₹');
define('ITEMS_PER_PAGE', 32);
define('MAX_WATCH_IMAGES', 5);

// Exchange Rate API Key
define('EXCHANGE_RATE_API_KEY', '13b2bf183d5a19701bcf3bcb');

// Scraper API
define('SCRAPER_API_KEY', 'mY_sCrApEr-KeY');

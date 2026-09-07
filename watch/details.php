<?php
require_once '../config/connection.php';
$COLOR_MAP = require_once '../config/colormap.php';
require_once '../config/app.php';

// Validate Request
if (
    !isset($_GET['id']) ||
    !ctype_digit((string) $_GET['id']) ||
    (int) $_GET['id'] <= 0
) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$id = (int) $_GET['id'];

// Fetch Watch Details
$stmt = $conn->prepare("SELECT w.*, (SELECT image_folder FROM watch_variants WHERE watch_id = w.id AND is_default = 1 LIMIT 1) AS image_folder FROM watches w WHERE w.id = ? AND w.is_active = 1;");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$watch = $result->fetch_assoc();

$expectedSlug = createWatchSlug($watch['brand'], $watch['model_name']);
$watchUrl = getWatchUrl($id, $watch['brand'], $watch['model_name']);

$requestedSlug = $_GET['slug'] ?? null;

// Redirect legacy or incorrect product URLs to the canonical URL.
if ($requestedSlug === null || $requestedSlug !== $expectedSlug) {
    header('Location: ' . $watchUrl, true, 301);
    exit;
}

// Load all images from the default variant's image folder
$images = [];

if (!empty($watch['image_folder'])) {
    $folder = WATCH_IMAGE_DIR . '/' . $watch['image_folder'];

    if (is_dir($folder)) {
        // Retrieve all .webp images and sort them naturally (1.webp, 2.webp, ..., 10.webp)
        $files = glob($folder . '/*.webp');
        natsort($files);

        // Convert filesystem paths to public image URLs
        foreach ($files as $file) {
            $images[] = WATCH_IMAGE_URL . '/' . $watch['image_folder'] . '/' . basename($file);
        }
    }
}

// Keep the actual product images separately for structured data
$productImages = $images;

if (empty($images)) {
    $images[] = DEFAULT_WATCH_IMAGE;
}

// Generic guide is always the final gallery image
$images[] = WATCH_CASE_SIZE_GUIDE_IMAGE;

// Fetch All Variants
$variantStmt = $conn->prepare("SELECT id, color_name, last_checked, image_folder, is_default, retailer_name, retailer_type, base_url, affiliate_url, price, currency, is_available
                                                    FROM watch_variants
                                                    WHERE watch_id = ?
                                                    ORDER BY display_order ASC, id ASC;");

$variantStmt->bind_param("i", $id);
$variantStmt->execute();

$variantResult = $variantStmt->get_result();

$variants = [];
$defaultVariant = null;

while ($row = $variantResult->fetch_assoc()) {
    $variants[] = $row;

    if ($row['is_default']) {
        $defaultVariant = $row;
    }
}

if (!$defaultVariant && !empty($variants)) {
    $defaultVariant = $variants[0];
}

// Group Variants by Color
$variantsByColor = [];
$colorVariants = [];

foreach ($variants as $variant) {
    $colorKey = strtolower(trim($variant['color_name']));

    // Group all retailers under the same color
    $variantsByColor[$colorKey][] = $variant;

    // Keep one representative variant for the color selector
    if (!isset($colorVariants[$colorKey])) {
        $colorVariants[$colorKey] = $variant;
    }
}

// Determine Selected Color
$selectedColor = null;

if ($defaultVariant) {
    $selectedColor = strtolower(trim($defaultVariant['color_name']));
}

// Get Retailers for Selected Color
$currentRetailers = [];

if ($selectedColor && isset($variantsByColor[$selectedColor])) {
    $currentRetailers = $variantsByColor[$selectedColor];
}

// Find Best Retailer
$bestRetailer = null;

foreach ($currentRetailers as $retailer) {
    if (!$retailer['is_available'] || $retailer['price'] === null) {
        continue;
    }

    if ($bestRetailer === null || $retailer['price'] < $bestRetailer['price']) {
        $bestRetailer = $retailer;
    }
}

$page_title = $watch['brand'] . " " . $watch['model_name'];

$og_type = 'product';
$og_title = $page_title;
$og_description = "Compare prices and retailers for {$page_title} on WatchShelf.";
$og_url = $watchUrl;
$og_image = $images[0] ?? DEFAULT_WATCH_IMAGE;

$page_description = sprintf(
    "View details, specifications, prices, and availability for the %s %s on WatchShelf.",
    $watch['brand'],
    $watch['model_name']
);

$page_canonical = $watchUrl;
$page_robots = 'index, follow';

// Calculate Discount
$discountAmount = 0;
$discountPercent = 0;

if ($bestRetailer && $bestRetailer['price'] !== null) {
    $discountAmount = $watch['mrp'] - $bestRetailer['price'];

    if ($discountAmount > 0) {
        $discountPercent = max(1, round(($discountAmount / $watch['mrp']) * 100));
    }
}

// Load Images for Every Variant
$variantImages = [];

foreach ($variants as $variant) {
    $variantImages[$variant['id']] = [];

    $folder = WATCH_IMAGE_DIR . '/' . $variant['image_folder'];

    if (is_dir($folder)) {
        $files = glob($folder . '/*.webp');
        natsort($files);

        foreach ($files as $file) {
            $variantImages[$variant['id']][] =
                WATCH_IMAGE_URL . '/' .
                $variant['image_folder'] . '/' .
                basename($file);
        }
    }

    if (empty($variantImages[$variant['id']])) {
        $variantImages[$variant['id']][] = DEFAULT_WATCH_IMAGE;
    }

    // Generic guide is always the final gallery image.
    $variantImages[$variant['id']][] = WATCH_CASE_SIZE_GUIDE_IMAGE;
}

$productSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',

    'name' => $page_title,
    'url' => $page_canonical,
    'description' => $page_description,

    'brand' => [
        '@type' => 'Brand',
        'name' => $watch['brand']
    ],

    'offers' => []
];

foreach ($currentRetailers as $retailer) {

    if (!$retailer['is_available'] || $retailer['price'] === null || empty($retailer['base_url'])) {
        continue;
    }

    $productSchema['offers'][] = [
        '@type' => 'Offer',
        'url' => $retailer['affiliate_url'] ?: $retailer['base_url'],
        'priceCurrency' => $retailer['currency'] ?: 'INR',
        'price' => number_format((float) $retailer['price'], 2, '.', ''),
        'availability' => 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/NewCondition'
    ];
}

if (empty($productSchema['offers'])) {
    unset($productSchema['offers']);
}

if (!empty($productImages)) {
    $productSchema['image'] = array_values($productImages);
}

if (!empty($watch['model_name'])) {
    $productSchema['model'] = $watch['model_name'];
}

$breadcrumbSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => SITE_URL . '/'
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => $watch['brand'],
            'item' => SITE_URL . '/?brand[]=' . urlencode($watch['brand'])
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => $page_title,
            'item' => $page_canonical
        ]
    ]
];

$productQuickLinks = [];

if (isset($quickLinks) && is_array($quickLinks)) {

    foreach ($quickLinks as $slug => $quickLink) {

        $filters = $quickLink['filters'];
        $matches = true;

        if (isset($filters['brand'])) {
            $matches = $matches &&
                strtolower($watch['brand']) === strtolower($filters['brand']);
        }

        if (isset($filters['gender'])) {
            $productGender = strtolower($watch['gender']);
            $filterGender = strtolower($filters['gender']);

            $matches = $matches &&
                ($productGender === $filterGender || $productGender === 'unisex');
        }

        if (isset($filters['movement_type'])) {
            $matches = $matches &&
                strtolower($watch['movement_type']) === strtolower($filters['movement_type']);
        }

        if (isset($filters['max_price'])) {
            $matches = $matches &&
                $bestRetailer &&
                $bestRetailer['price'] !== null &&
                (float) $bestRetailer['price'] <= (float) $filters['max_price'];
        }

        if ($matches) {
            $productQuickLinks[$slug] = $quickLink;
        }
    }
}

include '../includes/header.php';
?>

<main class="site-main">
    <div class="site-container">
        <!-- Breadcrumb -->
        <nav class="product-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= SITE_URL ?>/">Home</a>

            <span class="breadcrumb-separator" aria-hidden="true">
                <i class="fa-solid fa-chevron-right"></i>
            </span>

            <a href="<?= SITE_URL ?>/?brand[]=<?= urlencode($watch['brand']) ?>">
                <?= htmlspecialchars($watch['brand']) ?>
            </a>

            <span class="breadcrumb-separator" aria-hidden="true">
                <i class="fa-solid fa-chevron-right"></i>
            </span>

            <span aria-current="page">
                <?= htmlspecialchars($watch['model_name']) ?>
            </span>
        </nav>

        <section class="product-page">
            <!-- Product Images -->
            <div class="product-gallery">

                <div class="watch-image image-frame">

                    <button
                        type="button"
                        class="gallery-prev"
                        aria-label="Previous image">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>

                    <img
                        id="main-image"
                        class="product-main-image"
                        src="<?= htmlspecialchars($images[0]) ?>"
                        alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name'] . ' Watch') ?>"
                        loading="lazy">

                    <button type="button" class="gallery-next" aria-label="Next image">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>

                </div>
            </div>

            <div class="product-content">
                <!-- Product Information -->
                <div class="product-details">
                    <p class="product-brand"><?= htmlspecialchars($watch['brand']) ?></p>
                    <h1 class="product-title"><?= htmlspecialchars($watch['model_name']) ?></h1>

                    <!-- Product Statuses -->
                    <?php if (
                        $watch['is_featured']
                        || $watch['owner_status'] === OWNER_STATUS_OWNED
                        || $watch['owner_status'] === OWNER_STATUS_INTERESTED
                    ): ?>

                        <div class="product-statuses">

                            <?php if ($watch['is_featured']): ?>
                                <span class="product-status product-status-featured">
                                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                                    <?= FEATURED_LABEL ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($watch['owner_status'] === OWNER_STATUS_OWNED): ?>
                                <span class="product-status product-status-owned">
                                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                                    <?= OWNER_COLLECTION_LABEL ?>
                                </span>
                            <?php elseif ($watch['owner_status'] === OWNER_STATUS_INTERESTED): ?>
                                <span class="product-status product-status-wishlist">
                                    <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
                                    <?= OWNER_WISHLIST_LABEL ?>
                                </span>
                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($productDescription)): ?>
                        <p class="product-description">
                            <?= htmlspecialchars($productDescription) ?>
                        </p>
                    <?php endif; ?>

                    <?php
                    $productIntro = '';

                    if (!empty($watch['gender'])) {
                        switch (strtolower($watch['gender'])) {
                            case 'men':
                                $productIntro .= "This men's watch";
                                break;

                            case 'women':
                                $productIntro .= "This women's watch";
                                break;

                            case 'unisex':
                                $productIntro .= "This unisex watch";
                                break;

                            default:
                                $productIntro .= "This " . strtolower($watch['gender']) . " watch";
                                break;
                        }
                    }

                    if (!empty($watch['movement_type'])) {
                        $productIntro .= " features a " . strtolower($watch['movement_type']) . " movement";
                    }

                    if (!empty($watch['case_diameter_mm'])) {
                        $productIntro .= " and a " . $watch['case_diameter_mm'] . " mm case";
                    }

                    $productIntro .= ".";
                    ?>

                    <p class="product-intro">
                        <?= htmlspecialchars($productIntro) ?>
                    </p>

                    <!-- Buying Options -->
                    <div class="buying-options">
                        <h3>Buying Options</h3>

                        <div id="buying-options-content">
                            <?php if ($bestRetailer): ?>

                                <div class="best-offer">
                                    <span class="best-offer-label">Best Price</span>

                                    <div class="best-offer-main">

                                        <div class="best-offer-info">
                                            <a href="<?= htmlspecialchars($bestRetailer['affiliate_url'] ?: $bestRetailer['base_url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="retailer-link">
                                                <?= htmlspecialchars($bestRetailer['retailer_name']) ?>
                                            </a>

                                            <div class="best-offer-pricing">
                                                <strong class="best-offer-price">
                                                    ₹<?= number_format($bestRetailer['price'], 0) ?>
                                                </strong>

                                                <?php if ($discountAmount > 0): ?>
                                                    <del class="mrp">
                                                        ₹<?= number_format($watch['mrp'], 0) ?>
                                                    </del>

                                                    <span class="discount-badge">
                                                        <?= $discountPercent ?>% off
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <a href="<?= htmlspecialchars($bestRetailer['affiliate_url'] ?: $bestRetailer['base_url']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="best-offer-button">
                                            View Deal
                                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                        </a>

                                    </div>

                                </div>

                                <?php if (count($currentRetailers) > 1): ?>
                                    <div class="other-retailers">
                                        <h4>Other Buying Options</h4>

                                        <?php
                                        $otherRetailers = array_filter($currentRetailers, function ($retailer) use ($bestRetailer) {
                                            return $retailer['id'] != $bestRetailer['id']
                                                && $retailer['is_available']
                                                && $retailer['price'] !== null
                                                && (!empty($retailer['base_url']) || !empty($retailer['affiliate_url']));
                                        });

                                        usort($otherRetailers, function ($a, $b) {
                                            return ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX);
                                        });

                                        foreach ($otherRetailers as $retailer):
                                        ?>
                                            <div class="retailer-row">
                                                <a href="<?= htmlspecialchars($retailer['affiliate_url'] ?: $retailer['base_url']) ?>"
                                                    target="_blank" rel="noopener noreferrer" class="retailer-link">
                                                    <?= htmlspecialchars($retailer['retailer_name']) ?>
                                                </a>

                                                <?php if ($retailer['price'] !== null): ?>
                                                    <span class="retailer-price">₹<?= number_format($retailer['price'], 0) ?></span>
                                                <?php endif; ?>
                                            </div>

                                        <?php endforeach; ?>

                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <p class="no-buy-options">This watch is currently unavailable to buy online.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Available Colors -->
                <?php if (!empty($colorVariants)): ?>
                    <div class="product-colors">
                        <h3>Available Colors</h3>

                        <div class="color-options">
                            <?php foreach ($colorVariants as $variant):
                                $color = ucwords(strtolower($variant['color_name']));
                                $watchColor = $COLOR_MAP[$color] ?? '#555555';
                            ?>

                                <button
                                    type="button"
                                    class="color-item <?= $variant['is_default'] ? 'active' : '' ?>"
                                    data-variant-id="<?= $variant['id'] ?>"
                                    data-color="<?= htmlspecialchars(strtolower(trim($variant['color_name']))) ?>">

                                    <span class="color-circle" style="background: <?= htmlspecialchars($watchColor) ?>"></span>

                                    <span class="color-name">
                                        <?= htmlspecialchars($color) ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php endif; ?>

                <!-- Top Highlights -->
                <?php
                $hasHighlights =
                    !empty($watch['case_diameter_mm']) ||
                    !empty($watch['case_material']) ||
                    !empty($watch['band_material']) ||
                    !empty($watch['warranty_years']) ||
                    !empty($watch['movement_type']) ||
                    !empty($watch['display_type']) ||
                    !empty($watch['water_resistance_atm']);
                ?>

                <?php if ($hasHighlights): ?>
                    <section class="top-highlights">
                        <button type="button" class="highlights-toggle" aria-expanded="false" aria-controls="highlights-content">
                            <span>Top Highlights</span>
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>

                        <div class="highlights-content" id="highlights-content" hidden>
                            <!-- Case Size -->
                            <?php if (!empty($watch['case_diameter_mm'])): ?>
                                <div class="highlight-item">
                                    <span>Case Size</span>
                                    <strong><?= $watch['case_diameter_mm'] ?> mm</strong>
                                </div>
                            <?php endif; ?>

                            <!-- Case Material -->
                            <?php if (!empty($watch['case_material'])): ?>
                                <div class="highlight-item">
                                    <span>Case Material</span>
                                    <strong><?= htmlspecialchars($watch['case_material']) ?></strong>
                                </div>
                            <?php endif; ?>

                            <!-- Strap Material -->
                            <?php if (!empty($watch['band_material'])): ?>
                                <div class="highlight-item">
                                    <span>Strap Material</span>
                                    <strong><?= htmlspecialchars($watch['band_material']) ?></strong>
                                </div>
                            <?php endif; ?>

                            <!-- Warranty -->
                            <?php if (!empty($watch['warranty_years'])): ?>
                                <div class="highlight-item">
                                    <span>Warranty</span>
                                    <strong>
                                        <?= $watch['warranty_years'] ?>
                                        <?= (float) $watch['warranty_years'] === 1.0 ? 'Year' : 'Years' ?>
                                    </strong>
                                </div>
                            <?php endif; ?>

                            <!-- Movement -->
                            <?php if (!empty($watch['movement_type'])): ?>
                                <div class="highlight-item">
                                    <span>Movement</span>
                                    <strong><?= ucfirst($watch['movement_type']) ?></strong>
                                </div>
                            <?php endif; ?>

                            <!-- Display -->
                            <?php if (!empty($watch['display_type'])): ?>
                                <div class="highlight-item">
                                    <span>Display</span>
                                    <strong><?= ucfirst($watch['display_type']) ?></strong>
                                </div>
                            <?php endif; ?>

                            <!-- Water Resistance -->
                            <?php if (!empty($watch['water_resistance_atm'])): ?>
                                <div class="highlight-item">
                                    <span>Water Resistance</span>
                                    <strong><?= $watch['water_resistance_atm'] ?> ATM</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </section>

        <!-- Product Data for JavaScript -->
        <script>
            // Default images
            let images = <?= json_encode($images) ?>;

            // Generic case-size guide
            const caseSizeGuideImage = <?= json_encode(WATCH_CASE_SIZE_GUIDE_IMAGE) ?>;

            // Product image alt text
            const productImageAlt = <?= json_encode($watch['brand'] . ' ' . $watch['model_name'] . ' Watch') ?>;

            // All variants
            const variants = <?= json_encode($variants) ?>;

            // Images grouped by variant
            const variantImages = <?= json_encode($variantImages) ?>;

            // Retailers grouped by color
            const variantsByColor = <?= json_encode($variantsByColor) ?>;

            // Watch MRP
            const mrp = <?= (float) $watch['mrp'] ?>;

            // Selected color
            const selectedColor = <?= json_encode($selectedColor) ?>;
        </script>

        <script src="<?= BASE_URL ?>/assets/js/details.js"></script>
    </div>

    <!-- Related Watch Guides -->
    <?php if (!empty($productQuickLinks)): ?>

        <section class="product-guides">
            <div class="site-container">

                <div class="product-guides-header">
                    <h2>Explore More Watch Guides</h2>
                    <p>Continue browsing related watch collections.</p>
                </div>

                <div class="quick-links-list">

                    <?php foreach ($productQuickLinks as $slug => $quickLink): ?>

                        <a class="quick-link-item"
                            href="<?= BASE_URL ?>/index.php?quick_link=<?= urlencode($slug) ?>">
                            <span><?= htmlspecialchars($quickLink['title']) ?></span>
                            <span class="quick-link-arrow" aria-hidden="true">→</span>
                        </a>

                    <?php endforeach; ?>

                </div>

            </div>
        </section>

    <?php endif; ?>
</main>

<?php include 'related.php'; ?>
<?php include '../includes/footer.php'; ?>
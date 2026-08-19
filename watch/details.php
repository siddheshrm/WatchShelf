<?php
require_once '../config/connection.php';
$COLOR_MAP = require_once '../config/colormap.php';
require_once '../config/app.php';

// Validate Request 
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid watch.");
}

$id = (int) $_GET['id'];

// Fetch Watch Details
$stmt = $conn->prepare("SELECT w.*, (SELECT image_folder FROM watch_variants WHERE watch_id = w.id AND is_default = 1 LIMIT 1) AS image_folder FROM watches w WHERE w.id = ? AND w.is_active = 1;");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Watch not found.");
}

$watch = $result->fetch_assoc();

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

if (empty($images)) {
    $images[] = DEFAULT_WATCH_IMAGE;
}

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
$og_url = SITE_URL . '/watch/details.php?id=' . $id;
$og_image = $images[0] ?? DEFAULT_WATCH_IMAGE;

$page_description = sprintf(
    "View details, specifications, prices, and availability for the %s %s on WatchShelf.",
    $watch['brand'],
    $watch['model_name']
);

$page_canonical = SITE_URL . '/watch/details.php?id=' . $id;
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
}

$productSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',

    'name' => $page_title,
    'image' => array_values($images),
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

if (!empty($images) && $images[0] !== DEFAULT_WATCH_IMAGE) {
    $productSchema['image'] = $images;
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
            <span>&gt;</span>

            <a href="<?= SITE_URL ?>/?brand[]=<?= urlencode($watch['brand']) ?>">
                <?= htmlspecialchars($watch['brand']) ?>
            </a>
            <span>&gt;</span>

            <span><?= htmlspecialchars($watch['model_name']) ?></span>
        </nav>

        <section class="product-page">
            <!-- Product Images -->
            <div class="product-gallery">
                <button type="button" class="gallery-prev">&#10094;</button>

                <div class="watch-image image-frame">
                    <img id="main-image" class="product-main-image" src="<?= htmlspecialchars($images[0]) ?>"
                        alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name'] . ' Watch') ?>" loading="lazy">
                </div>

                <button type="button" class="gallery-next">&#10095;</button>
            </div>

            <!-- Product Information -->
            <div class="product-details">
                <p class="product-brand"><?= htmlspecialchars($watch['brand']) ?></p>
                <h1 class="product-title"><?= htmlspecialchars($watch['model_name']) ?></h1>

                <?php if (!empty($productDescription)): ?>
                    <p class="product-description">
                        <?= htmlspecialchars($productDescription) ?>
                    </p>
                <?php endif; ?>

                <!-- Featured Status -->
                <?php if ($watch['is_featured']): ?>
                    <p class="product-badge featured"><?= FEATURED_LABEL ?></p>
                <?php endif; ?>

                <!-- Owner Status -->
                <?php if ($watch['owner_status'] === OWNER_STATUS_OWNED): ?>
                    <p class="product-badge owned"><?= OWNER_COLLECTION_LABEL ?></p>
                <?php elseif ($watch['owner_status'] === OWNER_STATUS_INTERESTED): ?>
                    <p class="product-badge wishlist"><?= OWNER_WISHLIST_LABEL ?></p>
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

                <!-- Top Highlights -->
                <div class="top-highlights">
                    <h3>Top Highlights</h3>

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
                            <strong><?= $watch['warranty_years'] ?> Years</strong>
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

                <!-- Buying Options -->
                <div class="buying-options">
                    <h3>Buying Options</h3>

                    <div id="buying-options-content">
                        <?php if ($bestRetailer): ?>

                            <div class="best-offer">
                                <p class="best-offer-text">Available at
                                    <a href="<?= htmlspecialchars($bestRetailer['affiliate_url'] ?: $bestRetailer['base_url']) ?>"
                                        target="_blank" rel="noopener noreferrer" class="retailer-link">
                                        <?= htmlspecialchars($bestRetailer['retailer_name']) ?>
                                    </a>
                                    for
                                    <strong>₹<?= number_format($bestRetailer['price'], 0) ?></strong>

                                    <?php if ($discountAmount > 0): ?>
                                        <span class="mrp">
                                            <del>₹<?= number_format($watch['mrp'], 0) ?></del>
                                        </span>
                                        <span class="discount-badge"><?= $discountPercent ?>% off</span>
                                    <?php endif; ?>
                                </p>
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

                <?php if (!empty($colorVariants)): ?>
                    <!-- Available Colors -->
                    <div class="product-colors">
                        <h3>Available Colors</h3>

                        <?php foreach ($colorVariants as $variant):
                            $color = ucwords(strtolower($variant['color_name']));
                            $watchColor = $COLOR_MAP[$color] ?? '#555555';
                        ?>

                            <button type="button" class="color-item <?= $variant['is_default'] ? 'active' : '' ?>"
                                data-variant-id="<?= $variant['id'] ?>"
                                data-color="<?= htmlspecialchars(strtolower(trim($variant['color_name']))) ?>">

                                <span class="color-circle" style="background-color: <?= htmlspecialchars($watchColor) ?>"></span>
                                <span><?= htmlspecialchars($color) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Product Data for JavaScript -->
        <script>
            // Default images
            let images = <?= json_encode($images) ?>;

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

                <h2>Explore More Watch Guides</h2>

                <div class="quick-links-list">

                    <?php foreach ($productQuickLinks as $slug => $quickLink): ?>

                        <a href="<?= BASE_URL ?>/index.php?quick_link=<?= urlencode($slug) ?>">
                            <?= htmlspecialchars($quickLink['title']) ?>
                        </a>

                    <?php endforeach; ?>

                </div>

            </div>
        </section>

    <?php endif; ?>
</main>

<?php include 'related.php'; ?>
<?php include '../includes/footer.php'; ?>
<?php
require_once '../config/connection.php';
require_once '../config/colormap.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid watch.");
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare(" SELECT * FROM watches WHERE id = ? AND is_active = 1");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Watch not found.");
}

$watch = $result->fetch_assoc();

// Fetch retailer buying options
$retailerStmt = $conn->prepare("SELECT retailer_name, base_url, affiliate_url, price, is_available FROM watch_retailers WHERE watch_id = ? ORDER BY display_order ASC");

$retailerStmt->bind_param("i", $id);
$retailerStmt->execute();

$retailerResult = $retailerStmt->get_result();

$retailers = [];
$bestRetailer = null;

while ($row = $retailerResult->fetch_assoc()) {
    // Skip retailers without a URL
    if (empty($row['base_url']) && empty($row['affiliate_url'])) {
        continue;
    }

    $retailers[] = $row;

    // Find the lowest available price
    if ($row['is_available'] && $row['price'] !== null && ($bestRetailer === null || $row['price'] < $bestRetailer['price'])) {
        $bestRetailer = $row;
    }
}

$page_title = $watch['brand'] . " " . $watch['model_name'];

// Calculate the discount from the watch's MRP
$discountAmount = 0;
$discountPercent = 0;

if ($bestRetailer !== null) {
    $discountAmount = $watch['mrp'] - $bestRetailer['price'];

    // Calculate the discount percentage, ensuring any valid discount displays at least 1%
    if ($discountAmount > 0) {
        $discountPercent = max(1, round(($discountAmount / $watch['mrp']) * 100));
    }
}

include '../includes/header.php';
?>

<?php
$images = [];

if (!empty($watch['image_folder'])) {
    for ($i = 1; $i <= MAX_WATCH_IMAGES; $i++) {
        $filename = sprintf('%d.webp', $i);

        if (file_exists(WATCH_IMAGE_DIR . '/' . $watch['image_folder'] . '/' . $filename)) {
            $images[] = WATCH_IMAGE_URL . '/' . $watch['image_folder'] . '/' . $filename;
        }
    }
}

// Fall back to the default image if no product images are found
if (empty($images)) {
    $images[] = DEFAULT_WATCH_IMAGE;
}
?>

<main>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="../index.php">Home</a>
        <span>&gt;</span>

        <a href="../index.php?brand[]=<?= urlencode($watch['brand']) ?>">
            <?= htmlspecialchars($watch['brand']) ?>
        </a>
        <span>&gt;</span>

        <span><?= htmlspecialchars($watch['model_name']) ?></span>
    </nav>

    <section class="product-page">
        <?php
        $hasMultipleImages = count($images) > 1;
        ?>

        <div class="product-image">
            <?php if ($hasMultipleImages): ?>
                <button type="button" class="prev-image">&#10094;</button>
            <?php endif; ?>

            <img id="main-image" src="<?= htmlspecialchars($images[0]) ?>"
                alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name']) ?>" loading="lazy">

            <?php if ($hasMultipleImages): ?>
                <button type="button" class="next-image">&#10095;</button>
            <?php endif; ?>
        </div>

        <div class="product-details">
            <h1><?= htmlspecialchars($watch['brand']) ?></h1>
            <h2><?= htmlspecialchars($watch['model_name']) ?></h2>

            <?php if ($watch['is_featured']): ?>
                <p><?= FEATURED_LABEL ?></p>
            <?php endif; ?>

            <?php if ($watch['owner_status'] === OWNER_STATUS_OWNED): ?>
                <p><?= OWNER_COLLECTION_LABEL ?></p>
            <?php elseif ($watch['owner_status'] === OWNER_STATUS_INTERESTED): ?>
                <p><?= OWNER_WISHLIST_LABEL ?></p>
            <?php endif; ?>

            <p><?= ucfirst($watch['gender']) ?></p>

            <div class="top-highlights">
                <h3>Top Highlights</h3>

                <!-- Case Diameter -->
                <?php if (!empty($watch['case_diameter_mm'])): ?>
                    <div class="highlight-item">
                        <span>Case Diameter</span>
                        <strong><?= $watch['case_diameter_mm'] ?> millimetres</strong>
                    </div>
                <?php endif; ?>

                <!-- Case Material -->
                <?php if (!empty($watch['case_material'])): ?>
                    <div class="highlight-item">
                        <span>Case Material</span>
                        <strong><?= htmlspecialchars($watch['case_material']) ?></strong>
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

            <div class="buying-options">
                <h3>Buying Options</h3>

                <?php if ($bestRetailer): ?>

                    <div class="best-price">
                        <p class="best-price-text">
                            Best price found on
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
                                <span class="discount-badge">
                                    <?= $discountPercent ?>% off
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (count($retailers) > 1): ?>
                        <div class="other-retailers">
                            <h4>Other Buying Options</h4>

                            <?php foreach ($retailers as $retailer): ?>

                                <?php
                                if ($retailer['retailer_name'] === $bestRetailer['retailer_name']) {
                                    continue;
                                }
                                ?>

                                <div class="retailer-row">
                                    <a href="<?= htmlspecialchars($retailer['affiliate_url'] ?: $retailer['base_url']) ?>"
                                        target="_blank" rel="noopener noreferrer" class="retailer-link">
                                        <?= htmlspecialchars($retailer['retailer_name']) ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="no-buy-options">
                        This watch is currently unavailable to buy online.
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($watch['available_colors'])): ?>
                <div class="product-colors">
                    <h3>Available In Colors</h3>

                    <?php
                    $colors = array_map('trim', explode(',', $watch['available_colors']));
                    $colors = array_filter($colors);

                    $colors = array_map(function ($color) {
                        return ucwords(strtolower($color));
                    }, $colors);

                    foreach ($colors as $color):
                        $swatchColor = $COLOR_MAP[$color] ?? '#555555';
                        ?>
                        <li class="color-item">
                            <span class="color-circle" style="background-color: <?= $swatchColor; ?>;"></span>
                            <span><?= htmlspecialchars($color); ?></span>
                        </li>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasMultipleImages): ?>
            <script>
                const images = <?= json_encode($images) ?>;
            </script>

            <script src="<?= BASE_URL ?>/assets/js/details.js"></script>
        <?php endif; ?>
    </section>
</main>

<?php include 'related.php'; ?>
<?php include '../includes/footer.php'; ?>
<?php
require_once 'config/connection.php';
require_once 'watch/search.php';
require_once 'includes/sidebar.php';
require_once 'config/app.php';

$quickLinks = require_once 'config/quick-links.php';

// Page configuration
$page_title = "Home";

// Sorting
$sort = $_GET['sort'] ?? 'featured';

switch ($sort) {
    case 'price_low':
        $orderBy = "min_price ASC";
        break;

    case 'price_high':
        $orderBy = "min_price DESC";
        break;

    default:
        $orderBy = "w.is_featured DESC, min_price ASC, w.brand ASC, w.model_name ASC";
}

// Availability
$includeOutOfStock = isset($_GET['include_out_of_stock']);

// Determine which set of watches to display
// Priority: Search > Quick Link > Filters/Default

// Check if a search query was submitted and is not empty
if (isset($_GET['search_submit']) && !empty(trim($_GET['search'] ?? ''))) {
    $watchIds = searchWatches($conn, $_GET['search']);

} elseif (isset($_GET['quick_link']) && isset($quickLinks[$_GET['quick_link']])) {
    // Check if a valid quick link was selected
    $quickLink = $quickLinks[$_GET['quick_link']];
    $page_title = $quickLink['title'];

    $watchIds = filterQuickLinkWatches($conn, $quickLink['filters']);
} else {
    // Check if a valid quick link was selected
    $watchIds = filterWatches($conn, $_GET);
}
if (empty($watchIds)) {
    $watchResult = false;
} else {
    // Display watches using user-selected filters, or all watches when no filters are applied
    $placeholders = implode(',', array_fill(0, count($watchIds), '?'));

    $sql = "SELECT w.*, (SELECT image_folder FROM watch_variants dv WHERE dv.watch_id = w.id AND dv.is_default = 1 LIMIT 1) AS default_image_folder, MIN(CASE WHEN wr.is_available = 1 AND wr.price IS NOT NULL THEN wr.price END) AS min_price, MAX(wr.is_available) AS has_stock, COUNT(DISTINCT wr.color_name) AS color_count, COUNT(*) AS variant_count
                FROM watches w
                LEFT JOIN watch_variants wr
                ON wr.watch_id = w.id
                WHERE w.id IN ($placeholders)
                AND w.is_active = 1
                GROUP BY w.id";

    if (!$includeOutOfStock) {
        $sql .= " HAVING has_stock = 1 ";
    }

    $sql .= " ORDER BY $orderBy";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die($conn->error . "<br><br>" . $sql);
    }

    // Bind all watch IDs to the IN() clause
    $types = str_repeat('i', count($watchIds));
    $stmt->bind_param($types, ...$watchIds);

    $stmt->execute();
    $watchResult = $stmt->get_result();
}

include 'includes/header.php';
?>

<main>
    <!-- Hero -->
    <section id="hero">
        <h1>Find Your Perfect Budget Watch</h1>
        <p>Explore budget watches, compare specifications, and find the best available prices.</p>
    </section>

    <div class="page-layout">
        <!-- Catalog -->
        <section id="watch-list">
            <div class="catalog-toolbar">
                <h2>Available Watches</h2>

                <form action="index.php" method="GET" class="sort-form">
                    <!-- Preserve the current search and filter state by copying existing GET parameters into hidden inputs, excluding the sort parameter -->
                    <?php
                    foreach ($_GET as $key => $value) {
                        // Skip the current sort parameter so the selected value replaces it
                        if ($key === 'sort') {
                            continue;
                        }

                        // Check whether the parameter is an array
                        if (is_array($value)) {
                            foreach ($value as $item) {
                                ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($item) ?>">
                                <?php
                            }
                        } else {
                            ?>
                            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php
                        }
                    }
                    ?>

                    <label for="sort">Sort By</label>

                    <select id="sort" name="sort" onchange="this.form.submit()">
                        <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured First</option>
                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                    </select>
                </form>
            </div>

            <div class="watch-grid">
                <?php if ($watchResult && $watchResult->num_rows > 0): ?>
                    <!-- Display the matching watches -->
                    <?php while ($watch = $watchResult->fetch_assoc()): ?>

                        <?php
                        $imagePath = DEFAULT_WATCH_IMAGE;

                        if (!empty($watch['image_folder'])) {
                            for ($i = 1; $i <= MAX_WATCH_IMAGES; $i++) {
                                $relativeFile = sprintf('%s/%d.webp', $watch['image_folder'], $i);

                                if (file_exists(WATCH_IMAGE_DIR . '/' . $relativeFile)) {
                                    $imagePath = WATCH_IMAGE_URL . '/' . $relativeFile;
                                    break;
                                }
                            }
                        }
                        ?>

                        <article class="watch-card">
                            <a href="<?= BASE_URL ?>/watch/details.php?id=<?= $watch['id'] ?>">
                                <?php
                                $imagePath = DEFAULT_WATCH_IMAGE;

                                if (!empty($watch['default_image_folder'])) {
                                    $imagePath = WATCH_IMAGE_URL . '/' . $watch['default_image_folder'] . '/1.webp';
                                }
                                ?>
                                <img src="<?= htmlspecialchars($imagePath) ?>"
                                    alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name']) ?>" loading="lazy"
                                    onerror="this.onerror=null;this.src='<?= htmlspecialchars(DEFAULT_WATCH_IMAGE) ?>';">

                                <?php if ($watch['is_featured']): ?>
                                    <p><?= FEATURED_LABEL ?></p>
                                <?php endif; ?>

                                <h3><?= htmlspecialchars($watch['brand']) ?></h3>s
                                <h4><?= htmlspecialchars($watch['model_name']) ?></h4>

                                <?php if ($watch['owner_status'] === OWNER_STATUS_OWNED): ?>
                                    <p><?= OWNER_COLLECTION_LABEL ?></p>
                                <?php elseif ($watch['owner_status'] === OWNER_STATUS_INTERESTED): ?>
                                    <p><?= OWNER_WISHLIST_LABEL ?></p>
                                <?php endif; ?>

                                <?php if ($watch['min_price'] !== null && $watch['min_price'] > 0): ?>
                                    <p>From ₹<?= number_format($watch['min_price']) ?></p>
                                <?php else: ?>
                                    <p>Currently unavailable to buy</p>
                                <?php endif; ?>
                            </a>
                            <button type="button"
                                onclick="window.location.href='<?= BASE_URL ?>/watch/details.php?id=<?= $watch['id'] ?>'">Buying Options</button>
                        </article>
                    <?php endwhile; ?>

                <?php else: ?>
                    <p>No watches available.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/connection.php';
require_once 'watch/search.php';
require_once 'includes/sidebar-data.php';
require_once 'config/app.php';

$quickLinks = require_once 'config/quick-links.php';

// Page configuration
$page_title = "Home";
$page_description = "Discover and compare budget-friendly watches from popular brands and retailers in India.";
$page_canonical = SITE_URL . '/';
$page_robots = 'index, follow';

// Sorting
$sort = $_GET['sort'] ?? 'featured';

switch ($sort) {
    case 'price_low':
        $orderBy = "min_price ASC, w.brand ASC, w.model_name ASC, w.id ASC";
        break;

    case 'price_high':
        $orderBy = "min_price DESC, w.brand ASC, w.model_name ASC, w.id ASC";
        break;

    default:
        $orderBy = "w.is_featured DESC, min_price ASC, w.brand ASC, w.model_name ASC, w.id ASC";
}

// Availability
$includeOutOfStock = isset($_GET['include_out_of_stock']);

// Pagination
$productsPerPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

function buildPaginationUrl(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;

    return 'index.php?' . http_build_query($params);
}

// Determine which set of watches to display
// Priority: Search > Quick Link > Filters/Default

// Check if a search query was submitted and is not empty
if (isset($_GET['search_submit']) && !empty(trim($_GET['search'] ?? ''))) {
    $watchIds = searchWatches($conn, $_GET['search']);

    $searchQuery = trim($_GET['search']);

    // Search result pages are not canonical landing pages.
    $page_title = "Search Results for \"" . $searchQuery . "\"";

    $catalog_heading = "Search Results for \"" . $searchQuery . "\"";
    $catalog_intro = '';

    $page_robots = 'noindex, follow';
    $page_canonical = SITE_URL . '/';

    $og_type = 'website';
    $og_title = 'Search Results | WatchShelf';
    $og_description = 'Search and compare budget-friendly watches on WatchShelf.';
    $og_url = SITE_URL . '/';
} elseif (isset($_GET['quick_link']) && isset($quickLinks[$_GET['quick_link']])) {

    // Apply quick-link catalogue logic
    $quickLink = $quickLinks[$_GET['quick_link']];

    $page_title = $quickLink['title'];
    $page_description = $quickLink['description'] ?? "Discover {$quickLink['title']} on WatchShelf.";
    $page_canonical = SITE_URL . '/?quick_link=' . urlencode($_GET['quick_link']);
    $page_robots = 'index, follow';

    $og_type = 'website';
    $og_title = $page_title;
    $og_description = $page_description;
    $og_url = $page_canonical;

    $catalog_heading = $quickLink['title'];
    $catalog_intro = $quickLink['description'] ?? '';

    $watchIds = filterQuickLinkWatches($conn, $quickLink['filters']);
} else {
    $watchIds = filterWatches($conn, $_GET);

    $hasCatalogueFilters =
        isset($_GET['gender']) ||
        isset($_GET['brand']) ||
        isset($_GET['retailer']) ||
        isset($_GET['color']) ||
        isset($_GET['movement']) ||
        isset($_GET['include_out_of_stock']) ||
        isset($_GET['max_price']) ||
        isset($_GET['sort']);

    if ($hasCatalogueFilters) {
        // Generic search/filter/sort combinations are not canonical landing pages.
        $page_robots = 'noindex, follow';
        $page_canonical = SITE_URL . '/';

        $og_type = 'website';
        $og_title = 'WatchShelf';
        $og_description = 'Discover and compare budget-friendly watches on WatchShelf.';
        $og_url = SITE_URL . '/';
    } else {
        // Homepage / default catalogue
        $page_robots = 'index, follow';
        $page_canonical = SITE_URL . '/';

        $og_type = 'website';
        $og_title = 'WatchShelf';
        $og_description = $page_description;
        $og_url = SITE_URL . '/';
    }

    $catalog_heading = 'Find Your Perfect Budget Watch';
    $catalog_intro = '';
}

if (empty($watchIds)) {
    $watchResult = false;
    $totalPages = 0;
} else {
    $totalWatches = count($watchIds);
    $totalPages = (int) ceil($totalWatches / $productsPerPage);

    // Prevent invalid page numbers from requesting an empty page.
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    // Sort the complete result set before pagination.
    // This ensures sorting is applied consistently across all pages.
    $placeholders = implode(',', array_fill(0, count($watchIds), '?'));

    $sortSql = "SELECT w.id, w.is_featured, MIN(CASE WHEN wr.is_available = 1 AND wr.price IS NOT NULL THEN wr.price END) AS min_price
                        FROM watches w
                        LEFT JOIN watch_variants wr
                        ON wr.watch_id = w.id
                        WHERE w.id IN ($placeholders)
                        AND w.is_active = 1
                        GROUP BY w.id";

    if (!$includeOutOfStock) {
        $sortSql .= " HAVING MAX(wr.is_available) = 1 ";
    }

    $sortSql .= " ORDER BY $orderBy";

    $stmt = $conn->prepare($sortSql);

    if (!$stmt) {
        die($conn->error . "<br><br>" . $sortSql);
    }

    $types = str_repeat('i', count($watchIds));
    $stmt->bind_param($types, ...$watchIds);

    $stmt->execute();

    $sortedResult = $stmt->get_result();

    $sortedWatchIds = [];

    while ($row = $sortedResult->fetch_assoc()) {
        $sortedWatchIds[] = (int) $row['id'];
    }

    $stmt->close();

    // Paginate the globally sorted result set
    $offset = ($currentPage - 1) * $productsPerPage;

    $pageWatchIds = array_slice(
        $sortedWatchIds,
        $offset,
        $productsPerPage
    );

    // Fetch the complete product data for the current page
    $pagePlaceholders = implode(',', array_fill(0, count($pageWatchIds), '?'));

    $sql = "SELECT w.*, (SELECT image_folder FROM watch_variants dv WHERE dv.watch_id = w.id AND dv.is_default = 1 LIMIT 1) AS default_image_folder, MIN(CASE WHEN wr.is_available = 1 AND wr.price IS NOT NULL THEN wr.price END) AS min_price, MAX(wr.is_available) AS has_stock, COUNT(DISTINCT wr.color_name) AS color_count, COUNT(*) AS variant_count
                FROM watches w
                LEFT JOIN watch_variants wr
                ON wr.watch_id = w.id
                WHERE w.id IN ($pagePlaceholders)
                AND w.is_active = 1
                GROUP BY w.id";

    if (!$includeOutOfStock) {
        $sql .= " HAVING has_stock = 1 ";
    }

    // The IDs have already been globally sorted.
    // FIELD() preserves that exact order for the final product result.
    $idOrder = implode(',', array_map('intval', $pageWatchIds));

    $sql .= " ORDER BY FIELD(w.id, $idOrder)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die($conn->error . "<br><br>" . $sql);
    }

    $types = str_repeat('i', count($pageWatchIds));
    $stmt->bind_param($types, ...$pageWatchIds);

    $stmt->execute();
    $watchResult = $stmt->get_result();
}

include 'includes/header.php';
?>

<main class="site-main">
    <section class="page-hero">
        <div class="site-container">
            <h1><?= htmlspecialchars($catalog_heading) ?></h1>

            <?php if (!empty($catalog_intro)): ?>
                <p><?= htmlspecialchars($catalog_intro) ?></p>
            <?php else: ?>
                <p>Explore budget watches, compare specifications, and find the best available prices.</p>
            <?php endif; ?>
        </div>
    </section>

    <div class="site-container">
        <div class="catalog-layout">
            <?php require_once 'includes/sidebar.php'; ?>

            <section class="watch-catalog">
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
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>[]"
                                        value="<?= htmlspecialchars($item) ?>">
                                <?php
                                }
                            } else {
                                ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>"
                                    value="<?= htmlspecialchars($value) ?>">
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
                                <a class="watch-card-link" href="<?= BASE_URL ?>/watch/details.php?id=<?= $watch['id'] ?>">
                                    <?php
                                    $imagePath = DEFAULT_WATCH_IMAGE;

                                    if (!empty($watch['default_image_folder'])) {
                                        $imagePath = WATCH_IMAGE_URL . '/' . $watch['default_image_folder'] . '/1.webp';
                                    }
                                    ?>
                                    <div class="watch-image image-frame">
                                        <img src="<?= htmlspecialchars($imagePath) ?>"
                                            alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= htmlspecialchars(DEFAULT_WATCH_IMAGE) ?>';">
                                    </div>

                                    <?php if ($watch['is_featured']): ?>
                                        <p><?= FEATURED_LABEL ?></p>
                                    <?php endif; ?>

                                    <h3><?= htmlspecialchars($watch['brand']) ?></h3>
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
                                    onclick="window.location.href='<?= BASE_URL ?>/watch/details.php?id=<?= $watch['id'] ?>'">
                                    Buying Options
                                </button>
                            </article>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <p>No watches available.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Catalogue pagination">

                        <?php if ($currentPage > 1): ?>
                            <a class="pagination-link"
                                href="<?= htmlspecialchars(buildPaginationUrl($currentPage - 1)) ?>"
                                aria-label="Previous page">
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                            <?php if ($page === $currentPage): ?>

                                <span class="pagination-link active" aria-current="page">
                                    <?= $page ?>
                                </span>

                            <?php else: ?>

                                <a class="pagination-link"
                                    href="<?= htmlspecialchars(buildPaginationUrl($page)) ?>">
                                    <?= $page ?>
                                </a>

                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a class="pagination-link"
                                href="<?= htmlspecialchars(buildPaginationUrl($currentPage + 1)) ?>"
                                aria-label="Next page">
                                Next
                            </a>
                        <?php endif; ?>

                    </nav>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- Related Quick Links for internal navigation between SEO landing pages -->
    <?php if (!empty($quickLink['related'])): ?>

        <section class="related-quick-links">
            <div class="site-container">
                <h2>Explore More Watch Guides</h2>

                <div class="quick-links-list">

                    <?php foreach ($quickLink['related'] as $relatedSlug): ?>

                        <?php if (!isset($quickLinks[$relatedSlug])) {
                            continue;
                        } ?>

                        <a href="<?= BASE_URL ?>/index.php?quick_link=<?= urlencode($relatedSlug) ?>">
                            <?= htmlspecialchars($quickLinks[$relatedSlug]['title']) ?>
                        </a>

                    <?php endforeach; ?>
                </div>

            </div>
        </section>

    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
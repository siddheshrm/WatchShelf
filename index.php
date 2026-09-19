<?php

require_once 'config/app.php';
require_once 'config/connection.php';
require_once 'watch/search.php';
require_once 'includes/sidebar-data.php';

$quickLinks = require_once 'config/quick-links.php';

/* PAGE CONFIGURATION */

$page_title = "Home";
$page_description = "Discover and compare budget-friendly watches from popular brands and retailers in India.";
$page_canonical = SITE_URL . '/';
$page_robots = 'index, follow';

$og_type = 'website';
$og_title = 'WatchShelf';
$og_description = $page_description;
$og_url = SITE_URL . '/';

$catalog_heading = 'Find Your Perfect Budget Watch';
$catalog_intro = '';

/* SORTING */
/* Sorting only controls the order of an already-established eligible watch set.
    It must never determine which watches qualify. */

$allowedSorts = ['featured', 'price_low', 'price_high'];

$sort = $_GET['sort'] ?? 'featured';

if (!is_string($sort) || !in_array($sort, $allowedSorts, true)) {
    $sort = 'featured';
}

$minPriceSql = "MIN(CASE WHEN wr.is_available = 1
                                            AND wr.price IS NOT NULL
                                            AND wr.price > 0
                                            THEN wr.price
                                    END)";

switch ($sort) {
    case 'price_low':
        $orderBy = "CASE WHEN $minPriceSql IS NULL THEN 1 ELSE 0 END ASC,
                             $minPriceSql ASC, w.brand ASC, w.model_name ASC, w.id ASC";
        break;

    case 'price_high':
        $orderBy = "CASE WHEN $minPriceSql IS NULL THEN 1 ELSE 0 END ASC,
                            $minPriceSql DESC, w.brand ASC, w.model_name ASC, w.id ASC";
        break;

    case 'featured':
    default:
        $orderBy = "w.is_featured DESC,
                            CASE WHEN $minPriceSql IS NULL THEN 1 ELSE 0 END ASC,
                            $minPriceSql ASC, w.brand ASC, w.model_name ASC, w.id ASC";
        break;
}

/* PAGINATION */

$productsPerPage = 36;
$currentPage = 1;

if (isset($_GET['page'])) {
    $requestedPage = $_GET['page'];

    // Reject malformed page parameters.
    if (
        !is_string($requestedPage) ||
        !ctype_digit($requestedPage) ||
        (int) $requestedPage < 1 ||
        (string) (int) $requestedPage !== $requestedPage
    ) {
        require __DIR__ . '/404.php';
        exit;
    }

    $currentPage = (int) $requestedPage;

    // Remove redundant page=1 while preserving all other query parameters.
    if ($currentPage === 1) {
        $params = $_GET;
        unset($params['page']);

        $redirectUrl = BASE_URL . '/';

        if (!empty($params)) {
            $redirectUrl .= '?' . http_build_query($params);
        }

        header('Location: ' . $redirectUrl, true, 301);
        exit;
    }
}

// Builds pagination URLs while preserving the current request parameters.
function buildPaginationUrl(int $page): string
{
    $params = $_GET;

    if ($page <= 1) {
        unset($params['page']);
    } else {
        $params['page'] = $page;
    }

    $query = http_build_query($params);

    return BASE_URL . '/' . ($query !== '' ? '?' . $query : '');
}

// Generates compact pagination items.
function getPaginationItems(int $currentPage, int $totalPages, int $radius = 1): array
{
    if ($totalPages <= 1) {
        return [];
    }

    $pages = [1, $totalPages];

    $startPage = max(1, $currentPage - $radius);
    $endPage = min($totalPages, $currentPage + $radius);

    for ($page = $startPage; $page <= $endPage; $page++) {
        $pages[] = $page;
    }

    $pages = array_values(array_unique($pages));
    sort($pages);

    $items = [];
    $previousPage = null;

    foreach ($pages as $page) {
        if ($previousPage !== null && $page > $previousPage + 1) {
            $items[] = 'ellipsis';
        }

        $items[] = $page;
        $previousPage = $page;
    }

    return $items;
}

// Redirects the current request when it contains parameters that are not valid for the active catalogue mode.
function normalizeCatalogueRequest(array $allowedKeys): void
{
    $normalizedParams = [];

    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $_GET)) {
            $normalizedParams[$key] = $_GET[$key];
        }
    }

    if ($normalizedParams === $_GET) {
        return;
    }

    $redirectUrl = BASE_URL . '/';

    if (!empty($normalizedParams)) {
        $redirectUrl .= '?' . http_build_query($normalizedParams);
    }

    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

/* CATALOGUE REQUEST STATE */

$paginationItems = [];

$emptyResult = false;
$emptyResultType = null;

$showResultCount = false;

/* This describes the eligibility policy established by the request source.
    Default / Sidebar: false unless sidebar explicitly enables out-of-stock.
    Search / Quick Link: true by design.
    Sorting must not change this value. */

$includeOutOfStock = false;

/* IMPORTANT: `sort` is deliberately NOT included here.
    /?sort=price_low is still the default catalogue, merely ordered differently. */

$hasValidMaxPrice =
    isset($_GET['max_price']) &&
    is_string($_GET['max_price']) &&
    is_numeric($_GET['max_price']) &&
    (float) $_GET['max_price'] > 0;

$hasIncludeOutOfStock =
    isset($_GET['include_out_of_stock']) &&
    is_string($_GET['include_out_of_stock']) &&
    $_GET['include_out_of_stock'] === '1';

$hasCatalogueFilters =
    !empty($selectedGenders) ||
    !empty($selectedBrands) ||
    !empty($selectedRetailers) ||
    !empty($selectedColors) ||
    !empty($selectedMovement) ||
    !empty($selectedCaseWidths) ||
    $hasIncludeOutOfStock ||
    $hasValidMaxPrice;

$catalogueMode = 'default';

/* Determine catalogue eligibility
    Each branch below has one responsibility:
    Determine WHICH watch IDs belong in the catalogue.
    Nothing below the eligibility stage should remove watches. */

/* SEARCH */
$searchQuery = '';

if (isset($_GET['search']) && is_string($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
}

/* QUICK LINK */
if (isset($_GET['quick_link']) && is_string($_GET['quick_link']) && isset($quickLinks[$_GET['quick_link']])) {
    $catalogueMode = 'quick_link';

    normalizeCatalogueRequest(['quick_link', 'sort', 'page']);

    $quickLink = $quickLinks[$_GET['quick_link']];

    /* Quick Links are curated/discovery collections.
       Matching out-of-stock products are intentionally retained. */
    $includeOutOfStock = true;

    $watchIds = filterQuickLinkWatches(
        $conn,
        $quickLink['filters']
    );

    if (!empty($watchIds)) {
        $showResultCount = true;
    }

    $page_title = $quickLink['title'];

    $page_description =
        $quickLink['description']
        ?? "Discover {$quickLink['title']} on WatchShelf.";

    $page_robots = 'index, follow';

    $page_canonical =
        SITE_URL
        . '/?quick_link='
        . urlencode($_GET['quick_link']);

    $og_type = 'website';
    $og_title = $page_title;
    $og_description = $page_description;
    $og_url = $page_canonical;

    $catalog_heading = $quickLink['title'];
    $catalog_intro = $quickLink['description'] ?? '';

    /* SEARCH */
} elseif (isset($_GET['search_submit']) && $searchQuery !== '') {
    $catalogueMode = 'search';

    normalizeCatalogueRequest(['search', 'search_submit', 'sort', 'page']);

    /* Search is discovery-oriented.
       Matching out-of-stock watches are allowed. */
    $includeOutOfStock = true;

    $watchIds = searchWatches(
        $conn,
        $searchQuery
    );

    /* Empty search:
       Preserve the search URL/message, but display the normal
       available catalogue as fallback content. */
    if (empty($watchIds)) {
        $emptyResult = true;
        $emptyResultType = 'search';

        $includeOutOfStock = false;

        $watchIds = filterWatches($conn, []);
    } else {
        $showResultCount = true;
    }

    $page_title = 'Search Results for "' . $searchQuery . '"';

    $catalog_heading = 'Search Results for "' . $searchQuery . '"';
    $catalog_intro = '';

    $page_robots = 'noindex, follow';
    $page_canonical = SITE_URL . '/';

    $og_type = 'website';
    $og_title = 'Search Results | WatchShelf';
    $og_description =
        'Search and compare budget-friendly watches on WatchShelf.';
    $og_url = SITE_URL . '/';

    /* SIDEBAR FILTERS */
} elseif ($hasCatalogueFilters) {
    $catalogueMode = 'filter';

    normalizeCatalogueRequest(['gender', 'brand', 'retailer', 'color', 'movement', 'case_width', 'include_out_of_stock', 'max_price', 'filter_submit', 'sort', 'page']);

    // This is the only request source where the user explicitly controls out-of-stock inclusion.
    $includeOutOfStock = $hasIncludeOutOfStock;

    $filterParams = $_GET;

    if (!$hasIncludeOutOfStock) {
        unset($filterParams['include_out_of_stock']);
    }

    /* Invalid max_price values must not reach filterWatches(). */
    if (!$hasValidMaxPrice) {
        unset($filterParams['max_price']);
    }

    $watchIds = filterWatches($conn, $filterParams);

    /* Empty filter:
       Preserve filter URL/message while displaying the normal
       available catalogue as fallback content. */
    if (empty($watchIds)) {
        $emptyResult = true;
        $emptyResultType = 'filter';

        $includeOutOfStock = false;

        $watchIds = filterWatches($conn, []);
    } else {
        $showResultCount = true;
    }

    $page_robots = 'noindex, follow';
    $page_canonical = SITE_URL . '/';

    $og_type = 'website';
    $og_title = 'WatchShelf';
    $og_description = 'Discover and compare budget-friendly watches on WatchShelf.';
    $og_url = SITE_URL . '/';

    $catalog_heading = 'Find Your Perfect Budget Watch';
    $catalog_intro = '';

    /* DEFAULT CATALOGUE */
} else {
    $catalogueMode = 'default';

    $allowedDefaultKeys = ['sort', 'page'];

    if ($sort === 'featured') {
        $allowedDefaultKeys = ['page'];
    }

    normalizeCatalogueRequest($allowedDefaultKeys);

    $includeOutOfStock = false;

    $watchIds = filterWatches($conn, []);

    $page_robots = 'index, follow';
    $page_canonical = SITE_URL . '/';

    $og_type = 'website';
    $og_title = 'WatchShelf';
    $og_description = $page_description;
    $og_url = SITE_URL . '/';

    $catalog_heading = 'Find Your Perfect Budget Watch';
    $catalog_intro = '';

    if ($sort !== 'featured') {
        $page_robots = 'noindex, follow';
        $page_canonical = SITE_URL . '/';
    }
}

/* EMPTY CATALOGUE */

if (empty($watchIds)) {
    $watchResult = false;

    $totalWatches = 0;
    $totalPages = 0;
} else {
    $totalWatches = count($watchIds);

    $totalPages = (int) ceil(
        $totalWatches / $productsPerPage
    );

    /* Reject catalogue page numbers beyond the eligible result set. */
    if ($currentPage > $totalPages) {
        require __DIR__ . '/404.php';
        exit;
    }

    /* SORT VARIANT SEO
   Non-default sorting changes presentation only and must not create
   separately indexable catalogue URLs. */
    if (
        $sort !== 'featured' &&
        in_array($catalogueMode, ['default', 'quick_link'], true)
    ) {
        $page_robots = 'noindex, follow';
    }

    /* PAGINATED SEO METADATA */

    if ($currentPage > 1) {
        if ($catalogueMode === 'default') {
            $page_title = "Budget Watches - Page {$currentPage}";
            $page_canonical = SITE_URL . '/?page=' . $currentPage;

            $og_title = "Budget Watches - Page {$currentPage} | WatchShelf";
            $og_url = $page_canonical;
        } elseif ($catalogueMode === 'quick_link') {
            $page_title = $quickLink['title'] . " - Page {$currentPage}";
            $page_canonical = SITE_URL . '/?quick_link=' . urlencode($_GET['quick_link']) . '&page=' . $currentPage;

            $og_title = $page_title . ' | WatchShelf';
            $og_url = $page_canonical;
        }
    }

    /* Pagination UI */

    $paginationItems = getPaginationItems($currentPage, $totalPages);

    /* Global Sorting */
    /* The sorter receives the FINAL eligible watch IDs.
    It is only allowed to reorder those IDs. */

    $placeholders = implode(',', array_fill(0, count($watchIds), '?'));

    /* Prioritize explicitly selected Men/Women results ahead of Unisex.
    This changes ordering only, not eligibility. */
    $genderPrioritySql = '';

    if ($catalogueMode === 'filter' && !empty($selectedGenders)) {
        $selectedGendersForPriority = array_map(
            fn($gender) => strtolower(trim($gender)),
            $selectedGenders
        );

        $hasSpecificGender =
            in_array('men', $selectedGendersForPriority, true) ||
            in_array('women', $selectedGendersForPriority, true);

        /* If Unisex itself was explicitly selected, all selected genders receive equal priority. */
        if (
            $hasSpecificGender &&
            !in_array('unisex', $selectedGendersForPriority, true)
        ) {
            $genderPrioritySql = "
            CASE
                WHEN LOWER(w.gender) IN ('men', 'women') THEN 0
                WHEN LOWER(w.gender) = 'unisex' THEN 1
                ELSE 2
            END,
        ";
        }
    }

    /* min_price deliberately considers only currently available, positively-priced retailer records.
    Therefore an entirely out-of-stock watch receives: min_price = NULL
    Price sorting explicitly places those NULL values last. */
    $sortSql = "SELECT w.id, w.is_featured, $minPriceSql AS min_price
                        FROM watches w
                        LEFT JOIN watch_variants wr
                        ON wr.watch_id = w.id
                        WHERE w.id IN ($placeholders)
                        AND w.is_active = 1
                        GROUP BY w.id, w.is_featured
                        ORDER BY $genderPrioritySql $orderBy";

    $stmt = $conn->prepare($sortSql);

    if (!$stmt) {
        die($conn->error .
            "<br><br>" .
            $sortSql);
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

    // Paginate globally sorted IDs
    $offset = ($currentPage - 1) * $productsPerPage;

    $pageWatchIds = array_slice($sortedWatchIds, $offset, $productsPerPage);

    /* Fetch complete rows for current page
    Eligibility has already been established.
    This query MUST NOT remove watches. */

    $pagePlaceholders = implode(',', array_fill(0, count($pageWatchIds), '?'));

    $sql = "SELECT w.*,
                (
                SELECT dv.image_folder
                FROM watch_variants dv
                WHERE dv.watch_id = w.id
                AND dv.is_default = 1
                LIMIT 1
                ) AS default_image_folder,

                MIN(
                        CASE
                        WHEN wr.is_available = 1
                        AND wr.price IS NOT NULL
                        AND wr.price > 0
                        THEN wr.price
                        END
                        ) AS min_price,

                MAX(
                        CASE
                        WHEN wr.is_available = 1
                        AND wr.price IS NOT NULL
                        AND wr.price > 0
                        THEN 1
                        ELSE 0
                        END
                        ) AS has_stock,

                COUNT(DISTINCT wr.color_name) AS color_count,

                COUNT(wr.id) AS variant_count

        FROM watches w

        LEFT JOIN watch_variants wr
        ON wr.watch_id = w.id

        WHERE w.id IN ($pagePlaceholders)
        AND w.is_active = 1

        GROUP BY w.id";

    /* IDs have already been globally sorted and paginated.
    FIELD() preserves precisely that order. */
    $idOrder = implode(',', array_map('intval', $pageWatchIds));

    $sql .= "
                ORDER BY FIELD(
                    w.id,
                    $idOrder
                )
            ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die($conn->error .
            "<br><br>" .
            $sql);
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
                    <div class="catalog-toolbar-info">
                        <h2>Available Watches</h2>

                        <?php if ($showResultCount): ?>
                            <p class="catalog-result-count">
                                <?= $totalWatches ?> watches found
                            </p>
                        <?php endif; ?>
                    </div>

                    <button
                        type="button"
                        class="filter-open-button"
                        aria-controls="filter-sidebar"
                        aria-expanded="false">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        Filters
                    </button>

                    <form action="index.php" method="GET" class="sort-form">
                        <!-- Preserve the current search and filter state by copying existing GET parameters into hidden inputs, excluding the sort parameter -->
                        <?php
                        foreach ($_GET as $key => $value) {
                            // The new sort value replaces the old one.
                            // Pagination must restart from page 1.
                            if ($key === 'sort' || $key === 'page') {
                                continue;
                            }

                            if (is_array($value)) {
                                foreach ($value as $item) {
                                    // Ignore malformed nested arrays.
                                    if (!is_scalar($item)) {
                                        continue;
                                    }
                        ?>
                                    <input
                                        type="hidden"
                                        name="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>[]"
                                        value="<?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?>">
                                <?php
                                }
                            } elseif (is_scalar($value)) {
                                ?>
                                <input
                                    type="hidden"
                                    name="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                                    value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>">
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

                <?php if ($emptyResult): ?>
                    <div class="empty-state">
                        <h2>No matching watches found</h2>

                        <?php if ($emptyResultType === 'search'): ?>
                            <p>
                                We couldn't find any watches matching your search.
                                Try a different search term or remove some filters.
                            </p>
                        <?php elseif ($emptyResultType === 'filter'): ?>
                            <p>
                                We couldn't find any watches matching your selected filters.
                                Try removing one or more filters.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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

                            <!-- Define $watchPath -->
                            <?php
                            $watchPath = getWatchPath((int) $watch['id'], $watch['brand'], $watch['model_name']);
                            ?>

                            <article class="watch-card">
                                <a class="watch-card-link" href="<?= htmlspecialchars($watchPath) ?>">

                                    <?php
                                    $imagePath = DEFAULT_WATCH_IMAGE;

                                    if (!empty($watch['default_image_folder'])) {
                                        $imagePath = WATCH_IMAGE_URL . '/' . $watch['default_image_folder'] . '/1.webp';
                                    }
                                    ?>

                                    <div class="watch-image image-frame">

                                        <img
                                            src="<?= htmlspecialchars($imagePath) ?>"
                                            alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name']) ?>"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.src='<?= htmlspecialchars(DEFAULT_WATCH_IMAGE) ?>';">

                                        <?php if ($watch['is_featured']): ?>
                                            <span
                                                class="watch-image-badge watch-image-badge-featured"
                                                title="Featured"
                                                aria-label="Featured">
                                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($watch['owner_status'] === OWNER_STATUS_OWNED): ?>

                                            <span
                                                class="watch-image-badge watch-image-badge-status watch-image-badge-owned"
                                                title="<?= htmlspecialchars(OWNER_COLLECTION_LABEL) ?>"
                                                aria-label="<?= htmlspecialchars(OWNER_COLLECTION_LABEL) ?>">
                                                <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                                            </span>

                                        <?php elseif ($watch['owner_status'] === OWNER_STATUS_INTERESTED): ?>

                                            <span
                                                class="watch-image-badge watch-image-badge-status watch-image-badge-wishlist"
                                                title="<?= htmlspecialchars(OWNER_WISHLIST_LABEL) ?>"
                                                aria-label="<?= htmlspecialchars(OWNER_WISHLIST_LABEL) ?>">
                                                <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                    <h3><?= htmlspecialchars($watch['brand']) ?></h3>
                                    <h4><?= htmlspecialchars($watch['model_name']) ?></h4>

                                    <?php if ($watch['min_price'] !== null && $watch['min_price'] > 0): ?>

                                        <p class="watch-price">
                                            From ₹<?= number_format($watch['min_price']) ?>
                                        </p>

                                    <?php else: ?>

                                        <p class="watch-unavailable">
                                            Currently unavailable to buy
                                        </p>

                                    <?php endif; ?>
                                </a>

                                <button type="button"
                                    onclick="window.location.href='<?= htmlspecialchars($watchPath) ?>'">
                                    Buying Options
                                </button>
                            </article>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <h2>No watches available</h2>
                            <p>There are currently no watches in our catalogue.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Catalogue pagination">

                        <?php if ($currentPage > 1): ?>
                            <a class="pagination-link pagination-prev"
                                href="<?= htmlspecialchars(buildPaginationUrl($currentPage - 1)) ?>"
                                aria-label="Previous page">

                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                <span class="pagination-label">Previous</span>
                            </a>
                        <?php endif; ?>

                        <?php foreach ($paginationItems as $item): ?>

                            <?php if ($item === 'ellipsis'): ?>

                                <span class="pagination-ellipsis" aria-hidden="true">
                                    &hellip;
                                </span>

                            <?php elseif ($item === $currentPage): ?>

                                <span class="pagination-link active" aria-current="page">
                                    <?= $item ?>
                                </span>

                            <?php else: ?>

                                <a class="pagination-link"
                                    href="<?= htmlspecialchars(buildPaginationUrl($item)) ?>">
                                    <?= $item === $totalPages ? 'Last' : $item ?>
                                </a>

                            <?php endif; ?>

                        <?php endforeach; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a class="pagination-link pagination-next"
                                href="<?= htmlspecialchars(buildPaginationUrl($currentPage + 1)) ?>"
                                aria-label="Next page">

                                <span class="pagination-label">Next</span>
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
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

                        <a href="<?= BASE_URL ?>/?quick_link=<?= urlencode($relatedSlug) ?>"
                            class="quick-link-item">

                            <span>
                                <?= htmlspecialchars($quickLinks[$relatedSlug]['title']) ?>
                            </span>

                            <i class="fa-solid fa-chevron-right quick-link-arrow" aria-hidden="true"></i>
                        </a>

                    <?php endforeach; ?>
                </div>

            </div>
        </section>

    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>

<script src="<?= JS_URL ?>/sidebar.js"></script>
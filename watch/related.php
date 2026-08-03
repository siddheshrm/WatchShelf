<?php
// This file expects $watch to be defined by the parent (details.php)
/** @var array<string, mixed> $watch */

require_once '../config/app.php';

// Build a searchable keyword string from the current watch's attributes
$search = strtolower(
    $watch['brand'] . ' ' .
    $watch['movement_type'] . ' ' .
    $watch['display_type'] . ' ' .
    $watch['gender'] . ' ' .
    $watch['case_material'] . ' ' .
    $watch['band_material'] . ' ' .
    $watch['crystal_type'] . ' ' .
    $watch['tags']
);

// Normalize separators and remove duplicate whitespace before extracting keywords
$search = str_replace(['-', '_'], ' ', $search);
$search = preg_replace('/\s+/', ' ', $search);

// Split into unique keywords to avoid duplicate LIKE conditions
$words = array_unique(explode(' ', $search));

// Select only active watches that are not the current watch, and dynamically build the query based on the extracted keywords
$sql = "SELECT w.*,
                        (SELECT image_folder FROM watch_variants WHERE watch_id = w.id AND is_default = 1 LIMIT 1) AS image_folder,
                        (SELECT price FROM watch_variants WHERE watch_id = w.id AND is_available = 1 AND price IS NOT NULL ORDER BY price ASC LIMIT 1) AS best_price
            FROM watches w
            WHERE w.is_active = 1
            AND w.id != ?
            AND EXISTS (SELECT 1 FROM watch_variants wr WHERE wr.watch_id = w.id AND wr.is_available = 1 AND wr.price IS NOT NULL)";

$types = "i";
$params = [$watch['id']];

$sql .= " AND (";

// Tracks whether the next search condition is the first one being added
$isFirstCondition = true;

// Dynamically build the search query using each extracted keyword
foreach ($words as $word) {
    $word = trim($word);

    if ($word === '') {
        continue;
    }

    $keyword = '%' . $word . '%';

    if (!$isFirstCondition) {
        $sql .= " OR ";
    }

    $sql .= "(
        LOWER(w.brand) LIKE ?
        OR LOWER(w.model_name) LIKE ?
        OR LOWER(w.gender) LIKE ?
        OR LOWER(w.case_material) LIKE ?
        OR LOWER(w.band_material) LIKE ?
        OR LOWER(w.movement_type) LIKE ?
        OR LOWER(w.display_type) LIKE ?
        OR LOWER(w.crystal_type) LIKE ?
        OR LOWER(w.tags) LIKE ?
    )";

    for ($i = 0; $i < 9; $i++) {
        $types .= "s";
        $params[] = $keyword;
    }

    $isFirstCondition = false;
}

$sql .= ") LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();
?>

<section class="related-watches">
    <div class="site-container">

        <div class="section-heading">
            <h2>WatchShelf Recommends</h2>
        </div>

        <div class="related-carousel">
            <button type="button" class="carousel-btn carousel-btn-left" aria-label="Previous watches">&#10094;</button>

            <div class="related-grid">
                <?php while ($relatedWatch = $result->fetch_assoc()): ?>

                    <?php
                    $image = DEFAULT_WATCH_IMAGE;

                    if (!empty($relatedWatch['image_folder'])) {
                        $candidate = WATCH_IMAGE_DIR . '/' . $relatedWatch['image_folder'] . '/1.webp';

                        if (file_exists($candidate)) {
                            $image = WATCH_IMAGE_URL . '/' . $relatedWatch['image_folder'] . '/1.webp';
                        }
                    }
                    ?>

                    <a href="details.php?id=<?= $relatedWatch['id'] ?>" class="related-card">

                        <img class="related-card-image" src="<?= htmlspecialchars($image) ?>"
                            alt="<?= htmlspecialchars($relatedWatch['brand'] . ' ' . $relatedWatch['model_name']) ?>"
                            loading="lazy">

                        <div class="related-card-content">
                            <h3 class="related-card-title">
                                <?= htmlspecialchars($relatedWatch['brand']) ?>
                                <?= htmlspecialchars($relatedWatch['model_name']) ?>
                            </h3>

                            <p class="related-card-movement">
                                <?= htmlspecialchars(ucfirst($relatedWatch['gender'])) ?>
                                <?= htmlspecialchars(ucfirst($relatedWatch['movement_type'])) ?>
                            </p>

                            <?php if ($relatedWatch['best_price'] !== null): ?>
                                <p class="related-card-price">
                                    From ₹<?= number_format($relatedWatch['best_price']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>

                <?php endwhile; ?>
            </div>

            <button type="button" class="carousel-btn carousel-btn-right" aria-label="Next watches">&#10095;</button>
        </div>
    </div>

    <script src="../assets/js/related.js"></script>
</section>
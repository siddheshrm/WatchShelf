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
$sql = "SELECT w.*, (SELECT image_folder FROM watch_variants WHERE watch_id = w.id AND is_default = 1 LIMIT 1) AS image_folder
            FROM watches w
            WHERE w.is_active = 1
            AND w.id != ?";

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
    <h2>WatchShelf Recommends</h2>

    <div class="related-wrapper">
        <button class="scroll-btn scroll-left">&#10094;</button>

        <div class="related-grid">
            <?php

            while ($relatedWatch = $result->fetch_assoc()):
                // Use the watch's primary image if available; otherwise fall back to the default image
                $image = DEFAULT_WATCH_IMAGE;

                if (!empty($relatedWatch['image_folder'])) {
                    $candidate = WATCH_IMAGE_DIR . '/' .
                        $relatedWatch['image_folder'] . "/1.webp";

                    if (file_exists($candidate)) {
                        $image = WATCH_IMAGE_URL . '/' .
                            $relatedWatch['image_folder'] . "/1.webp";
                    }
                }
                ?>

                <a href="details.php?id=<?= $relatedWatch['id'] ?>" class="related-card">
                    <img src="<?= htmlspecialchars($image) ?>"
                        alt="<?= htmlspecialchars($relatedWatch['brand'] . ' ' . $relatedWatch['model_name']) ?>"
                        loading="lazy">

                    <div class="related-card-content">
                        <h3><?= htmlspecialchars($relatedWatch['brand']) ?></h3>
                        <p><?= htmlspecialchars($relatedWatch['model_name']) ?></p>
                        <span><?= htmlspecialchars(ucfirst($relatedWatch['movement_type'])) ?></span>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>

        <button class="scroll-btn scroll-right">&#10095;</button>
    </div>

    <script src="../assets/js/related.js"></script>
</section>
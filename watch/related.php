<?php
// This file expects $watch to be defined by the parent (details.php)

/** @var array<string, mixed> $watch */

require_once '../config/app.php';

// Select active watches that are not the current watch and have at least one currently available retailer offer.
$sql = "SELECT w.*,
            (
                SELECT image_folder
                FROM watch_variants
                WHERE watch_id = w.id
                AND is_default = 1
                LIMIT 1
            ) AS image_folder,
            (
                SELECT price
                FROM watch_variants
                WHERE watch_id = w.id
                AND is_available = 1
                AND price IS NOT NULL
                ORDER BY price ASC
                LIMIT 1
            ) AS best_price,
            (
                CASE
                    WHEN LOWER(w.brand) = LOWER(?) THEN 50
                    ELSE 0
                END
                +
                CASE
                    WHEN LOWER(w.gender) = LOWER(?) THEN 20
                    ELSE 0
                END
                +
                CASE
                    WHEN LOWER(w.movement_type) = LOWER(?) THEN 15
                    ELSE 0
                END
                +
                CASE
                    WHEN LOWER(w.display_type) = LOWER(?) THEN 10
                    ELSE 0
                END
                +
                CASE
                    WHEN LOWER(w.case_material) = LOWER(?) THEN 5
                    ELSE 0
                END
            ) AS relevance_score
        FROM watches w
        WHERE w.is_active = 1
        AND w.id != ?
        AND EXISTS (
            SELECT 1
            FROM watch_variants wr
            WHERE wr.watch_id = w.id
            AND wr.is_available = 1
            AND wr.price IS NOT NULL
        )
        ORDER BY relevance_score DESC, w.id DESC
        LIMIT 20";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssss" . "i",
    $watch['brand'],
    $watch['gender'],
    $watch['movement_type'],
    $watch['display_type'],
    $watch['case_material'],
    $watch['id']
);

$stmt->execute();

$result = $stmt->get_result(); ?>

<section class="related-watches">
    <div class="site-container">

        <div class="section-heading">
            <h2>WatchShelf Recommends</h2>
        </div>

        <div class="related-carousel">
            <button
                type="button"
                class="carousel-btn carousel-btn-left"
                aria-label="Previous watches">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>

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

                    <a href="<?= htmlspecialchars(getWatchPath((int) $relatedWatch['id'], $relatedWatch['brand'], $relatedWatch['model_name'])) ?>"
                        class="related-card"
                        title="View <?= htmlspecialchars($relatedWatch['brand'] . ' ' . $relatedWatch['model_name']) ?>">
                        <div class="watch-image image-frame">
                            <img class="related-card-image" src="<?= htmlspecialchars($image) ?>"
                                alt="<?= htmlspecialchars($relatedWatch['brand'] . ' ' . $relatedWatch['model_name']) ?>"
                                loading="lazy">
                        </div>

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
                            <?php else: ?>
                                <p class="related-card-price related-card-unavailable">
                                    Currently unavailable
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>

                <?php endwhile; ?>
            </div>

            <button
                type="button"
                class="carousel-btn carousel-btn-right"
                aria-label="Next watches">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <script src="../assets/js/related.js"></script>
</section>
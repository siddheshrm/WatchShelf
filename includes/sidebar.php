<?php
$selectedGenders = $_GET['gender'] ?? [];
$selectedBrands = $_GET['brand'] ?? [];
$selectedRetailers = $_GET['retailer'] ?? [];
$selectedColors = $_GET['color'] ?? [];
$selectedMovement = $_GET['movement'] ?? [];

// Returns watch IDs matching the selected sidebar filters
function filterWatches(mysqli $conn, array $filters): array
{
    $includeOutOfStock = isset($filters['include_out_of_stock']);

    // Include all retailer records when requested; otherwise only join retailers that currently have the watch in stock
    $joinCondition = $includeOutOfStock ? "wr.watch_id = w.id" : "wr.watch_id = w.id AND wr.is_available = 1";

    // DISTINCT prevents duplicate watch IDs when a watch is sold by multiple retailers matching the selected filters
    $sql = "SELECT DISTINCT w.id
                FROM watches w
                LEFT JOIN watch_variants wr
                ON $joinCondition
                WHERE w.is_active = 1";

    // Exclude watches that have no in-stock retailer when the "include out of stock" option is disabled
    if (!$includeOutOfStock) {
        $sql .= " AND wr.watch_id IS NOT NULL";
    }

    $bindTypes = "";
    $params = [];

    // Gender
    if (!empty($filters['gender']) && is_array($filters['gender'])) {
        $genders = array_map(
            fn($gender) => strtolower(trim($gender)),
            $filters['gender']
        );

        // Include unisex whenever men or women is selected
        if ((in_array('men', $genders, true) || in_array('women', $genders, true)) && !in_array('unisex', $genders, true)) {
            array_push($genders, 'unisex');
        }

        $placeholders = implode(',', array_fill(0, count($genders), '?'));

        $sql .= " AND LOWER(w.gender) IN ($placeholders)";

        foreach ($genders as $gender) {
            $bindTypes .= "s";
            $params[] = $gender;
        }
    }

    // Brand
    if (!empty($filters['brand']) && is_array($filters['brand'])) {
        $placeholders = implode(',', array_fill(0, count($filters['brand']), '?'));

        $sql .= " AND LOWER(w.brand) IN ($placeholders)";

        foreach ($filters['brand'] as $brand) {
            $bindTypes .= "s";
            $params[] = strtolower(trim($brand));
        }
    }

    // Retailer
    if (!empty($filters['retailer']) && is_array($filters['retailer'])) {
        $placeholders = implode(',', array_fill(0, count($filters['retailer']), '?'));

        $sql .= " AND LOWER(wr.retailer_name) IN ($placeholders)";

        foreach ($filters['retailer'] as $retailer) {
            $bindTypes .= "s";
            $params[] = strtolower(trim($retailer));
        }
    }

    // Dial Colour
    if (!empty($filters['color']) && is_array($filters['color'])) {
        $placeholders = implode(',', array_fill(0, count($filters['color']), '?'));

        $sql .= " AND LOWER(wr.color_name) IN ($placeholders)";

        foreach ($filters['color'] as $color) {
            $bindTypes .= "s";
            $params[] = strtolower(trim($color));
        }
    }

    // Movement
    if (!empty($filters['movement']) && is_array($filters['movement'])) {
        $placeholders = implode(',', array_fill(0, count($filters['movement']), '?'));

        $sql .= " AND LOWER(w.movement_type) IN ($placeholders)";

        foreach ($filters['movement'] as $movement) {
            $bindTypes .= "s";
            $params[] = strtolower(trim($movement));
        }
    }

    // Filter against retailer price, ignoring retailer records without a valid price
    if (!empty($filters['max_price']) && is_numeric($filters['max_price'])) {
        $sql .= " AND wr.price IS NOT NULL AND wr.price <= ?";

        $bindTypes .= "d";
        $params[] = (float) $filters['max_price'];
    }

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($bindTypes, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $watchIds = [];

    while ($row = $result->fetch_assoc()) {
        $watchIds[] = (int) $row['id'];
    }

    $stmt->close();
    return $watchIds;
}

// Fetch all active brands
$brandQuery = "SELECT DISTINCT brand FROM watches WHERE is_active = 1 ORDER BY brand ASC";

$brandResult = $conn->query($brandQuery);
$brands = [];

while ($row = $brandResult->fetch_assoc()) {
    $brands[] = $row;
}

// Fetch all Dial Colors
$colorQuery = "SELECT DISTINCT color_name
                            FROM watch_variants
                            WHERE color_name IS NOT NULL
                            AND TRIM(color_name) <> ''
                            ORDER BY color_name ASC";

$colorResult = $conn->query($colorQuery);
$colors = [];

while ($row = $colorResult->fetch_assoc()) {
    $colors[] = $row;
}

// Fetch all retailers
$retailerQuery = "SELECT DISTINCT retailer_name FROM watch_variants ORDER BY retailer_name ASC";

$retailerResult = $conn->query($retailerQuery);
$retailers = [];

while ($row = $retailerResult->fetch_assoc()) {
    $retailers[] = $row;
}
?>

<main class="catalog-page">
    <!-- Sidebar -->
    <aside class="filter-sidebar">
        <h2>Filters</h2>

        <form action="index.php" method="GET">
            <!-- Gender -->
            <div class="filter-group">
                <h3>Gender</h3>

                <label>
                    <input type="checkbox" name="gender[]" value="men" <?= in_array('men', $selectedGenders, true) ? 'checked' : '' ?>>Men
                </label>

                <label>
                    <input type="checkbox" name="gender[]" value="women" <?= in_array('women', $selectedGenders, true) ? 'checked' : '' ?>>Women
                </label>

                <label>
                    <input type="checkbox" name="gender[]" value="unisex" <?= in_array('unisex', $selectedGenders, true) ? 'checked' : '' ?>>Unisex
                </label>
            </div>

            <!-- Brand -->
            <div class="filter-group">
                <h3>Brands</h3>

                <?php foreach ($brands as $brand): ?>
                    <label>
                        <input type="checkbox" name="brand[]" value="<?= htmlspecialchars($brand['brand']) ?>"
                            <?= in_array($brand['brand'], $selectedBrands, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($brand['brand']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Retailers -->
            <div class="filter-group">
                <h3>Retailers</h3>

                <?php foreach ($retailers as $retailer): ?>

                    <label>
                        <input type="checkbox" name="retailer[]" value="<?= htmlspecialchars($retailer['retailer_name']) ?>"
                            <?= in_array($retailer['retailer_name'], $selectedRetailers, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($retailer['retailer_name']) ?>
                    </label>

                <?php endforeach; ?>
            </div>

            <!-- Dial Colours -->
            <div class="filter-group">
                <h3>Dial Colours</h3>

                <?php foreach ($colors as $color): ?>
                    <label>
                        <input type="checkbox" name="color[]" value="<?= htmlspecialchars($color['color_name']) ?>"
                            <?= in_array($color['color_name'], $selectedColors, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($color['color_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Movement -->
            <div class="filter-group">
                <h3>Movement</h3>

                <label>
                    <input type="checkbox" name="movement[]" value="quartz" <?= in_array('quartz', $selectedMovement, true) ? 'checked' : '' ?>>Quartz
                </label>

                <label>
                    <input type="checkbox" name="movement[]" value="automatic" <?= in_array('automatic', $selectedMovement, true) ? 'checked' : '' ?>>Automatic
                </label>

                <label>
                    <input type="checkbox" name="movement[]" value="mechanical" <?= in_array('mechanical', $selectedMovement, true) ? 'checked' : '' ?>>Mechanical
                </label>

                <label>
                    <input type="checkbox" name="movement[]" value="manual" <?= in_array('manual', $selectedMovement, true) ? 'checked' : '' ?>>Manual
                </label>

                <label>
                    <input type="checkbox" name="movement[]" value="solar" <?= in_array('solar', $selectedMovement, true) ? 'checked' : '' ?>>Solar
                </label>

                <label>
                    <input type="checkbox" name="movement[]" value="kinetic" <?= in_array('kinetic', $selectedMovement, true) ? 'checked' : '' ?>>Kinetic
                </label>
            </div>

            <!-- Availability -->
            <div class="filter-group">
                <h3>Availability</h3>

                <label>
                    <input type="checkbox" name="include_out_of_stock" value="1" <?= isset($_GET['include_out_of_stock']) ? 'checked' : '' ?>>Include Out of Stock Products
                </label>
            </div>

            <!-- Maximum Price -->
            <div class="filter-group">
                <h3>Maximum Price</h3>
                <input type="number" name="max_price" min="0" placeholder="₹ 5000" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
            </div>

            <!-- Buttons -->
            <div class="filter-actions">
                <button type="submit" name="filter_submit" value="1">Apply Filters</button>
                <a href="index.php" class="clear-filters">Clear Filters</a>
            </div>
        </form>
    </aside>
</main>
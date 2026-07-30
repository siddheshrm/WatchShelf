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
<?php

// Returns an array of unique, non-empty string values from the specified key in the source array
function getStringArrayParam(array $source, string $key): array
{
    if (!isset($source[$key]) || !is_array($source[$key])) {
        return [];
    }

    $values = [];

    foreach ($source[$key] as $value) {
        if (!is_string($value)) {
            continue;
        }

        $value = trim($value);

        if ($value === '') {
            continue;
        }

        $values[] = $value;
    }

    return array_values(array_unique($values));
}

$selectedGenders = array_values(array_intersect(
    getStringArrayParam($_GET, 'gender'),
    ['men', 'women', 'unisex']
));

$selectedBrands = getStringArrayParam($_GET, 'brand');
$selectedRetailers = getStringArrayParam($_GET, 'retailer');
$selectedColors = getStringArrayParam($_GET, 'color');

$selectedMovement = array_values(array_intersect(
    getStringArrayParam($_GET, 'movement'),
    ['quartz', 'automatic', 'mechanical', 'manual', 'solar', 'kinetic']
));

$selectedCaseWidths = array_values(array_intersect(
    getStringArrayParam($_GET, 'case_width'),
    [
        'under_26',
        '26_30',
        '30_34',
        '34_38',
        '38_42',
        '42_46',
        '46_50',
        '50_plus'
    ]
));

// Returns watch IDs matching the selected sidebar filters
function filterWatches(mysqli $conn, array $filters): array
{
    $filters['gender'] = getStringArrayParam($filters, 'gender');
    $filters['brand'] = getStringArrayParam($filters, 'brand');
    $filters['retailer'] = getStringArrayParam($filters, 'retailer');
    $filters['color'] = getStringArrayParam($filters, 'color');
    $filters['movement'] = getStringArrayParam($filters, 'movement');
    $filters['case_width'] = getStringArrayParam($filters, 'case_width');

    $filters['gender'] = array_values(array_intersect(
        $filters['gender'],
        ['men', 'women', 'unisex']
    ));

    $filters['movement'] = array_values(array_intersect(
        $filters['movement'],
        ['quartz', 'automatic', 'mechanical', 'manual', 'solar', 'kinetic']
    ));

    $filters['case_width'] = array_values(array_intersect(
        $filters['case_width'],
        [
            'under_26',
            '26_30',
            '30_34',
            '34_38',
            '38_42',
            '42_46',
            '46_50',
            '50_plus'
        ]
    ));

    $includeOutOfStock = isset($filters['include_out_of_stock']);

    // Normal catalogue/sidebar browsing requires a currently purchasable variant.
    // When out-of-stock inclusion is enabled, all variants may participate.
    $joinCondition = $includeOutOfStock ?
        "wr.watch_id = w.id" :
        "wr.watch_id = w.id AND wr.is_available = 1 AND wr.price IS NOT NULL AND wr.price > 0";

    $sql = "SELECT DISTINCT w.id
                FROM watches w
                LEFT JOIN watch_variants wr
                ON $joinCondition
                WHERE w.is_active = 1";

    // Require at least one qualifying available variant unless 'include_out_of_stock' is explicitly enabled.
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

    // Case Width
    if (!empty($filters['case_width']) && is_array($filters['case_width'])) {
        $caseWidthConditions = [];

        foreach ($filters['case_width'] as $caseWidth) {
            switch ($caseWidth) {
                case 'under_26':
                    $caseWidthConditions[] = "w.case_diameter_mm < 26";
                    break;

                case '26_30':
                    $caseWidthConditions[] = "w.case_diameter_mm >= 26 AND w.case_diameter_mm < 30";
                    break;

                case '30_34':
                    $caseWidthConditions[] = "w.case_diameter_mm >= 30 AND w.case_diameter_mm < 34";
                    break;

                case '34_38':
                    $caseWidthConditions[] = "w.case_diameter_mm >= 34 AND w.case_diameter_mm < 38";
                    break;

                case '38_42':
                    $caseWidthConditions[] = "w.case_diameter_mm >= 38 AND w.case_diameter_mm < 42";
                    break;

                case '42_46':
                    $caseWidthConditions[] = "w.case_diameter_mm >= 42 AND w.case_diameter_mm < 46";
                    break;

                case '46_50':
                    $caseWidthConditions[] = "w.case_diameter_mm >= 46 AND w.case_diameter_mm < 50";
                    break;

                case '50_plus':
                    $caseWidthConditions[] = "w.case_diameter_mm >= 50";
                    break;
            }
        }

        if (!empty($caseWidthConditions)) {
            $sql .= " AND (" . implode(' OR ', $caseWidthConditions) . ")";
        }
    }

    // Filter against retailer price, ignoring retailer records without a valid price
    if (
        isset($filters['max_price']) &&
        is_string($filters['max_price']) &&
        is_numeric($filters['max_price']) &&
        (float) $filters['max_price'] > 0
    ) {
        $sql .= " AND wr.price IS NOT NULL AND wr.price > 0 AND wr.price <= ?";

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

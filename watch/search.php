<?php

// Searches watches using free-text keywords and returns matching watch IDs
function searchWatches(mysqli $conn, string $search): array
{
    $search = strtolower(trim($search));

    if ($search === '') {
        return [];
    }

    // Normalize separators and remove duplicate whitespace before extracting keywords
    $search = str_replace(['-', '_'], ' ', $search);
    $search = preg_replace('/\s+/', ' ', $search);

    $words = explode(' ', $search);

    // Ignore common filler words so only meaningful search terms contribute to the SQL query
    $ignoreWords = ['watch', 'watches', 'under', 'below', 'less', 'than', 'for', 'with', 'around', 'about', 'upto', 'up', 'to'];

    $includeOutOfStock = isset($_GET['include_out_of_stock']);

    $joinCondition = $includeOutOfStock ? "wr.watch_id = w.id" : "wr.watch_id = w.id AND wr.is_available = 1";

    $sql = "SELECT DISTINCT w.id
                FROM watches w
                LEFT JOIN watch_retailers wr
                ON $joinCondition
                WHERE w.is_active = 1";

    if (!$includeOutOfStock) {
        $sql .= " AND wr.watch_id IS NOT NULL";
    }

    // Final parameter type string for bind_param()
    $bindTypes = "";
    // Parameter types for detected price filters
    $priceParamTypes = "";
    // Parameter types for keyword search conditions
    $keywordParamTypes = "";

    // Values for price placeholders
    $priceParams = [];
    // Values for keyword placeholders
    $keywordParams = [];

    // SQL conditions generated for each search keyword
    $searchConditions = [];

    foreach ($words as $word) {
        $word = trim($word);

        if ($word === '') {
            continue;
        }

        if (in_array($word, $ignoreWords, true)) {
            continue;
        }

        // Pure numeric values = maximum price
        if (preg_match('/^\d+$/', $word)) {
            $sql .= " AND wr.price <= ?";

            $priceParamTypes .= "d";
            $priceParams[] = (float) $word;

        } else {
            $keyword = '%' . $word . '%';

            // Search each keyword across multiple watch attributes
            $searchConditions[] = "(
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
                $keywordParamTypes .= "s";
                $keywordParams[] = $keyword;
            }
        }
    }

    if (!empty($searchConditions)) {
        $sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
    }

    // Merge in the same order as the SQL placeholders
    $bindTypes = $priceParamTypes . $keywordParamTypes;
    $params = array_merge($priceParams, $keywordParams);

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($bindTypes, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    $searchWatchIds = [];

    while ($row = $result->fetch_assoc()) {
        $searchWatchIds[] = (int) $row['id'];
    }

    $stmt->close();
    return $searchWatchIds;
}

// Returns watch IDs matching the selected quick-link filters
// Supports predefined watch attributes such as brand, gender, movement, materials, display type, and maximum price.
function filterQuickLinkWatches(mysqli $conn, array $filters): array
{
    $includeOutOfStock = isset($_GET['include_out_of_stock']);

    $joinCondition = $includeOutOfStock ? "wr.watch_id = w.id" : "wr.watch_id = w.id AND wr.is_available = 1";

    $sql = "SELECT DISTINCT w.id
                FROM watches w
                LEFT JOIN watch_retailers wr
                ON $joinCondition
                WHERE w.is_active = 1";

    if (!$includeOutOfStock) {
        $sql .= " AND wr.watch_id IS NOT NULL";
    }

    $columnMap = [
        'brand' => 'w.brand',
        'gender' => 'w.gender',
        'movement_type' => 'w.movement_type',
        'display_type' => 'w.display_type',
        'case_material' => 'w.case_material',
        'band_material' => 'w.band_material',
        'crystal_type' => 'w.crystal_type',
    ];

    $bindTypes = '';
    $params = [];

    foreach ($filters as $key => $value) {
        if ($key === 'max_price') {

            $sql .= " AND wr.price <= ?";

            $bindTypes .= 'd';
            $params[] = (float) $value;

            continue;
        }

        if (isset($columnMap[$key])) {
            // Men and Women pages should also include Unisex watches
            if ($key === 'gender' && in_array($value, ['Men', 'Women'], true)) {

                $sql .= " AND ({$columnMap[$key]} = ? OR {$columnMap[$key]} = ?)";

                $bindTypes .= 'ss';
                $params[] = $value;
                $params[] = 'Unisex';

            } else {

                $sql .= " AND {$columnMap[$key]} = ?";

                $bindTypes .= 's';
                $params[] = $value;
            }
        }
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die($conn->error . "<br><br>" . $sql);
    }

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
?>
<?php

// Searches watches using free-text keywords and returns matching watch IDs
function searchWatches(mysqli $conn, string $search): array
{
    $colorGroups = require __DIR__ . '/../config/color-groups.php';

    $search = strtolower(trim($search));

    if ($search === '') {
        return [];
    }

    $originalSearch = $search;

    $maxPrice = null;

    /*
    * Extract an explicit maximum-price expression before search terms are tokenised.
    * Eg., under 5000 | under 5,000 | under ₹5,000 | below Rs 5000 | up to Rs. 5000 | less than INR 5000
    */
    $pricePattern = '/\b(?:under|below|upto|up\s+to|less\s+than)\s*'
        . '(?:₹|rs\.?|inr)?\s*'
        . '([0-9][0-9,]*(?:\.\d+)?)\s*(k)?\b/i';

    if (preg_match($pricePattern, $search, $priceMatch)) {
        $priceValue = str_replace(',', '', $priceMatch[1]);

        if (is_numeric($priceValue)) {
            $priceValue = (float) $priceValue;

            // Convert shorthand values such as 5k and 2.5k.
            if (!empty($priceMatch[2])) {
                $priceValue *= 1000;
            }

            if ($priceValue > 0) {
                $maxPrice = $priceValue;

                $search = preg_replace($pricePattern, ' ', $search, 1);
            }
        }
    }

    // Normalize separators and remove duplicate whitespace before extracting keywords
    $search = str_replace(['-', '_'], ' ', $search);
    $search = preg_replace('/\s+/', ' ', $search);

    $words = deduplicateSearchTerms(
        extractSearchTerms($search, $colorGroups)
    );

    // Ignore common filler words so only meaningful search terms contribute to the SQL query
    $ignoreWords = ['watch', 'watches', 'under', 'below', 'less', 'than', 'for', 'with', 'around', 'about', 'upto', 'up', 'to'];

    $sql = "SELECT DISTINCT w.id
                FROM watches w
                LEFT JOIN watch_variants wr
                ON wr.watch_id = w.id
                WHERE w.is_active = 1";

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

    // SQL conditions generated for each search concept
    $searchConditions = [];

    if ($maxPrice !== null && $maxPrice > 0) {
        $sql .= " AND wr.price IS NOT NULL
                      AND wr.price > 0
                      AND wr.price <= ?
                      AND (wr.is_available = 1
                        OR NOT EXISTS (
                            SELECT 1
                            FROM watch_variants available_wr
                            WHERE available_wr.watch_id = w.id
                              AND available_wr.is_available = 1
                              AND available_wr.price IS NOT NULL
                              AND available_wr.price > 0
                        )
                  )";

        $priceParamTypes .= 'd';
        $priceParams[] = $maxPrice;
    }

    $movementTerms = ['quartz', 'automatic', 'manual', 'mechanical', 'solar', 'kinetic'];
    $displayTerms = ['analog', 'digital', 'ana-digi'];
    $genderTerms = ['men', 'women', 'unisex'];

    foreach ($words as $termGroup) {
        if (!is_array($termGroup) || empty($termGroup)) {
            continue;
        }

        $termGroup = array_values(array_filter(
            array_map(
                fn($term) => is_string($term) ? trim($term) : '',
                $termGroup
            ),
            fn($term) => $term !== ''
        ));

        if (empty($termGroup)) {
            continue;
        }

        // Ignore filler words only when the concept contains one term
        if (count($termGroup) === 1 && in_array($termGroup[0], $ignoreWords, true)) {
            continue;
        }

        // Normalize common gender search terms into the canonical values stored in watches.gender.
        if (count($termGroup) === 1) {
            $genderAliases = [
                'men'     => 'men',
                'mens'    => 'men',
                "men's"   => 'men',

                'women'   => 'women',
                'womens'  => 'women',
                "women's" => 'women',

                'unisex'  => 'unisex',
            ];

            $genderTerm = $genderAliases[$termGroup[0]] ?? null;

            if ($genderTerm !== null) {
                if ($genderTerm === 'men') {
                    $searchConditions[] = "LOWER(w.gender) IN ('men', 'unisex')";
                } elseif ($genderTerm === 'women') {
                    $searchConditions[] = "LOWER(w.gender) IN ('women', 'unisex')";
                } else {
                    $searchConditions[] = "LOWER(w.gender) = 'unisex'";
                }

                continue;
            }
        }

        // Recognised movement terms are matched directly against movement_type.
        if (count($termGroup) === 1 && in_array($termGroup[0], $movementTerms, true)) {
            $searchConditions[] = "LOWER(w.movement_type) = ?";

            $keywordParamTypes .= 's';
            $keywordParams[] = $termGroup[0];

            continue;
        }

        // Recognised display terms are matched directly against display_type.
        if (count($termGroup) === 1 && in_array($termGroup[0], $displayTerms, true)) {
            $searchConditions[] = "LOWER(w.display_type) = ?";

            $keywordParamTypes .= 's';
            $keywordParams[] = $termGroup[0];

            continue;
        }

        $groupConditions = [];

        foreach ($termGroup as $term) {
            if ($term === '') {
                continue;
            }

            $keyword = '%' . $term . '%';

            $groupConditions[] = "(
                LOWER(w.brand) LIKE ?
                OR LOWER(w.model_name) LIKE ?
                OR REPLACE(
                    REPLACE(
                        REPLACE(LOWER(w.model_name), '-', ''),
                        '_', ''
                    ),
                    ' ', ''
                ) LIKE ?
                OR LOWER(w.gender) LIKE ?
                OR LOWER(w.case_material) LIKE ?
                OR LOWER(w.band_material) LIKE ?
                OR LOWER(w.movement_type) LIKE ?
                OR LOWER(w.display_type) LIKE ?
                OR LOWER(w.crystal_type) LIKE ?
                OR LOWER(wr.color_name) LIKE ?
                OR LOWER(w.tags) LIKE ?
            )";

            $normalizedTerm = normalizeModelCode($term);
            $normalizedModelKeyword = '%' . $normalizedTerm . '%';

            $keywordParamTypes .= 'sssssssssss';

            $keywordParams[] = $keyword;                // brand
            $keywordParams[] = $keyword;                // model_name
            $keywordParams[] = $normalizedModelKeyword; // normalized model_name
            $keywordParams[] = $keyword;                // gender
            $keywordParams[] = $keyword;                // case_material
            $keywordParams[] = $keyword;                // band_material
            $keywordParams[] = $keyword;                // movement_type
            $keywordParams[] = $keyword;                // display_type
            $keywordParams[] = $keyword;                // crystal_type
            $keywordParams[] = $keyword;                // color_name
            $keywordParams[] = $keyword;                // tags
        }

        if (!empty($groupConditions)) {
            $searchConditions[] = '(' . implode(' OR ', $groupConditions) . ')';
        }
    }

    if (!empty($searchConditions)) {
        $sql .= " AND " . implode(" AND ", $searchConditions);
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

    /*
    * If the normal search returns no results, try a conservative
    * model-code fallback for searches that look like model identifiers.
    */
    if (empty($searchWatchIds)) {
        /*
        * Approximate model fallback is only intended for a single
        * model-code-style query, not a multi-concept search.
        */
        $isSingleModelQuery =
            !preg_match('/\s/', trim($originalSearch));

        $normalizedSearch = normalizeModelCode($originalSearch);

        $isModelLike =
            $isSingleModelQuery &&
            strlen($normalizedSearch) >= 3 &&
            preg_match('/[a-z]/', $normalizedSearch) &&
            preg_match('/\d/', $normalizedSearch);

        if ($isModelLike) {
            $fallbackSql = "
                                    SELECT DISTINCT w.id
                                    FROM watches w
                                    WHERE w.is_active = 1
                                    AND CHAR_LENGTH(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(LOWER(w.model_name), '-', ''),
                                                    '_', ''
                                                ),
                                                ' ', ''
                                            )
                                        ) >= 3
                                    AND ? LIKE CONCAT(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(LOWER(w.model_name), '-', ''),
                                                    '_', ''
                                                ),
                                                ' ', ''
                                            ),
                                            '%'
                                        )
                                ";

            $fallbackStmt = $conn->prepare($fallbackSql);

            $fallbackStmt->bind_param('s', $normalizedSearch);

            $fallbackStmt->execute();
            $fallbackResult = $fallbackStmt->get_result();

            while ($row = $fallbackResult->fetch_assoc()) {
                $searchWatchIds[] = (int) $row['id'];
            }

            $fallbackStmt->close();
        }
    }

    return $searchWatchIds;
}

// Expands recognised colour names and aliases into all related colours for broader searching
function expandColorKeywords(array $terms, array $colorGroups): array
{
    $expandedColor = [];

    foreach ($terms as $term) {
        $term = strtolower(trim($term));

        if ($term === '') {
            continue;
        }

        $expandedColor[] = $term;

        foreach ($colorGroups as $group) {
            $aliases = array_map('strtolower', $group['aliases']);
            $colors = array_map('strtolower', $group['colors']);

            if (in_array($term, $aliases, true) || in_array($term, $colors, true)) {
                $expandedColor = array_merge($expandedColor, $colors);
            }
        }
    }

    return array_values(array_unique($expandedColor));
}

// Normalizes a model code by removing spaces, hyphens, and underscores, and converting to lowercase for consistent searching
function normalizeModelCode(string $value): string
{
    return strtolower(
        str_replace(
            ['-', '_', ' '],
            '',
            trim($value)
        )
    );
}

// Removes duplicate terms within search concepts and duplicate concepts from the parsed search query
function deduplicateSearchTerms(array $termGroups): array
{
    $uniqueGroups = [];
    $seenGroups = [];

    foreach ($termGroups as $termGroup) {
        if (!is_array($termGroup)) {
            continue;
        }

        $normalizedGroup = [];

        foreach ($termGroup as $term) {
            if (!is_string($term)) {
                continue;
            }

            $term = strtolower(trim($term));

            if ($term === '') {
                continue;
            }

            $normalizedGroup[] = $term;
        }

        $normalizedGroup = array_values(array_unique($normalizedGroup));

        if (empty($normalizedGroup)) {
            continue;
        }

        /*
        * Sort only for comparison so concept groups containing the same
        * alternatives are recognised as duplicates regardless of order.
        */
        $comparisonGroup = $normalizedGroup;
        sort($comparisonGroup, SORT_STRING);

        $groupKey = implode("\0", $comparisonGroup);

        if (isset($seenGroups[$groupKey])) {
            continue;
        }

        $seenGroups[$groupKey] = true;
        $uniqueGroups[] = $normalizedGroup;
    }

    return $uniqueGroups;
}

// Extracts search concepts while preserving recognised multi-word colour names.
// Each returned item represents one search concept and may contain multiple equivalent terms that should be matched using OR.
function extractSearchTerms(string $search, array $colorGroups): array
{
    $terms = [];

    // Normalize recognised non-colour phrases into canonical searchable values.
    $fixedPhrases = [
        'analog digital' => ['ana-digi'],
        'ana digi'       => ['ana-digi'],

        'stop watch'     => ['stopwatch'],
        'world time'     => ['world time'],
        'step counter'   => ['step counter'],
    ];

    foreach ($fixedPhrases as $phrase => $expandedTerms) {
        if (!preg_match('/\b' . preg_quote($phrase, '/') . '\b/i', $search)) {
            continue;
        }

        $terms[] = $expandedTerms;

        $search = preg_replace('/\b' . preg_quote($phrase, '/') . '\b/i', ' ', $search);
    }

    // Build searchable colour phrases
    $phrases = [];

    foreach ($colorGroups as $group) {
        foreach (array_merge($group['aliases'], $group['colors']) as $phrase) {
            $phrases[] = strtolower(trim($phrase));
        }
    }

    $phrases = array_values(array_unique($phrases));

    // Match longer phrases first
    usort(
        $phrases,
        fn($a, $b) => strlen($b) <=> strlen($a)
    );

    foreach ($phrases as $phrase) {
        if (!preg_match('/\b' . preg_quote($phrase, '/') . '\b/i', $search)) {
            continue;
        }

        $expandedTerms = expandColorKeywords([$phrase], $colorGroups);

        if (!empty($expandedTerms)) {
            $terms[] = $expandedTerms;
        }

        // Remove the matched phrase so its individual words are not later treated as separate mandatory concepts
        $search = preg_replace('/\b' . preg_quote($phrase, '/') . '\b/i', ' ', $search);
    }

    $singleTermAliases = [
        'worldtime'   => 'world time',
        'stepcounter' => 'step counter',
        'calender'    => 'calendar',
    ];

    // Remaining words become individual concepts
    foreach (preg_split('/\s+/', strtolower(trim($search))) as $word) {
        $word = trim($word);

        if ($word === '') {
            continue;
        }

        $terms[] = [
            $singleTermAliases[$word] ?? $word
        ];
    }

    return $terms;
}

// Returns watch IDs matching the selected quick-link filters
// Supports predefined watch attributes such as brand, gender, movement, materials, display type, and maximum price.
function filterQuickLinkWatches(mysqli $conn, array $filters): array
{
    // Include out-of-stock watches
    $sql = "SELECT DISTINCT w.id
                FROM watches w
                LEFT JOIN watch_variants wr
                ON wr.watch_id = w.id
                WHERE w.is_active = 1";

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
            $sql .= " AND wr.price IS NOT NULL
                          AND wr.price > 0
                          AND wr.price <= ?
                          AND (
                                    wr.is_available = 1
                                    OR NOT EXISTS (
                                                               SELECT 1
                                                               FROM watch_variants available_wr
                                                               WHERE available_wr.watch_id = w.id
                                                               AND available_wr.is_available = 1
                                                               AND available_wr.price IS NOT NULL
                                                               AND available_wr.price > 0
                                                               )
                                    )";

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

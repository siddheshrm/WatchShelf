<?php

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/connection.php';

header('Content-Type: application/json');

$providedKey = $_SERVER['HTTP_X_SCRAPER_KEY'] ?? '';

if (!hash_equals(SCRAPER_API_KEY, $providedKey)) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
    ]);

    exit;
}

$sql = "SELECT
            wv.id AS variant_id,
            wv.watch_id,
            w.brand,
            w.model_name,
            wv.color_name,
            wv.base_url,
            wv.price,
            wv.is_available,
            wv.last_scrape_status
        FROM watch_variants wv
        INNER JOIN watches w
            ON w.id = wv.watch_id
        WHERE LOWER(wv.retailer_name) = 'amazon'
        AND wv.base_url IS NOT NULL
        AND wv.base_url <> ''
        AND (
            wv.base_url LIKE 'https://www.amazon.in/%'
            OR wv.base_url LIKE 'http://www.amazon.in/%'
        )
        ORDER BY wv.id ASC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch Amazon scrape queue',
    ]);

    exit;
}

$variants = [];

while ($row = $result->fetch_assoc()) {
    $variants[] = [
        'variant_id' => (int) $row['variant_id'],
        'watch_id' => (int) $row['watch_id'],
        'brand' => $row['brand'],
        'model_name' => $row['model_name'],
        'color_name' => $row['color_name'],
        'price' => $row['price'] !== null
            ? (float) $row['price']
            : null,
        'is_available' => (int) $row['is_available'],
        'last_scrape_status' => $row['last_scrape_status'],
        'url' => $row['base_url'],
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($variants),
    'variants' => $variants,
]);

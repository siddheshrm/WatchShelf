<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');

// Request method validation is done at the top to avoid unnecessary database queries for invalid requests.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);

    exit;
}

// Authentication
$providedKey = $_SERVER['HTTP_X_SCRAPER_KEY'] ?? '';

if (!hash_equals(SCRAPER_API_KEY, $providedKey)) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
    ]);

    exit;
}

// Retailer validation
$retailer = strtolower(trim($_GET['retailer'] ?? ''));

$allowedRetailers = [
    'amazon' => 'https://www.amazon.in/%',
    'casio' => 'https://casiostore.bhawar.com/%',
    'titan' => 'https://www.titan.co.in/%',
];

if (!array_key_exists($retailer, $allowedRetailers)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Invalid or unsupported retailer',
    ]);

    exit;
}

$urlPattern = $allowedRetailers[$retailer];

// Optional variant selection
$variantIds = [];
$variantRange = null;

if (array_key_exists('filter', $_GET)) {
    if (!is_string($_GET['filter'])) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'Invalid filter',
        ]);

        exit;
    }

    $input = trim($_GET['filter']);

    if ($input === '') {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'Filter cannot be empty',
        ]);

        exit;
    }

    if (preg_match('/^([1-9][0-9]*)-([1-9][0-9]*)$/', $input, $matches)) {
        $startPosition = filter_var($matches[1], FILTER_VALIDATE_INT);

        $endPosition = filter_var($matches[2], FILTER_VALIDATE_INT);

        if ($startPosition === false || $endPosition === false || $startPosition > $endPosition) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'error' => 'Invalid position range',
            ]);

            exit;
        }

        $variantRange = [$startPosition, $endPosition];
    } else {
        foreach (explode(',', $input) as $inputId) {
            $inputId = trim($inputId);

            $validatedId = filter_var(
                $inputId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validatedId === false || (string) $validatedId !== $inputId) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'error' => "Invalid variant ID: {$inputId}",
                ]);

                exit;
            }

            $variantIds[] = $validatedId;
        }

        $variantIds = array_values(array_unique($variantIds));
    }
}

// Build queue query
$query = "SELECT wv.id AS variant_id, wv.watch_id, w.brand, w.model_name, wv.color_name, wv.base_url, wv.price, wv.is_available, wv.last_scrape_status
                FROM watch_variants wv
                INNER JOIN watches w
                ON w.id = wv.watch_id
                WHERE LOWER(wv.retailer_name) = ?
                AND wv.base_url IS NOT NULL
                AND wv.base_url <> ''
                AND wv.base_url LIKE ?";

$types = 'ss';
$params = [$retailer, $urlPattern];

// Filter by specific variant IDs
if (!empty($variantIds)) {
    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));

    $query .= " AND wv.id IN ({$placeholders})";

    $types .= str_repeat('i', count($variantIds));
    $params = array_merge($params, $variantIds);
}

// Sort eligible variants
$query .= " ORDER BY wv.id ASC";

// Filter by retailer-specific positions
if ($variantRange !== null) {
    $startPosition = $variantRange[0];
    $endPosition = $variantRange[1];

    $limit = $endPosition - $startPosition + 1;
    $offset = $startPosition - 1;

    $query .= " LIMIT ? OFFSET ?";

    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
}

// Execute queue query
try {
    $selectStmt = $conn->prepare($query);

    if (!$selectStmt) {
        throw new RuntimeException('Failed to prepare queue query');
    }

    $selectStmt->bind_param($types, ...$params);
    $selectStmt->execute();

    $result = $selectStmt->get_result();

    if (!$result) {
        throw new RuntimeException('Failed to fetch queue results');
    }

    $variants = [];

    while ($row = $result->fetch_assoc()) {
        $variants[] = [
            'variant_id' => (int) $row['variant_id'],
            'watch_id' => (int) $row['watch_id'],
            'brand' => $row['brand'],
            'model_name' => $row['model_name'],
            'color_name' => $row['color_name'],
            'price' => $row['price'] !== null ? (float) $row['price'] : null,
            'is_available' => (int) $row['is_available'],
            'last_scrape_status' => $row['last_scrape_status'],
            'url' => $row['base_url'],
        ];
    }

    $selectStmt->close();
} catch (Throwable $e) {
    error_log('Central scraper queue error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch scrape queue',
    ]);

    exit;
}

// Return queue
echo json_encode([
    'success' => true,
    'count' => count($variants),
    'variants' => $variants,
]);

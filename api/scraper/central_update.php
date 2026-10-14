<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');

// JSON response helper
function sendResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);

    echo json_encode($data);

    exit;
}

// Request method validation is done at the top to avoid unnecessary database queries for invalid requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    sendResponse(405, [
        'success' => false,
        'error' => 'Method not allowed',
    ]);
}

// Authentication
$providedKey = $_SERVER['HTTP_X_SCRAPER_KEY'] ?? '';

if (!hash_equals(SCRAPER_API_KEY, $providedKey)) {
    sendResponse(401, [
        'success' => false,
        'error' => 'Unauthorized',
    ]);
}

// Decode request body
$rawBody = file_get_contents('php://input');

if ($rawBody === false) {
    sendResponse(400, [
        'success' => false,
        'error' => 'Unable to read request body',
    ]);
}

$data = json_decode($rawBody, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data) ||
    array_is_list($data)
) {
    sendResponse(400, [
        'success' => false,
        'error' => 'Invalid JSON request object',
    ]);
}

// Retailer validation
$retailer = $data['retailer'] ?? '';

if (!is_string($retailer)) {
    sendResponse(400, [
        'success' => false,
        'error' => 'Invalid retailer',
    ]);
}

$retailer = strtolower(trim($retailer));

$allowedRetailers = [
    'amazon',
    'casio',
    'titan',
];

if (!in_array($retailer, $allowedRetailers, true)) {
    sendResponse(400, [
        'success' => false,
        'error' => 'Invalid or unsupported retailer',
    ]);
}

// Results validation
if (
    !array_key_exists('results', $data) ||
    !is_array($data['results']) ||
    !array_is_list($data['results'])
) {
    sendResponse(400, [
        'success' => false,
        'error' => 'Missing or invalid results array',
    ]);
}

// Initialize counters
$updated = 0;
$unchanged = 0;
$errorsRecorded = 0;
$failed = 0;

$details = [];
$processedIds = [];

$selectStmt = null;
$changeStmt = null;
$changeWithoutPriceStmt = null;
$noChangeStmt = null;
$errorStmt = null;

// Prepare database statements
try {
    $selectStmt = $conn->prepare("SELECT id, price, is_available, last_scrape_status
                                                        FROM watch_variants
                                                        WHERE id = ?
                                                        AND LOWER(retailer_name) = ?
                                                        LIMIT 1");

    $changeStmt = $conn->prepare("UPDATE watch_variants
                                                            SET price = ?, is_available = ?, last_checked = NOW(), last_updated_by_scraper = 1, last_scrape_status = ?
                                                            WHERE id = ?
                                                            AND LOWER(retailer_name) = ?");

    $changeWithoutPriceStmt = $conn->prepare("UPDATE watch_variants
                                                                                SET is_available = ?, last_checked = NOW(), last_updated_by_scraper = 1, last_scrape_status = ?
                                                                                WHERE id = ?
                                                                                AND LOWER(retailer_name) = ?");

    $noChangeStmt = $conn->prepare("UPDATE watch_variants
                                                                SET last_checked = NOW()
                                                                WHERE id = ?
                                                                AND LOWER(retailer_name) = ?");

    $errorStmt = $conn->prepare("UPDATE watch_variants
                                                        SET last_checked = NOW(), last_scrape_status = 'error'
                                                        WHERE id = ?
                                                        AND LOWER(retailer_name) = ?");

    if (
        !$selectStmt ||
        !$changeStmt ||
        !$changeWithoutPriceStmt ||
        !$noChangeStmt ||
        !$errorStmt
    ) {
        throw new RuntimeException(
            'Failed to prepare one or more database statements'
        );
    }
} catch (Throwable $e) {
    error_log('Central scraper update preparation error: ' . $e->getMessage());

    sendResponse(500, [
        'success' => false,
        'error' => 'Failed to prepare database statements',
    ]);
}

// Process scraper results
foreach ($data['results'] as $result) {

    // Validate result object
    if (!is_array($result) || array_is_list($result)) {
        $failed++;

        $details[] = [
            'variant_id' => null,
            'success' => false,
            'error' => 'Invalid result object',
        ];

        continue;
    }

    // Validate variant ID
    $rawVariantId = $result['variant_id'] ?? null;

    $variantId = filter_var(
        $rawVariantId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (
        $variantId === false ||
        is_bool($rawVariantId) ||
        (
            is_string($rawVariantId) &&
            (string) $variantId !== $rawVariantId
        )
    ) {
        $failed++;

        $details[] = [
            'variant_id' => null,
            'success' => false,
            'error' => 'Invalid variant ID',
        ];

        continue;
    }

    // Reject duplicate variant IDs
    if (isset($processedIds[$variantId])) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Duplicate variant ID in batch',
        ];

        continue;
    }

    $processedIds[$variantId] = true;

    // Validate scrape success flag
    if (
        !array_key_exists('success', $result) ||
        !is_bool($result['success'])
    ) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Missing or invalid scrape success flag',
        ];

        continue;
    }

    $scrapeSuccess = $result['success'];

    $available = null;
    $status = null;
    $hasPrice = false;
    $price = null;

    // Validate successful scrape data
    if ($scrapeSuccess) {

        // Availability must be an actual JSON boolean.
        if (!array_key_exists('available', $result) || !is_bool($result['available'])) {
            $failed++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => false,
                'error' => 'Missing or invalid availability',
            ];

            continue;
        }

        $available = $result['available'] ? 1 : 0;

        $status = $available ? 'success' : 'out_of_stock';

        /* A null or missing price is permitted only when the watch is confirmed out of stock. */
        $hasPrice = array_key_exists('price', $result) && $result['price'] !== null;

        if ($available === 1 && !$hasPrice) {
            $failed++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => false,
                'error' => 'In-stock result requires a price',
            ];

            continue;
        }

        // Validate a supplied price.
        if ($hasPrice) {
            $rawPrice = $result['price'];

            if (is_bool($rawPrice) || !is_numeric($rawPrice)) {
                $failed++;

                $details[] = [
                    'variant_id' => $variantId,
                    'success' => false,
                    'error' => 'Invalid price',
                ];

                continue;
            }

            $price = (float) $rawPrice;

            if (!is_finite($price) || $price <= 0) {
                $failed++;

                $details[] = [
                    'variant_id' => $variantId,
                    'success' => false,
                    'error' => 'Invalid price',
                ];

                continue;
            }
        }
    }

    // Database operations
    try {
        // Fetch existing variant
        $selectStmt->bind_param('is', $variantId, $retailer);

        $selectStmt->execute();

        $variantResult = $selectStmt->get_result();
        $variant = $variantResult->fetch_assoc();

        if (!$variant) {
            $failed++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => false,
                'error' => 'Retailer variant not found',
            ];

            continue;
        }

        // Handle scraping error
        /* Preserve the previous price and availability. Update only last_checked and scrape status. */
        if (!$scrapeSuccess) {
            $errorStmt->bind_param('is', $variantId, $retailer);

            $errorStmt->execute();

            $errorsRecorded++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => true,
                'status' => 'error',
                'changed' => true,
            ];

            continue;
        }

        // Existing database values
        $oldPrice = $variant['price'] !== null ? (float) $variant['price'] : null;
        $oldAvailable = (int) $variant['is_available'];
        $oldStatus = $variant['last_scrape_status'];

        // Detect changes
        $priceChanged = $hasPrice && ($oldPrice === null || $oldPrice !== $price);

        $availabilityChanged = $oldAvailable !== $available;

        $statusChanged = $oldStatus !== $status;

        $changed = $priceChanged || $availabilityChanged || $statusChanged;

        // Apply changes
        if ($changed) {
            if ($hasPrice) {
                // Update price, availability and status.
                $changeStmt->bind_param('disis', $price, $available, $status, $variantId, $retailer);

                $changeStmt->execute();
            } else {
                // Confirmed out of stock without price. Preserve the previous database price.
                $changeWithoutPriceStmt->bind_param('isis', $available, $status, $variantId, $retailer);

                $changeWithoutPriceStmt->execute();
            }

            $updated++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => true,
                'status' => $status,
                'changed' => true,
                'changes' => [
                    'price' => $priceChanged,
                    'availability' => $availabilityChanged,
                    'status' => $statusChanged,
                ],
                'old' => [
                    'price' => $oldPrice,
                    'available' => (bool) $oldAvailable,
                    'status' => $oldStatus,
                ],
                'new' => [
                    'price' => $hasPrice ? $price : $oldPrice,
                    'available' => (bool) $available,
                    'status' => $status,
                ],
            ];

            continue;
        }

        // No changes detected
        $noChangeStmt->bind_param('is', $variantId, $retailer);

        $noChangeStmt->execute();

        $unchanged++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => true,
            'status' => $status,
            'changed' => false,
        ];
    } catch (Throwable $e) {
        error_log(
            "Central scraper update error for {$retailer} " . "variant {$variantId}: " . $e->getMessage()
        );

        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Database operation failed',
        ];
    }
}

// Close prepared statements
foreach (
    [
        $selectStmt,
        $changeStmt,
        $changeWithoutPriceStmt,
        $noChangeStmt,
        $errorStmt,
    ] as $stmt
) {
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
}

// Return batch summary
echo json_encode([
    'success' => $failed === 0,
    'updated' => $updated,
    'unchanged' => $unchanged,
    'errors_recorded' => $errorsRecorded,
    'failed' => $failed,
    'results' => $details,
]);

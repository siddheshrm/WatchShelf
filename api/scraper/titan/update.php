<?php

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/connection.php';

// Tell the client that the response is JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);

    exit;
}

// Read the scraper API key
$providedKey = $_SERVER['HTTP_X_SCRAPER_KEY'] ?? '';

// Authenticate the scraper
if (!hash_equals(SCRAPER_API_KEY, $providedKey)) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
    ]);

    exit;
}

// Read the POST body
$rawBody = file_get_contents('php://input');

// Converts the JSON into a PHP associative array rather than an object
$data = json_decode($rawBody, true);

// Validate the JSON
if (!is_array($data)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON',
        'json_error' => json_last_error_msg(),
    ]);

    exit;
}

// Make sure results exists
if (!isset($data['results']) || !is_array($data['results'])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Missing or invalid results array',
    ]);

    exit;
}

// Prepare four statements
$selectStmt = $conn->prepare("SELECT id, price, is_available, last_scrape_status
                                                    FROM watch_variants
                                                    WHERE id = ?
                                                    AND LOWER(retailer_name) = 'titan'
                                                    LIMIT 1");

$changeStmt = $conn->prepare("UPDATE watch_variants
                                                       SET price = ?, is_available = ?, last_checked = NOW(), last_updated_by_scraper = 1, last_scrape_status = ?
                                                       WHERE id = ?");

$noChangeStmt = $conn->prepare("UPDATE watch_variants
                                                           SET last_checked = NOW()
                                                           WHERE id = ?");

$errorStmt = $conn->prepare("UPDATE watch_variants
                                                   SET last_checked = NOW(), last_scrape_status = 'error'
                                                   WHERE id = ?
                                                   AND LOWER(retailer_name) = 'titan'");

// Verify all statements were prepared
if (!$selectStmt || !$changeStmt || !$noChangeStmt || !$errorStmt) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Failed to prepare database statements',
    ]);

    exit;
}

// Initialize counters
$updated = 0;
$unchanged = 0;
$errorsRecorded = 0;
$failed = 0;
$details = [];

// Process every scraper result
foreach ($data['results'] as $result) {
    // Validate the variant ID
    $variantId = isset($result['variant_id']) ? (int) $result['variant_id'] : 0;

    if ($variantId <= 0) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Invalid variant ID',
        ];

        continue;
    }

    $selectStmt->bind_param('i', $variantId);
    $selectStmt->execute();

    $variantResult = $selectStmt->get_result();
    $variant = $variantResult->fetch_assoc();

    // Verify that the variant actually exists
    if (!$variant) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Titan variant not found',
        ];

        continue;
    }

    // Check whether scraping succeeded
    $scrapeSuccess = !empty($result['success']);

    if (!$scrapeSuccess) {
        $errorStmt->bind_param('i', $variantId);

        if ($errorStmt->execute()) {
            $errorsRecorded++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => true,
                'status' => 'error',
                'changed' => true,
            ];
        } else {
            $failed++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => false,
                'error' => 'Failed to record scrape error',
            ];
        }

        continue;
    }

    // Validate successful scrape data
    if (!isset($result['price']) || !is_numeric($result['price']) || !array_key_exists('available', $result)) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Incomplete scrape result',
        ];

        continue;
    }

    // Convert the scraped values
    $price = (float) $result['price'];
    $available = $result['available'] ? 1 : 0;
    $status = $available ? 'success' : 'out_of_stock';

    // Reject invalid prices
    if ($price <= 0) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Invalid price',
        ];

        continue;
    }

    // Read the old database values
    $oldPrice = (float) $variant['price'];
    $oldAvailable = (int) $variant['is_available'];
    $oldStatus = $variant['last_scrape_status'];

    // Detect exactly what changed
    $priceChanged = $oldPrice !== $price;
    $availabilityChanged = $oldAvailable !== $available;
    $statusChanged = $oldStatus !== $status;

    // If something changed, update the row
    $changed = $priceChanged || $availabilityChanged || $statusChanged;

    if ($changed) {
        $changeStmt->bind_param('disi', $price, $available, $status, $variantId);

        if ($changeStmt->execute()) {
            $updated++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => true,
                'status' => $status,
                'changed' => true,

                // Records exactly what changed
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
                    'price' => $price,
                    'available' => (bool) $available,
                    'status' => $status,
                ],
            ];
        } else {
            $failed++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => false,
                'error' => 'Database update failed',
            ];
        }

        continue;
    }

    // If nothing changed
    $noChangeStmt->bind_param('i', $variantId);

    if ($noChangeStmt->execute()) {
        $unchanged++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => true,
            'status' => $status,
            'changed' => false,
        ];
    } else {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Failed to update last_checked',
        ];
    }
}

// Close the prepared statements
$selectStmt->close();
$changeStmt->close();
$noChangeStmt->close();
$errorStmt->close();

// Return a batch summary
echo json_encode([
    'success' => $failed === 0,
    'updated' => $updated,
    'unchanged' => $unchanged,
    'errors_recorded' => $errorsRecorded,
    'failed' => $failed,
    'results' => $details,
]);

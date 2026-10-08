<?php

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);

    exit;
}

$providedKey = $_SERVER['HTTP_X_SCRAPER_KEY'] ?? '';

if (!hash_equals(SCRAPER_API_KEY, $providedKey)) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
    ]);

    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON',
        'json_error' => json_last_error_msg(),
    ]);

    exit;
}

if (!isset($data['results']) || !is_array($data['results'])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Missing or invalid results array',
    ]);

    exit;
}

$selectStmt = $conn->prepare(
    "SELECT id, price, is_available, last_scrape_status
     FROM watch_variants
     WHERE id = ?
     AND LOWER(retailer_name) = 'amazon'
     LIMIT 1"
);

$changeStmt = $conn->prepare(
    "UPDATE watch_variants
     SET price = ?,
         is_available = ?,
         last_checked = NOW(),
         last_updated_by_scraper = 1,
         last_scrape_status = ?
     WHERE id = ?
     AND LOWER(retailer_name) = 'amazon'"
);

$changeWithoutPriceStmt = $conn->prepare(
    "UPDATE watch_variants
     SET is_available = ?,
         last_checked = NOW(),
         last_updated_by_scraper = 1,
         last_scrape_status = ?
     WHERE id = ?
     AND LOWER(retailer_name) = 'amazon'"
);

$noChangeStmt = $conn->prepare(
    "UPDATE watch_variants
     SET last_checked = NOW()
     WHERE id = ?
     AND LOWER(retailer_name) = 'amazon'"
);

$errorStmt = $conn->prepare(
    "UPDATE watch_variants
     SET last_checked = NOW(),
         last_scrape_status = 'error'
     WHERE id = ?
     AND LOWER(retailer_name) = 'amazon'"
);

if (
    !$selectStmt ||
    !$changeStmt ||
    !$changeWithoutPriceStmt ||
    !$noChangeStmt ||
    !$errorStmt
) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Failed to prepare database statements',
    ]);

    exit;
}

$updated = 0;
$unchanged = 0;
$errorsRecorded = 0;
$failed = 0;
$details = [];

foreach ($data['results'] as $result) {
    $variantId = isset($result['variant_id'])
        ? (int) $result['variant_id']
        : 0;

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

    if (!$variant) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Amazon variant not found',
        ];

        continue;
    }

    $scrapeSuccess = !empty($result['success']);

    /*
    |--------------------------------------------------------------------------
    | Scrape failure
    |--------------------------------------------------------------------------
    |
    | CAPTCHA, navigation failure, invalid page, parsing failure, etc.
    | Existing price and availability remain untouched.
    |
    */

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

    if (!array_key_exists('available', $result)) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'Missing availability result',
        ];

        continue;
    }

    $available = $result['available'] ? 1 : 0;
    $status = $available ? 'success' : 'out_of_stock';

    /*
    |--------------------------------------------------------------------------
    | Price validation
    |--------------------------------------------------------------------------
    */

    $hasPrice =
        array_key_exists('price', $result) &&
        $result['price'] !== null;

    if ($available && !$hasPrice) {
        $failed++;

        $details[] = [
            'variant_id' => $variantId,
            'success' => false,
            'error' => 'In-stock Amazon result has no price',
        ];

        continue;
    }

    $price = null;

    if ($hasPrice) {
        if (!is_numeric($result['price'])) {
            $failed++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => false,
                'error' => 'Invalid price',
            ];

            continue;
        }

        $price = (float) $result['price'];

        if ($price <= 0) {
            $failed++;

            $details[] = [
                'variant_id' => $variantId,
                'success' => false,
                'error' => 'Invalid price',
            ];

            continue;
        }
    }

    $oldPrice = $variant['price'] !== null
        ? (float) $variant['price']
        : null;

    $oldAvailable = (int) $variant['is_available'];
    $oldStatus = $variant['last_scrape_status'];

    $priceChanged =
        $hasPrice &&
        ($oldPrice === null || $oldPrice !== $price);

    $availabilityChanged =
        $oldAvailable !== $available;

    $statusChanged =
        $oldStatus !== $status;

    $changed =
        $priceChanged ||
        $availabilityChanged ||
        $statusChanged;

    if ($changed) {
        /*
         * If Amazon supplied a trustworthy price, update it.
         */
        if ($hasPrice) {
            $changeStmt->bind_param(
                'disi',
                $price,
                $available,
                $status,
                $variantId
            );

            $executed = $changeStmt->execute();
        } else {
            /*
             * Confirmed OOS without a trustworthy current price.
             * Preserve the existing DB price.
             */
            $changeWithoutPriceStmt->bind_param(
                'isi',
                $available,
                $status,
                $variantId
            );

            $executed = $changeWithoutPriceStmt->execute();
        }

        if ($executed) {
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

$selectStmt->close();
$changeStmt->close();
$changeWithoutPriceStmt->close();
$noChangeStmt->close();
$errorStmt->close();

echo json_encode([
    'success' => $failed === 0,
    'updated' => $updated,
    'unchanged' => $unchanged,
    'errors_recorded' => $errorsRecorded,
    'failed' => $failed,
    'results' => $details,
]);

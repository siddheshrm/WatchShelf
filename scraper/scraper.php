<?php

/*
|--------------------------------------------------------------------------
| WatchShelf - Centralized Retailer Scraper
|--------------------------------------------------------------------------
|
| Usage:
|
| 1. Scrape all eligible variants for a retailer: php scraper/scraper.php flipkart
|
| 2. Scrape a specific variant by database ID: php scraper/scraper.php flipkart 1127
|
| 3. Scrape multiple variants by database IDs: php scraper/scraper.php flipkart 1127,4393,4496
|
| 4. Scrape the first 100 variants of a retailer: php scraper/scraper.php flipkart 1-100
|
| 5. Scrape variants at positions 101 through 200 (inclusive): php scraper/scraper.php flipkart 101-200
|
| 6. Scrape a single variant by retailer-specific position: php scraper/scraper.php flipkart 50-50
|
| 7. Scrape variants for a retailer whose name contains spaces: php scraper/scraper.php 'tata cliq luxury' 1-100
|
| Notes:
| - Commands must be executed via PHP CLI.
| - Individual IDs refer to watch_variants.id in the database.
| - Ranges refer to retailer-specific positions, NOT database IDs.
| - Positions are 1-based, inclusive, and ordered by variant ID ASC.
| - Only variants with non-empty base URLs are eligible.
| - Position ranges may shift when eligible variants are added or removed.
| - Omitting the second argument processes all eligible retailer variants.
| - Scraping results are recorded in retailer-specific log files.
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/logger.php';

// Read retailer and optional variant IDs from CLI
if (PHP_SAPI !== 'cli') {
    exit("This scraper must be executed via the CLI.\n");
}

if ($argc < 2 || $argc > 3 || trim($argv[1]) === '') {
    exit("Usage: php scraper.php <retailer_name> [variant_id1,variant_id2,... | start_position-end_position]\n");
}

$retailer = strtolower(trim($argv[1]));

$variantIds = [];
$variantRange = null;

// Validate optional variant IDs or inclusive range
if (isset($argv[2])) {
    $input = trim($argv[2]);

    if (preg_match('/^([1-9][0-9]*)-([1-9][0-9]*)$/', $input, $matches)) {
        // Inclusive retailer-specific position range
        $startPosition = filter_var($matches[1], FILTER_VALIDATE_INT);
        $endPosition = filter_var($matches[2], FILTER_VALIDATE_INT);

        if ($startPosition === false || $endPosition === false || $startPosition > $endPosition) {
            exit("Invalid position range: {$input}\n");
        }

        $variantRange = [$startPosition, $endPosition];
    } else {
        // Individual or comma-separated variant IDs
        foreach (explode(',', $input) as $inputId) {
            $inputId = trim($inputId);

            if (!ctype_digit($inputId) || (int)$inputId <= 0 || (string)(int)$inputId !== $inputId) {
                exit("Invalid variant ID: {$inputId}\n");
            }

            $variantIds[] = (int)$inputId;
        }

        $variantIds = array_values(array_unique($variantIds));
    }
}

// Fetch watch variants for the specified retailer
$query = "SELECT watch_variants.*, watches.brand, watches.model_name
                FROM watch_variants
                INNER JOIN watches
                ON watches.id = watch_variants.watch_id
                WHERE LOWER(watch_variants.retailer_name) = ?
                AND watch_variants.base_url IS NOT NULL
                AND watch_variants.base_url <> ''";

$types = "s";
$params = [$retailer];

// Apply optional variant ID filter
if (!empty($variantIds)) {
    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));

    $query .= " AND watch_variants.id IN ({$placeholders})";

    $types .= str_repeat('i', count($variantIds));
    $params = array_merge($params, $variantIds);
}

// Ensure predictable retailer-specific ordering
$query .= " ORDER BY watch_variants.id ASC";

// Apply inclusive position range
if ($variantRange !== null) {
    $startPosition = $variantRange[0];
    $endPosition = $variantRange[1];

    $limit = $endPosition - $startPosition + 1;
    $offset = $startPosition - 1;

    $query .= " LIMIT ? OFFSET ?";

    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
}

$selectStmt = $conn->prepare($query);

if (!$selectStmt) {
    die("Database Error: " . $conn->error);
}

$selectStmt->bind_param($types, ...$params);
$selectStmt->execute();

$result = $selectStmt->get_result();

if ($result === false) {
    die("Database Error: " . $conn->error);
}

if ($result->num_rows === 0) {
    $selectStmt->close();

    exit(empty($variantIds) && $variantRange === null
        ? "No products found for retailer: {$retailer}\n"
        : "No matching variants found for retailer: {$retailer}\n");
}

// Folder name
$retailerSlug = str_replace(' ', '-', $retailer);

// Build scraper function name
$functionName = 'scrape_' . preg_replace('/[^a-z0-9]+/', '_', $retailer);

$scraperFile = __DIR__ . "/{$retailerSlug}/scraper.php";

if (!file_exists($scraperFile)) {
    exit("Retailer scraper not found: {$scraperFile}\n");
}

require_once $scraperFile;

if (!function_exists($functionName)) {
    exit("Function {$functionName}() not found.\n");
}

write_log($retailer, "========== Starting {$retailer} scraper ==========");

if ($variantRange !== null) {
    write_log($retailer, "Selected retailer positions: {$variantRange[0]}-{$variantRange[1]}");
}

// Initialize scrape summary
$summary = [
    'updated' => 0,
    'unchanged' => 0,
    'errors' => 0,
    'failed' => 0
];

write_log($retailer, ucfirst($retailer) . " variants queued: {$result->num_rows}");

// Prepare the common error-status update once
$errorStmt = $conn->prepare("UPDATE watch_variants SET last_checked = NOW(), last_scrape_status = 'error' WHERE id = ?");

while ($variant = $result->fetch_assoc()) {
    $watchName = "{$variant['brand']} {$variant['model_name']} ({$variant['color_name']})";

    write_log($retailer, "--------------------------------------------------");
    write_log($retailer, "Starting scrape for {$watchName} [Variant ID: {$variant['id']}]");
    write_log($retailer, "URL: {$variant['base_url']}");

    // Scrape the watch
    $scrapedData = $functionName($variant);

    // Validate scraper response
    $scrapeError = null;

    if (!is_array($scrapedData) || !isset($scrapedData['success'])) {
        $scrapeError = "Invalid scraper response.";
    } elseif (!$scrapedData['success']) {
        $scrapeError = $scrapedData['message'] ?? 'Unknown scraping error.';
    } elseif (!isset($scrapedData['price'], $scrapedData['available'], $scrapedData['status'])) {
        $scrapeError = "Incomplete scraper response.";
    }

    // Record scraping errors
    if ($scrapeError !== null) {
        write_log($retailer, $scrapeError);

        if ($errorStmt) {
            $errorStmt->bind_param("i", $variant['id']);

            if ($errorStmt->execute()) {
                $summary['errors']++;
            } else {
                $summary['failed']++;

                write_log($retailer, "Failed to record scrape error: " . $errorStmt->error);
            }
        } else {
            $summary['failed']++;

            write_log($retailer, "Failed to prepare scrape error update: " . $conn->error);
        }

        continue;
    }

    $price = (float)$scrapedData['price'];
    $available = $scrapedData['available'] ? 1 : 0;
    $status = $scrapedData['status'];

    $oldPrice = number_format((float)$variant['price'], 2);
    $newPrice = number_format($price, 2);

    $oldAvailabilityText = $variant['is_available'] ? 'In Stock' : 'Out of Stock';
    $newAvailabilityText = $available ? 'In Stock' : 'Out of Stock';

    // Compare against existing values
    $priceChanged = ((float)$variant['price'] !== $price);
    $availabilityChanged = ((int)$variant['is_available'] !== $available);
    $statusChanged = ($variant['last_scrape_status'] !== $status);

    if ($priceChanged || $availabilityChanged || $statusChanged) {
        $stmt = $conn->prepare("UPDATE watch_variants SET price = ?, is_available = ?, last_checked = NOW(), last_updated_by_scraper = 1, last_scrape_status = ? WHERE id = ?");

        if (!$stmt) {
            $summary['failed']++;

            write_log($retailer, "Prepare failed: " . $conn->error);
            continue;
        }

        $stmt->bind_param("disi", $price, $available, $status, $variant['id']);

        if ($stmt->execute()) {
            $summary['updated']++;

            write_log($retailer, "Changes detected for {$watchName}:");

            if ($priceChanged) {
                write_log($retailer, "Price: {$oldPrice} → {$newPrice}");
            }

            if ($availabilityChanged) {
                write_log($retailer, "Availability: {$oldAvailabilityText} → {$newAvailabilityText}");
            }

            if ($statusChanged) {
                write_log($retailer, "Status: {$variant['last_scrape_status']} → {$status}");
            }
        } else {
            $summary['failed']++;

            write_log($retailer, "Database update failed: " . $stmt->error);
        }

        $stmt->close();
    } else {
        // Only update last checked time
        $stmt = $conn->prepare("UPDATE watch_variants SET last_checked = NOW() WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param("i", $variant['id']);

            if ($stmt->execute()) {
                $summary['unchanged']++;

                write_log($retailer, "No changes detected.");
            } else {
                $summary['failed']++;

                write_log($retailer, "Database update failed: " . $stmt->error);
            }

            $stmt->close();
        } else {
            $summary['failed']++;

            write_log($retailer, "Prepare failed: " . $conn->error);
        }
    }

    write_log($retailer, "Finished scraping {$watchName}");
}

$selectStmt->close();

if ($errorStmt) {
    $errorStmt->close();
}

// Log consolidated scrape summary
write_log($retailer, "--------------------------------------------------");

write_log(
    $retailer,
    "WatchShelf update result: " .
        "updated={$summary['updated']}, " .
        "unchanged={$summary['unchanged']}, " .
        "errors={$summary['errors']}, " .
        "failed={$summary['failed']}"
);

write_log($retailer, "========== Finished {$retailer} scraper ==========");

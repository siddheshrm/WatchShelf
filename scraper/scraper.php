<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/logger.php';

// Check if the retailer name is provided as a command-line argument
$retailer = $argv[1] ?? null;

if (!$retailer) {
    exit("Usage: php scraper.php <retailer_name>\n");
}

$retailer = strtolower(trim($retailer));

// Fetch all watch variants for the specified retailer
$query = "SELECT watch_variants.*, watches.brand, watches.model_name
                FROM watch_variants
                INNER JOIN watches
                ON watches.id = watch_variants.watch_id
                WHERE LOWER(watch_variants.retailer_name) = ?
                AND watch_variants.base_url IS NOT NULL
                AND watch_variants.base_url <> ''";

$selectStmt = $conn->prepare($query);

if (!$selectStmt) {
    die("Database Error: " . $conn->error);
}

$selectStmt->bind_param("s", $retailer);
$selectStmt->execute();

$result = $selectStmt->get_result();

if ($result === false) {
    die("Database Error: " . $conn->error);
}

if ($result->num_rows === 0) {
    exit("No products found for retailer: {$retailer}\n");
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

while ($variant = $result->fetch_assoc()) {
    $watchName = "{$variant['brand']} {$variant['model_name']} ({$variant['color_name']})";

    write_log($retailer, "--------------------------------------------------");
    write_log(
        $retailer,
        "Starting scrape for {$watchName} [Variant ID: {$variant['id']}]"
    );
    write_log($retailer, "URL: {$variant['base_url']}");

    // Scrape the watch
    $scrapedData = $functionName($variant);

    // Validate response type
    if (!is_array($scrapedData) || !isset($scrapedData['success'])) {
        write_log($retailer, "Invalid scraper response.");

        $stmt = $conn->prepare("UPDATE watch_variants
                                                    SET last_checked = NOW(), last_scrape_status = 'error'
                                                    WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param("i", $variant['id']);
            $stmt->execute();
            $stmt->close();
        }

        continue;
    }

    // Scraper returned an error
    if (!$scrapedData['success']) {
        write_log(
            $retailer,
            $scrapedData['message'] ?? 'Unknown scraping error.'
        );

        $stmt = $conn->prepare("UPDATE watch_variants
                                                    SET last_checked = NOW(), last_scrape_status = 'error'
                                                    WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param("i", $variant['id']);
            $stmt->execute();
            $stmt->close();
        }

        continue;
    }

    // Successful response must contain these fields
    if (!isset($scrapedData['price'], $scrapedData['available'], $scrapedData['status'])) {
        write_log($retailer, "Incomplete scraper response.");

        $stmt = $conn->prepare("UPDATE watch_variants
                                                    SET last_checked = NOW(), last_scrape_status = 'error'
                                                    WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param("i", $variant['id']);
            $stmt->execute();
            $stmt->close();
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
        $stmt = $conn->prepare("UPDATE watch_variants
                                                    SET price = ?, is_available = ?, last_checked = NOW(), last_updated_by_scraper = 1, last_scrape_status = ?
                                                    WHERE id = ?");

        if (!$stmt) {
            write_log($retailer, "Prepare failed: " . $conn->error);
            continue;
        }

        $stmt->bind_param("disi", $price, $available, $status, $variant['id']);

        if ($stmt->execute()) {
            write_log(
                $retailer,
                "Changes detected for {$watchName}:"
            );

            if ($priceChanged) {
                write_log(
                    $retailer,
                    "Price: {$oldPrice} → {$newPrice}"
                );
            }

            if ($availabilityChanged) {
                write_log(
                    $retailer,
                    "Availability: {$oldAvailabilityText} → {$newAvailabilityText}"
                );
            }

            if ($statusChanged) {
                write_log(
                    $retailer,
                    "Status: {$variant['last_scrape_status']} → {$status}"
                );
            }
        } else {
            write_log(
                $retailer,
                "Database update failed: " . $stmt->error
            );
        }

        $stmt->close();
    } else {

        // Only update last checked time
        $stmt = $conn->prepare("UPDATE watch_variants
                                                    SET last_checked = NOW()
                                                    WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param("i", $variant['id']);
            $stmt->execute();
            $stmt->close();
        }

        write_log($retailer, "No changes detected.");
    }

    write_log(
        $retailer,
        "Finished scraping {$watchName}"
    );
}

$selectStmt->close();

write_log($retailer, "========== Finished {$retailer} scraper ==========");

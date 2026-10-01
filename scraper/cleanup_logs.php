<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

// Configuration
$daysToKeep = 14;

$scraperDirectory = __DIR__;
$cleanupLogDirectory = __DIR__ . '/cleanup-logs';

// Setup
if ($daysToKeep < 1) {
    exit("daysToKeep must be at least 1." . PHP_EOL);
}

if (!is_dir($cleanupLogDirectory)) {
    mkdir($cleanupLogDirectory, 0755, true);
}

$cleanupLogFile =
    $cleanupLogDirectory . '/' . date('Y-m-d_H-i-s') . '.log';

$cutoffTimestamp = time() - ($daysToKeep * 86400);

$deleted = 0;
$failed = 0;
$ignored = 0;

// Cleanup Logger
function cleanup_log(string $message): void
{
    global $cleanupLogFile;

    $timestamp = date('Y-m-d H:i:s');

    file_put_contents(
        $cleanupLogFile,
        "[{$timestamp}] {$message}" . PHP_EOL,
        FILE_APPEND
    );
}

// Start
cleanup_log("Log cleanup started.");
cleanup_log("Retention period: {$daysToKeep} days.");
cleanup_log(
    "Deleting logs older than: " .
        date('Y-m-d H:i:s', $cutoffTimestamp)
);

// Scan Retailer / Brand Directories
$directories = glob($scraperDirectory . '/*', GLOB_ONLYDIR);

foreach ($directories as $directory) {
    $logDirectory = $directory . '/logs';

    if (!is_dir($logDirectory)) {
        continue;
    }

    $source = basename($directory);

    $logFiles = glob($logDirectory . '/*.log');

    foreach ($logFiles as $logFile) {
        $filename = basename($logFile);

        // Expected format: YYYY-MM-DD_HH-MM-SS.log
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d_H-i-s',
            pathinfo($filename, PATHINFO_FILENAME),
            new DateTimeZone('Asia/Kolkata')
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false ||
            (
                $errors !== false &&
                ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
            )
        ) {
            $ignored++;
            continue;
        }

        if ($date->getTimestamp() >= $cutoffTimestamp) {
            continue;
        }

        if (unlink($logFile)) {
            $deleted++;

            cleanup_log(
                "DELETED | {$source}/logs/{$filename}"
            );
        } else {
            $failed++;

            cleanup_log(
                "FAILED | {$source}/logs/{$filename}"
            );
        }
    }
}

// Summary
cleanup_log(
    "Cleanup completed. " .
        "Deleted: {$deleted}, " .
        "Failed: {$failed}, " .
        "Ignored: {$ignored}."
);

/* echo "Cleanup completed." . PHP_EOL;
echo "Deleted: {$deleted}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;
echo "Ignored: {$ignored}" . PHP_EOL; */

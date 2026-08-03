<?php

date_default_timezone_set('Asia/Kolkata');

$GLOBALS['SCRAPER_LOG_FILES'] = [];

function write_log(string $retailer, string $message): void
{
    $retailer = str_replace(' ', '-', strtolower(trim($retailer)));

    // Create retailer log directory if it doesn't exist
    $logDirectory = __DIR__ . "/{$retailer}/logs";

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0755, true);
    }

    // Create a unique log file once per execution
    if (!isset($GLOBALS['SCRAPER_LOG_FILES'][$retailer])) {
        $GLOBALS['SCRAPER_LOG_FILES'][$retailer] =
            $logDirectory . '/' . date('Y-m-d_H-i-s') . '.log';
    }

    $logFile = $GLOBALS['SCRAPER_LOG_FILES'][$retailer];

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

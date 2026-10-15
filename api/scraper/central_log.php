<?php

require_once __DIR__ . '/../../config/app.php';

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    respond(405, [
        "success" => false,
        "error" => "Method not allowed",
    ]);
}

// Authentication
$providedKey = $_SERVER['HTTP_X_SCRAPER_KEY'] ?? '';

if (!hash_equals(SCRAPER_API_KEY, $providedKey)) {
    respond(401, [
        'success' => false,
        'error' => 'Unauthorized',
    ]);
}

// Request validation
$maxBytes = 10 * 1024 * 1024;

if (
    isset($_SERVER["CONTENT_LENGTH"]) &&
    (int) $_SERVER["CONTENT_LENGTH"] > $maxBytes + 1024
) {
    respond(413, [
        "success" => false,
        "error" => "Request too large",
    ]);
}

$raw = file_get_contents(
    "php://input",
    false,
    null,
    0,
    $maxBytes + 1025
);

if ($raw === false || strlen($raw) > $maxBytes + 1024) {
    respond(413, [
        "success" => false,
        "error" => "Request too large",
    ]);
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    respond(400, [
        "success" => false,
        "error" => "Invalid JSON",
    ]);
}

$retailer = $data["retailer"] ?? null;
$filename = $data["filename"] ?? null;
$content = $data["content"] ?? null;

if (
    !is_string($retailer) ||
    !in_array($retailer, ["casio", "amazon", "titan"], true)
) {
    respond(400, [
        "success" => false,
        "error" => "Invalid retailer",
    ]);
}

if (
    !is_string($filename) ||
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.log$/D',
        $filename
    )
) {
    respond(400, [
        "success" => false,
        "error" => "Invalid filename",
    ]);
}

if (
    !is_string($content) ||
    $content === "" ||
    strlen($content) > $maxBytes ||
    !preg_match('//u', $content)
) {
    respond(400, [
        "success" => false,
        "error" => "Invalid log content or size",
    ]);
}

// Store completed log
$baseDirectory = dirname(__DIR__, 2) . "/scraper";

$directory = $baseDirectory . "/" . $retailer . "/logs";

if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    respond(500, [
        "success" => false,
        "error" => "Unable to create log directory",
    ]);
}

$filepath = $directory . "/" . $filename;

// Exclusive creation prevents overwriting an existing log.
$handle = @fopen($filepath, "x");

if ($handle === false) {
    respond(409, [
        "success" => false,
        "error" => "Log already exists or cannot be created",
    ]);
}

$length = strlen($content);
$written = 0;

while ($written < $length) {
    $bytes = fwrite($handle, substr($content, $written));

    if ($bytes === false || $bytes === 0) {
        break;
    }

    $written += $bytes;
}

fclose($handle);

if ($written !== $length) {
    @unlink($filepath);

    respond(500, [
        "success" => false,
        "error" => "Incomplete log write",
    ]);
}

respond(200, [
    "success" => true,
    "retailer" => $retailer,
    "filename" => $filename,
    "bytes" => $written,
]);

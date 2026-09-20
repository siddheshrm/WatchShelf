<?php

/*
Returns:
[
    'success'   => bool,
    'price'     => float,
    'available' => bool,
    'status'    => 'success' | 'out_of_stock' | 'error',
    'message'   => string // Present only when status = 'error'
]
*/

function scrape_boderry(array $variant): array
{
    $url = $variant['base_url'];

    // Initialize cURL
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
        ]
    ]);

    $html = curl_exec($ch);

    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        curl_close($ch);

        return [
            'success' => false,
            'status' => 'error',
            'message' => "cURL Error: {$error}"
        ];
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => "HTTP {$httpCode} received."
        ];
    }

    if (empty($html)) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Empty HTML received.'
        ];
    }

    // Parse HTML
    $dom = new DOMDocument();

    libxml_use_internal_errors(true);

    if (!$dom->loadHTML($html)) {
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to parse HTML.'
        ];
    }

    libxml_clear_errors();
    libxml_use_internal_errors(false);

    $xpath = new DOMXPath($dom);

    // Find JSON-LD blocks
    $scripts = $xpath->query("//script[@type='application/ld+json']");

    if ($scripts->length === 0) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'No JSON-LD found.'
        ];
    }

    foreach ($scripts as $script) {
        $json = json_decode(trim($script->textContent), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            continue;
        }

        if (($json['@type'] ?? '') !== 'Product') {
            continue;
        }

        if (
            !isset($json['offers'][0]['price']) ||
            !isset($json['offers'][0]['availability'])
        ) {
            continue;
        }

        $offer = $json['offers'][0];

        $price = (float)$offer['price'];

        if ($price <= 0) {
            continue;
        }

        // Convert USD to INR
        require_once __DIR__ . '/../currency.php';

        $price = usd_to_inr($price);

        $available = (
            $offer['availability'] === 'https://schema.org/InStock'
        );

        return [
            'success' => true,
            'price' => $price,
            'available' => $available,
            'status' => $available ? 'success' : 'out_of_stock'
        ];
    }

    return [
        'success' => false,
        'status' => 'error',
        'message' => 'Product data not found in JSON-LD.'
    ];
}

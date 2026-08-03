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

function scrape_fossil(array $variant): array
{
    $url = $variant['base_url'];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1'
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

        if (!isset($json['offers']) || !is_array($json['offers']) || !isset($json['offers']['lowPrice'], $json['offers']['availability'])) {
            continue;
        }

        $offer = $json['offers'];

        $price = (float)$offer['lowPrice'];

        if ($price <= 0) {
            continue;
        }

        $available = (
            stripos($offer['availability'], 'InStock') !== false
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

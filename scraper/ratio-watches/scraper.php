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

function scrape_ratio_watches(array $variant): array
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
        CURLOPT_COOKIE => 'localization=IN; cart_currency=INR',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36'
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

    if (!preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Product JSON-LD not found.'
        ];
    }

    $json = null;

    foreach ($matches[1] as $script) {
        $decoded = json_decode(trim($script), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            continue;
        }

        if (($decoded['@type'] ?? '') !== 'Product') {
            continue;
        }

        $json = $decoded;
        break;
    }

    if ($json === null) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Product JSON-LD not found.'
        ];
    }

    if (
        !isset(
            $json['offers']['price'],
            $json['offers']['availability']
        )
    ) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Required product data not found.'
        ];
    }

    $price = (float) $json['offers']['price'];

    if ($price <= 0) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Invalid product price.'
        ];
    }

    $available = stripos(
        $json['offers']['availability'],
        'InStock'
    ) !== false;

    return [
        'success' => true,
        'price' => $price,
        'available' => $available,
        'status' => $available ? 'success' : 'out_of_stock'
    ];
}

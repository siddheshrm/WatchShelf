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

function scrape_tata_cliq(array $variant): array
{
    $url = $variant['base_url'];

    // Extract Tata Cliq product ID
    if (!preg_match('/\/p-(mp\d+)/i', $url, $matches)) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to extract product ID.'
        ];
    }

    $productId = $matches[1];

    // Build API URL
    $apiUrl = "https://www.tatacliq.com/marketplacewebservices/v2/mpl/products/productDetails/{$productId}" . "?isPwa=true&isMDE=true&isDynamicVar=true";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
        ]
    ]);

    $response = curl_exec($ch);

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

    if (empty($response)) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Empty API response.'
        ];
    }

    $json = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to decode API response.'
        ];
    }

    if (($json['status'] ?? '') !== 'SUCCESS') {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'API returned an unsuccessful response.'
        ];
    }

    if (
        !isset(
            $json['winningSellerPrice']['value'],
            $json['winningSellerAvailableStock']
        )
    ) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Required product data not found.'
        ];
    }

    $price = (float)$json['winningSellerPrice']['value'];

    if ($price <= 0) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Invalid product price.'
        ];
    }

    $available = ($json['winningSellerAvailableStock'] > 0);

    return [
        'success' => true,
        'price' => $price,
        'available' => $available,
        'status' => $available ? 'success' : 'out_of_stock'
    ];
}

<?php

/*
Returns:
[
    'success'   => bool,
    'price'     => float,
    'available' => bool,
    'status'    => 'success' | 'out_of_stock' | 'error',
    'message'   => string // only on failure
]
*/

function scrape_timex(array $variant): array
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $variant['base_url'],
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

    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'status'  => 'error',
            'message' => "cURL Error: {$error}"
        ];
    }

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'status'  => 'error',
            'message' => "HTTP {$httpCode} received."
        ];
    }

    if (empty($html)) {
        return [
            'success' => false,
            'status'  => 'error',
            'message' => 'Empty HTML received.'
        ];
    }

    if (
        !preg_match(
            '/window\.SwymProductInfo\.product\s*=\s*(\{.*?\});/s',
            $html,
            $matches
        )
    ) {
        return [
            'success' => false,
            'status'  => 'error',
            'message' => 'Product data not found.'
        ];
    }

    $json = json_decode($matches[1], true);

    if (!is_array($json)) {
        return [
            'success' => false,
            'status'  => 'error',
            'message' => 'Unable to decode product data.'
        ];
    }

    if (
        !isset($json['price']) ||
        !isset($json['available'])
    ) {
        return [
            'success' => false,
            'status'  => 'error',
            'message' => 'Price or availability missing.'
        ];
    }

    $price = $json['price'] / 100;

    $available = (bool)$json['available'];

    return [
        'success'   => true,
        'price'     => $price,
        'available' => $available,
        'status'    => $available ? 'success' : 'out_of_stock'
    ];
}

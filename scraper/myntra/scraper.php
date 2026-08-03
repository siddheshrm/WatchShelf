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

function scrape_myntra(array $variant): array
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

    preg_match_all(
        '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
        $html,
        $matches
    );

    $scripts = $matches[1];

    if (empty($scripts)) {
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'No JSON-LD found.'
        ];
    }

    foreach ($scripts as $jsonText) {
        $jsonText = trim(
            html_entity_decode($jsonText, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );

        // Preserve line breaks inside JSON string values
        $jsonText = preg_replace_callback(
            '/"((?:[^"\\\\]|\\\\.)*)"/s',
            function ($matches) {
                return '"' . str_replace(
                    ["\r\n", "\r", "\n"],
                    ['\\n', '\\n', '\\n'],
                    $matches[1]
                ) . '"';
            },
            $jsonText
        );

        $json = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
            continue;
        }

        if (($json['@type'] ?? '') !== 'Product') {
            continue;
        }

        $offers = $json['offers'] ?? null;

        if (!is_array($offers) || !isset($offers['price'], $offers['availability'])) {
            continue;
        }

        $price = (float) $offers['price'];

        if ($price <= 0) {
            continue;
        }

        $available = (
            strtolower($offers['availability']) === 'instock'
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

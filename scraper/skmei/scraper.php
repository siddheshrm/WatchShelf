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

function scrape_skmei(array $variant): array
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

    preg_match_all(
        '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
        $html,
        $matches
    );

    $scripts = $matches[1];

    if (empty($scripts)) {
        return [
            'success' => false,
            'status'  => 'error',
            'message' => 'No JSON-LD found.'
        ];
    }

    foreach ($scripts as $jsonText) {

        $json = json_decode(
            html_entity_decode(
                trim($jsonText),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ),
            true
        );

        if (!is_array($json)) {
            continue;
        }

        if (($json['@type'] ?? '') !== 'Product') {
            continue;
        }

        $offers = $json['offers'] ?? null;

        if (!is_array($offers)) {
            continue;
        }

        if (!isset($offers['price']) || !isset($offers['availability'])) {
            continue;
        }

        $available = stripos(
            $offers['availability'],
            'InStock'
        ) !== false;

        return [
            'success'   => true,
            'price'     => (float) $offers['price'],
            'available' => $available,
            'status'    => $available ? 'success' : 'out_of_stock'
        ];
    }

    return [
        'success' => false,
        'status'  => 'error',
        'message' => 'Product data not found in JSON-LD.'
    ];
}

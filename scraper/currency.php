<?php

require_once __DIR__ . '/../config/app.php';

const USD_INR_CACHE_FILE = __DIR__ . '/cache/usd_inr.json';
const CACHE_LIFETIME = 86400; // 24 hours

function usd_to_inr(float $usd): float
{
    $rate = get_usd_inr_rate();

    return round($usd * $rate, 2);
}

function get_usd_inr_rate(): float
{
    // Use cached rate if still valid
    if (file_exists(USD_INR_CACHE_FILE)) {
        $cache = json_decode(file_get_contents(USD_INR_CACHE_FILE), true);

        if (is_array($cache) && isset($cache['rate'], $cache['updated_at']) && (time() - $cache['updated_at']) < CACHE_LIFETIME) {
            return (float)$cache['rate'];
        }
    }

    // Fetch latest rate
    $url = 'https://v6.exchangerate-api.com/v6/' . EXCHANGE_RATE_API_KEY . '/latest/USD';

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0'
        ]
    ]);

    $response = curl_exec($ch);

    $curlError = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if (!$curlError && $httpCode === 200) {
        $json = json_decode($response, true);

        if (isset($json['result']) && $json['result'] === 'success' && isset($json['conversion_rates']['INR'])) {
            $rate = (float)$json['conversion_rates']['INR'];

            file_put_contents(
                USD_INR_CACHE_FILE,
                json_encode([
                    'rate' => $rate,
                    'updated_at' => time()
                ], JSON_PRETTY_PRINT)
            );

            return $rate;
        }
    }

    // API failed: fall back to old cache
    if (file_exists(USD_INR_CACHE_FILE)) {
        $cache = json_decode(file_get_contents(USD_INR_CACHE_FILE), true);

        if (is_array($cache) && isset($cache['rate'])) {
            return (float)$cache['rate'];
        }
    }

    // Final fallback
    return 95.00;
}

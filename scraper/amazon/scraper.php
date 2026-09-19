<?php

/*
Returns:
[
    'success'   => bool,
    'price'     => float|null,
    'available' => bool|null,
    'status'    => 'success' | 'out_of_stock' | 'error' | 'captcha',
    'message'   => string // Present only when status = 'error' or 'captcha'
]
*/

function scrape_amazon(array $variant): array
{
    $url = trim($variant['base_url'] ?? '');

    if ($url === '') {
        return [
            'success'   => false,
            'price'     => null,
            'available' => null,
            'status'    => 'error',
            'message'   => 'Amazon URL is empty.'
        ];
    }

    /*
     * Normalize Amazon URLs to:
     * https://www.amazon.in/dp/ASIN
     */
    if (preg_match('#/dp/([A-Z0-9]{10})#i', $url, $matches)) {
        $asin = strtoupper($matches[1]);
        $url = "https://www.amazon.in/dp/{$asin}";
    }

    $maxAttempts = 2;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

        write_log(
            'amazon',
            "Request attempt {$attempt}/{$maxAttempts}"
        );

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT =>
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                'Chrome/138.0.0.0 Safari/537.36',
        ]);

        $html = curl_exec($ch);

        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        write_log(
            'amazon',
            "HTTP status: {$httpCode}"
        );

        /*
         * cURL / network error.
         */
        if ($curlErrno !== 0 || $html === false) {

            if ($attempt < $maxAttempts) {
                sleep(1);
                continue;
            }

            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'error',
                'message'   => "Amazon request failed: {$curlError}"
            ];
        }

        /*
         * Empty response.
         */
        if ($html === '') {

            if ($attempt < $maxAttempts) {
                sleep(1);
                continue;
            }

            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'error',
                'message'   => 'Empty HTML received.'
            ];
        }

        /*
         * CAPTCHA / bot-check detection.
         */
        $isCaptcha =
            stripos($html, 'validateCaptcha') !== false ||
            stripos($html, 'api-services-support@amazon.com') !== false ||
            stripos($html, 'Enter the characters you see below') !== false ||
            stripos(
                $html,
                "Sorry, we just need to make sure you're not a robot"
            ) !== false ||
            stripos(
                $html,
                'Type the characters you see in this image'
            ) !== false ||
            stripos($html, 'Robot Check') !== false ||
            (
                stripos($html, 'captcha') !== false &&
                stripos($html, 'amazon') !== false
            );

        if ($isCaptcha) {

            if ($attempt < $maxAttempts) {
                sleep(2);
                continue;
            }

            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'captcha',
                'message'   => 'Amazon CAPTCHA / challenge page detected.'
            ];
        }

        /*
         * Retry temporary HTTP responses.
         */
        if ($httpCode === 429 || $httpCode >= 500) {

            if ($attempt < $maxAttempts) {
                sleep(2);
                continue;
            }

            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'error',
                'message'   => "Amazon returned HTTP status {$httpCode}."
            ];
        }

        /*
         * Other HTTP errors.
         */
        if ($httpCode !== 200) {
            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'error',
                'message'   => "Amazon returned HTTP status {$httpCode}."
            ];
        }

        /*
         * Parse HTML.
         */
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        if (!$dom->loadHTML($html)) {
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'error',
                'message'   => 'Failed to parse Amazon HTML.'
            ];
        }

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($dom);

        /*
         * Determine availability.
         */
        $availabilitySelectors = [
            "//*[@id='outOfStock']",
            "//*[@id='availability']//span[contains(@class, 'primary-availability-message')]"
        ];

        $isOutOfStock = false;

        foreach ($availabilitySelectors as $selector) {

            $nodes = $xpath->query($selector);

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {

                $availabilityText = strtolower(
                    trim(preg_replace('/\s+/', ' ', $node->textContent))
                );

                if (
                    strpos($availabilityText, 'currently unavailable') !== false ||
                    strpos($availabilityText, 'out of stock') !== false ||
                    strpos($availabilityText, 'temporarily out of stock') !== false ||
                    strpos(
                        $availabilityText,
                        "we don't know when or if this item will be back in stock"
                    ) !== false
                ) {
                    $isOutOfStock = true;
                    break 2;
                }
            }
        }

        write_log(
            'amazon',
            'Availability: ' . ($isOutOfStock ? 'OUT OF STOCK' : 'IN STOCK')
        );

        /*
         * Determine price.
         */
        $priceSelectors = [
            "//*[@id='corePrice_feature_div']//span[contains(@class, 'a-price-whole')]",
            "//*[@id='apex_desktop']//span[contains(@class, 'a-price-whole')]",
            "//span[contains(@class, 'a-price-whole')]",
            "//*[@id='priceblock_ourprice']",
            "//*[@id='priceblock_dealprice']",
            "//*[@id='priceblock_saleprice']"
        ];

        $productPrice = null;

        foreach ($priceSelectors as $selector) {

            $nodes = $xpath->query($selector);

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {

                $candidate = trim($node->textContent);

                if ($candidate === '') {
                    continue;
                }

                $candidate = preg_replace('/[^\d.,]/', '', $candidate);
                $candidate = str_replace(',', '', $candidate);

                if ($candidate !== '' && is_numeric($candidate)) {
                    $productPrice = (float) $candidate;
                    break 2;
                }
            }
        }

        /*
         * An out-of-stock product can still be a valid result
         * even when Amazon does not expose a price.
         */
        if ($productPrice === null) {

            if ($isOutOfStock) {

                write_log(
                    'amazon',
                    'Price: Not found'
                );

                return [
                    'success'   => true,
                    'price'     => null,
                    'available' => false,
                    'status'    => 'out_of_stock'
                ];
            }

            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'error',
                'message'   => 'Product price not found.'
            ];
        }

        /*
         * Validate price.
         */
        if ($productPrice <= 0) {
            return [
                'success'   => false,
                'price'     => null,
                'available' => null,
                'status'    => 'error',
                'message'   => 'Invalid product price.'
            ];
        }

        write_log(
            'amazon',
            'Price: ₹' . number_format($productPrice, 2)
        );

        /*
         * Final result.
         */
        if ($isOutOfStock) {
            return [
                'success'   => true,
                'price'     => $productPrice,
                'available' => false,
                'status'    => 'out_of_stock'
            ];
        }

        return [
            'success'   => true,
            'price'     => $productPrice,
            'available' => true,
            'status'    => 'success'
        ];
    }

    return [
        'success'   => false,
        'price'     => null,
        'available' => null,
        'status'    => 'error',
        'message'   => 'Amazon scraper failed unexpectedly.'
    ];
}

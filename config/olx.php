<?php

return [
    'listing_url_prefix' => env('OLX_LISTING_URL_PREFIX', 'https://www.olx.ua/'),
    'payment_base_url' => env('OLX_PAYMENT_BASE_URL', 'https://ua.production.delivery.olx.tools'),
    'http' => [
        'timeout_seconds' => (int) env('OLX_HTTP_TIMEOUT_SECONDS', 10),
        'retry_times' => (int) env('OLX_HTTP_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('OLX_HTTP_RETRY_SLEEP_MS', 200),
    ],
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('ALABIQ_CURRENCY', 'SAR'),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => env('ALABIQ_TIMEZONE', 'Asia/Riyadh'),

    /*
    |--------------------------------------------------------------------------
    | Default Delivery Fee
    |--------------------------------------------------------------------------
    */
    'default_delivery_fee' => env('ALABIQ_DEFAULT_DELIVERY_FEE', 10),

    /*
    |--------------------------------------------------------------------------
    | Platform Commission Rate
    |--------------------------------------------------------------------------
    */
    'platform_commission_rate' => env('ALABIQ_PLATFORM_COMMISSION_RATE', 0.15),

    /*
    |--------------------------------------------------------------------------
    | VAT Rate
    |--------------------------------------------------------------------------
    */
    'vat_rate' => env('ALABIQ_VAT_RATE', 0.15),

    /*
    |--------------------------------------------------------------------------
    | Mapbox Configuration
    |--------------------------------------------------------------------------
    */
    'mapbox' => [
        'token' => env('MAPBOX_TOKEN'),
        'style' => 'mapbox://styles/mapbox/streets-v12',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tap Payments Configuration
    |--------------------------------------------------------------------------
    */
    'tap_payments' => [
        'secret_key' => env('TAP_PAYMENTS_SECRET_KEY'),
        'public_key' => env('TAP_PAYMENTS_PUBLIC_KEY'),
        'webhook_secret' => env('TAP_PAYMENTS_WEBHOOK_SECRET'),
        'sandbox' => env('TAP_PAYMENTS_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pusher Configuration (for real-time updates)
    |--------------------------------------------------------------------------
    */
    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MinIO (S3-compatible storage)
    |--------------------------------------------------------------------------
    */
    'minio' => [
        'endpoint' => env('MINIO_ENDPOINT', 'http://minio:9000'),
        'bucket' => env('MINIO_BUCKET', 'alabiq'),
        'key' => env('MINIO_ROOT_USER', 'minioadmin'),
        'secret' => env('MINIO_ROOT_PASSWORD', 'minioadmin'),
        'use_path_style' => env('MINIO_USE_PATH_STYLE_ENDPOINT', true),
    ],
];

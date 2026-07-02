<?php

return [
    'default_delivery_fee' => (float) env('STORE_DEFAULT_DELIVERY_FEE', 600),
    'free_shipping_threshold' => (float) env('STORE_FREE_SHIPPING_THRESHOLD', 0),

    'delivery' => [
        // default provider used by the "Dispatch" button in admin
        'default' => env('DELIVERY_DEFAULT', 'manual'),

        'providers' => [
            'noest' => [
                'enabled'   => (bool) env('NOEST_ENABLED', false),
                'base_url'  => env('NOEST_BASE_URL', 'https://app.noest-dz.com'),
                'api_token' => env('NOEST_API_TOKEN'),
                'guid'      => env('NOEST_GUID'),
            ],
            'yalidine' => [
                'enabled'   => (bool) env('YALIDINE_ENABLED', false),
                'base_url'  => env('YALIDINE_BASE_URL', 'https://api.yalidine.app/v1'),
                'api_id'    => env('YALIDINE_API_ID'),
                'api_token' => env('YALIDINE_API_TOKEN'),
            ],
        ],
    ],
];

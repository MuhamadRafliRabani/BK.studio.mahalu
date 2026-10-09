<?php

return [
    'va' => env('IPAYMU_VA'),
    'api_key' => env('IPAYMU_API_KEY'),
    'environment' => env('IPAYMU_ENV', 'sandbox'),
    'base_url' => env('IPAYMU_ENV', 'sandbox') === 'production'
        ? 'https://my.ipaymu.com'
        : 'https://sandbox.ipaymu.com',
    'notify_url' => env('IPAYMU_NOTIFY_URL'),
    'return_url' => env('IPAYMU_RETURN_URL', 'http://localhost:3000/payment/{referenceId}/notice'),
    'cancel_url' => env('IPAYMU_CANCEL_URL', 'http://localhost:3000/payment/{referenceId}/notice?from=cancel'),
    'expiry_minutes' => (int) env('IPAYMU_EXPIRY_MINUTES', 15),
];
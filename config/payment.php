<?php

return [
    'gateway' => env('PAYMENT_GATEWAY', 'ipy'),
    'supported_gateways' => ['ipy', 'mds'],
    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 15),
];
<?php

return [
    'paths' => ['api/*', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Comma-separated list, e.g. "https://shop.example.com,https://admin.example.com".
    // Never use "*" for an authenticated API.
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:4200'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With', 'X-Cart-Token', 'X-XSRF-TOKEN'],

    'exposed_headers' => ['X-Cart-Token', 'Retry-After', 'X-RateLimit-Remaining', 'Content-Disposition'],

    'max_age' => 3600,

    'supports_credentials' => false,
];

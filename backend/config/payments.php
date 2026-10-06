<?php

return [

    /*
    | Simulated "Card (Demo)" / "UPI (Demo)" methods. Handy for local demos without any
    | gateway account; turn them off in production.
    */
    'demo_methods' => (bool) env('PAYMENT_DEMO_METHODS', true),

    /*
    | Razorpay (https://razorpay.com). Use test-mode keys (rzp_test_...) while developing.
    | key_id is public and is sent to the browser; key_secret and webhook_secret never leave
    | the server.
    */
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
        'timeout' => (int) env('RAZORPAY_TIMEOUT', 15),
        'theme_color' => env('RAZORPAY_THEME_COLOR', '#d7263d'),
    ],

];

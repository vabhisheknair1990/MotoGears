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

    /*
    | UPI shown on our own page: a QR code to scan with any UPI app (UPI intent) or a
    | collect request to the customer's UPI ID. The page polls the payment status and the
    | Razorpay webhook confirms it too, so the order moves on even if the tab is closed.
    |
    | provider: "auto" (Razorpay when keys are set, otherwise the demo simulator when demo
    | methods are on), "razorpay" or "demo". Razorpay needs UPI S2S (intent + collect)
    | enabled on the account — ask Razorpay support to switch it on.
    */
    'upi' => [
        'enabled' => (bool) env('UPI_ENABLED', true),
        'provider' => env('UPI_PROVIDER', 'auto'),
        'qr_ttl_minutes' => (int) env('UPI_QR_TTL_MINUTES', 10),
        'collect_ttl_minutes' => (int) env('UPI_COLLECT_TTL_MINUTES', 5),
        // Demo simulator only: the payee shown in the QR code.
        'demo_vpa' => env('UPI_DEMO_VPA', 'motogears@demo'),
    ],

];

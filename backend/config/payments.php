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
        'theme_color' => env('RAZORPAY_THEME_COLOR', '#da1e25'),
    ],

    /*
    | UPI shown on our own page: a single-use QR code for the exact total, scanned with any
    | UPI app. The page polls the payment status and the Razorpay qr_code.credited webhook
    | confirms it too, so the order moves on even if the tab is closed.
    |
    | provider: "auto" (Razorpay when keys are set, otherwise the demo simulator when demo
    | methods are on), "razorpay" or "demo". Razorpay uses its UPI QR Codes API, an on-demand
    | feature: ask Razorpay support to enable it. Paying with a UPI ID or UPI app button goes
    | through Razorpay's window (NPCI retired UPI Collect on merchant pages). The collect
    | setting below only applies to the simulator.
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

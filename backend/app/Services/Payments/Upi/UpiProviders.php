<?php

namespace App\Services\Payments\Upi;

use App\Services\Payments\Razorpay\RazorpayClient;

/** Picks the UPI provider from config('payments.upi.provider'). */
class UpiProviders
{
    public function __construct(private RazorpayClient $razorpay) {}

    /** Provider used for new payments, or null when in-page UPI isn't available. */
    public function current(): ?UpiProvider
    {
        if (! config('payments.upi.enabled', true)) {
            return null;
        }
        $choice = config('payments.upi.provider', 'auto');
        $provider = match ($choice) {
            'razorpay' => app(RazorpayUpiProvider::class),
            'demo' => app(DemoUpiProvider::class),
            default => $this->razorpay->isConfigured()
                ? app(RazorpayUpiProvider::class)
                : ((bool) config('payments.demo_methods', true) ? app(DemoUpiProvider::class) : null),
        };

        return $provider?->isAvailable() ? $provider : null;
    }

    /** Provider that handled an existing payment (checks and refunds go back to it). */
    public function forGateway(?string $gateway): UpiProvider
    {
        return $gateway === 'razorpay' ? app(RazorpayUpiProvider::class) : app(DemoUpiProvider::class);
    }
}

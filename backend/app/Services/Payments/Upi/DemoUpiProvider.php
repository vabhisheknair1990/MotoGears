<?php

namespace App\Services\Payments\Upi;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;
use App\Services\SettingsService;
use Illuminate\Support\Str;

/**
 * Simulated UPI for local demos (no money moves). The QR code is a real upi:// link to a
 * demo payee; "paying" happens with the Simulate buttons. Collect requests are approved
 * after a few seconds, except UPI IDs starting with "fail", which are declined.
 */
class DemoUpiProvider implements UpiProvider
{
    public const APPROVE_AFTER_SECONDS = 4;

    public function __construct(private SettingsService $settings) {}

    public function name(): string
    {
        return 'demo';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isDemo(): bool
    {
        return true;
    }

    public function openOrder(Order $order, Payment $payment): string
    {
        return 'upi_demo_'.Str::lower(Str::random(14));
    }

    public function intent(Order $order, Payment $payment, array $client, array $previous = []): array
    {
        $link = 'upi://pay?'.http_build_query([
            'pa' => config('payments.upi.demo_vpa', 'motogears@demo'),
            'pn' => (string) $this->settings->get('store_name'),
            'am' => number_format((float) $payment->amount, 2, '.', ''),
            'cu' => $payment->currency,
            'tn' => 'Order '.$order->order_number,
            'tr' => $payment->gateway_order_id,
        ], '', '&', PHP_QUERY_RFC3986);

        return ['link' => $link, 'reference' => 'demo_pay_'.Str::lower(Str::random(12)), 'open_app' => true];
    }

    public function supportsCollect(): bool
    {
        return true;
    }

    public function collect(Order $order, Payment $payment, string $vpa, array $client): array
    {
        return ['reference' => 'demo_pay_'.Str::lower(Str::random(12))];
    }

    public function validateVpa(string $vpa): ?array
    {
        $user = Str::before(strtolower($vpa), '@');

        return str_starts_with($user, 'invalid')
            ? ['valid' => false, 'name' => null]
            : ['valid' => true, 'name' => 'Demo Customer'];
    }

    public function check(Payment $payment): UpiCheck
    {
        $meta = $payment->meta ?? [];
        $outcome = $meta['demo_outcome'] ?? null;
        if (! $outcome && ($meta['upi_flow'] ?? null) === 'collect' && isset($meta['upi_started_at'])
            && now()->diffInSeconds($meta['upi_started_at'], true) >= self::APPROVE_AFTER_SECONDS) {
            $outcome = ($meta['demo_decline'] ?? false) ? 'failure' : 'success';
        }

        return match ($outcome) {
            'success' => UpiCheck::paid('UPI'.Str::upper(Str::random(12)), ['rzp_method' => 'upi']),
            'failure' => UpiCheck::failed('The UPI request was declined by the payer (demo).'),
            default => UpiCheck::pending(),
        };
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        return PaymentResult::success('UPI-RF-'.Str::upper(Str::random(10)));
    }
}

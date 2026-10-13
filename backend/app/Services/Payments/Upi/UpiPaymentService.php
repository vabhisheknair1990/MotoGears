<?php

namespace App\Services\Payments\Upi;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * In-page UPI: show a QR code (or, with providers that allow it, send a collect request to a
 * UPI ID), then poll the provider until the money arrives. Whoever confirms first — status
 * poll or webhook — marks the order paid; OrderService makes that idempotent and refunds late
 * duplicates.
 *
 * The browser never decides the outcome: "paid" only ever comes from the provider.
 */
class UpiPaymentService
{
    /** Remote status checks per payment are spaced out at least this much (seconds). */
    private const CHECK_INTERVAL = 2;

    public function __construct(
        private UpiProviders $providers,
        private OrderService $orders,
        private SettingsService $settings,
    ) {}

    /** Show (or reuse) a QR code for the order. */
    public function qr(Order $order, ?User $by, array $client = []): array
    {
        $payment = $this->attempt($order, $by);
        $meta = $payment->meta ?? [];
        $reusable = ($meta['upi_flow'] ?? null) === 'intent' && isset($meta['upi_link'], $meta['upi_expires_at'])
            && Carbon::parse($meta['upi_expires_at'])->isAfter(now()->addSeconds(30));

        if (! $reusable) {
            $provider = $this->providers->forGateway($payment->gateway);
            $refs = array_values(array_filter([...(array) ($meta['upi_refs'] ?? []), $meta['upi_ref'] ?? null]));
            $started = $this->call(fn () => $provider->intent($order, $payment, $client, $refs));
            $expires = isset($started['expires_at']) ? Carbon::createFromTimestamp($started['expires_at']) : now()->addMinutes($this->ttl('qr'));
            $meta = array_merge($this->freshMeta($meta), [
                'upi_flow' => 'intent',
                'upi_ref' => $started['reference'],
                // Every code shown for this payment: a customer may pay an older one just before it closes.
                'upi_refs' => array_slice(array_values(array_unique(array_filter([...$refs, $started['reference']]))), -10),
                'upi_link' => $started['link'],
                'upi_open_app' => (bool) ($started['open_app'] ?? true),
                'upi_started_at' => now()->toIso8601String(),
                'upi_expires_at' => $expires->toIso8601String(),
            ]);
            $payment->update(['meta' => $meta]);
        }

        return $this->session($order, $payment->fresh(), 'pending') + [
            'qr' => UpiQr::dataUri($meta['upi_link']),
            // Only links a UPI app may open directly are offered as an "Open UPI app" button.
            'link' => ($meta['upi_open_app'] ?? true) ? $meta['upi_link'] : null,
        ];
    }

    /** Send a payment request to the customer's UPI ID. */
    public function collect(Order $order, string $vpa, ?User $by, array $client = []): array
    {
        $vpa = strtolower(trim($vpa));
        $payment = $this->attempt($order, $by);
        $provider = $this->providers->forGateway($payment->gateway);
        if (! $provider->supportsCollect()) {
            throw new BusinessException('Pay with your UPI ID or UPI app in the secure Razorpay window.', 422, [], ['fallback' => $this->fallback()]);
        }

        $check = $provider->validateVpa($vpa);
        if ($check && ! $check['valid']) {
            throw new BusinessException('This UPI ID could not be found. Please check it and try again.', 422, ['vpa' => ['UPI ID not found.']]);
        }

        $started = $this->call(fn () => $provider->collect($order, $payment, $vpa, $client));
        $payment->update(['meta' => $this->freshMeta($payment->meta ?? []) + array_filter([
            'upi_flow' => 'collect',
            'upi_ref' => $started['reference'],
            'vpa' => self::maskVpa($vpa),
            'payer_name' => $check['name'] ?? null,
            'demo_decline' => $provider->isDemo() && str_starts_with($vpa, 'fail') ? true : null,
            'upi_started_at' => now()->toIso8601String(),
            'upi_expires_at' => now()->addMinutes($this->ttl('collect'))->toIso8601String(),
        ], fn ($v) => $v !== null)]);

        return $this->session($order, $payment->fresh(), 'pending');
    }

    /** Polled by the payment page: asks the provider and moves the order on once paid. */
    public function status(Order $order, ?User $by): array
    {
        $order->refresh();
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return $this->session($order, $this->latest($order), 'paid', 'Payment received — your order is confirmed.');
        }
        $payment = $this->latest($order);
        if (! $payment || ! isset($payment->meta['upi_flow'])) {
            return $this->session($order, $payment, 'idle');
        }
        if ($payment->status === PaymentStatus::Failed) {
            return $this->session($order, $payment, 'failed', $payment->failure_reason ?: 'The UPI payment was not completed.');
        }
        if ($payment->status !== PaymentStatus::Pending) {
            return $this->session($order, $payment, $payment->status === PaymentStatus::Success ? 'paid' : 'idle');
        }

        $provider = $this->providers->forGateway($payment->gateway);
        if ($provider->isDemo() || Cache::add('upi-check:'.$payment->id, 1, self::CHECK_INTERVAL)) {
            $result = $provider->check($payment);
            if ($result->status === 'paid') {
                $paid = $this->orders->completeOnlinePayment($payment, $result->transactionId, $result->meta, $by, 'upi_status');
                $order->refresh();
                if ($order->payment_status === OrderPaymentStatus::Paid) {
                    return $this->session($order, $paid, 'paid', 'Payment received — your order is confirmed.');
                }
            } elseif ($result->status === 'failed') {
                $failed = $this->orders->failOnlinePayment($payment, $result->reason ?? 'The UPI payment was not completed.', $result->meta, $by);

                return $this->session($order->refresh(), $failed, 'failed', $failed->failure_reason);
            }
        }

        $expired = Carbon::parse($payment->meta['upi_expires_at'] ?? now())->isPast();

        return $this->session($order, $payment, $expired ? 'expired' : 'pending', $expired
            ? (($payment->meta['upi_flow'] ?? '') === 'collect' ? 'The payment request expired.' : 'This QR code expired.')
            : null);
    }

    /** Demo provider only: pretend the customer approved / declined in their UPI app. */
    public function simulate(Order $order, string $outcome, ?User $by): array
    {
        $payment = $this->latest($order);
        if (! $payment || $payment->status !== PaymentStatus::Pending || ! $this->providers->forGateway($payment->gateway)->isDemo() || ! isset($payment->meta['upi_flow'])) {
            throw new BusinessException('There is no demo UPI payment to simulate.', 404);
        }
        $payment->update(['meta' => array_merge($payment->meta, ['demo_outcome' => $outcome === 'failure' ? 'failure' : 'success'])]);

        return $this->status($order, $by);
    }

    /** Public settings the page needs before starting (amount, payee, demo flag, timings). */
    public function info(Order $order): array
    {
        $provider = $this->providers->current();

        return [
            'available' => (bool) $provider,
            'demo' => (bool) $provider?->isDemo(),
            'collect' => (bool) $provider?->supportsCollect(),
            'amount' => (float) $order->grand_total,
            'payee' => (string) $this->settings->get('store_name'),
            'qr_ttl_seconds' => $this->ttl('qr') * 60,
            'collect_ttl_seconds' => $this->ttl('collect') * 60,
        ];
    }

    public static function maskVpa(string $vpa): string
    {
        [$user, $bank] = array_pad(explode('@', $vpa, 2), 2, '');

        return mb_substr($user, 0, 2).str_repeat('*', max(1, mb_strlen($user) - 2)).'@'.$bank;
    }

    /** The pending UPI payment for this order, starting a new one when needed. */
    private function attempt(Order $order, ?User $by): Payment
    {
        $order->refresh();
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            throw new BusinessException('This order is already paid.', 409);
        }
        if ($order->status !== OrderStatus::Pending) {
            throw new BusinessException('This order can no longer be paid ('.$order->status->label().').', 422);
        }

        $payment = $order->payments()->where('method', PaymentMethod::Upi->value)->where('status', PaymentStatus::Pending)
            ->latest('id')->first();
        if ($payment && $payment->gateway_order_id) {
            return $payment;
        }

        $payment = $this->orders->pay($order, PaymentMethod::Upi, [], $by);
        if ($payment->status !== PaymentStatus::Pending) {
            throw new BusinessException($payment->failure_reason ?: 'UPI payments are unavailable right now.', 503, [], ['fallback' => $this->fallback()]);
        }

        return $payment;
    }

    private function latest(Order $order): ?Payment
    {
        return $order->payments()->where('method', PaymentMethod::Upi->value)->latest('id')->first();
    }

    /** Run a provider call, turning provider errors into a 503 the page can show. */
    private function call(callable $fn): array
    {
        try {
            return $fn();
        } catch (UpiException $e) {
            throw new BusinessException($e->getMessage(), 503, [], ['fallback' => $this->fallback()]);
        }
    }

    /** Another way to pay when in-page UPI fails (Razorpay's own window also takes UPI). */
    private function fallback(): ?string
    {
        return \App\Services\Payments\PaymentManager::isOffered(PaymentMethod::Razorpay) ? 'razorpay' : null;
    }

    /** Drop the previous QR/collect details when switching or restarting. */
    private function freshMeta(array $meta): array
    {
        return array_diff_key($meta, array_flip(['upi_flow', 'upi_ref', 'upi_link', 'upi_open_app', 'upi_started_at', 'upi_expires_at', 'vpa', 'payer_name', 'demo_outcome', 'demo_decline']));
    }

    private function ttl(string $kind): int
    {
        return $kind === 'qr'
            ? max(2, min(30, (int) config('payments.upi.qr_ttl_minutes', 10)))
            : max(1, min(15, (int) config('payments.upi.collect_ttl_minutes', 5)));
    }

    private function session(Order $order, ?Payment $payment, string $status, ?string $message = null): array
    {
        $meta = $payment?->meta ?? [];
        $expires = isset($meta['upi_expires_at']) ? Carbon::parse($meta['upi_expires_at']) : null;

        return [
            'status' => $status,
            'message' => $message,
            'flow' => $meta['upi_flow'] ?? null,
            'vpa' => $meta['vpa'] ?? null,
            'payer_name' => $meta['payer_name'] ?? null,
            'expires_at' => $expires?->toIso8601String(),
            'expires_in' => $expires ? max(0, (int) round(now()->diffInSeconds($expires, false))) : null,
            'amount' => (float) $order->grand_total,
            'payee' => (string) $this->settings->get('store_name'),
            'demo' => $payment ? $this->providers->forGateway($payment->gateway)->isDemo() : (bool) $this->providers->current()?->isDemo(),
            // false: no UPI ID box on our page — the "UPI ID / app" option opens Razorpay's window.
            'collect' => $payment ? $this->providers->forGateway($payment->gateway)->supportsCollect() : (bool) $this->providers->current()?->supportsCollect(),
            'transaction_id' => $status === 'paid' ? $payment?->transaction_id : null,
        ];
    }
}

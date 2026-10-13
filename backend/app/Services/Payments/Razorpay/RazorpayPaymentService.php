<?php

namespace App\Services\Payments\Razorpay;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\Upi\RazorpayUpiProvider;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Everything around a Razorpay payment after the gateway order exists:
 * the options for checkout.js, verifying the browser callback, failures and webhooks.
 * The browser is never trusted: success needs a valid HMAC signature and (when the API
 * is reachable) a matching amount/order on Razorpay's side.
 */
class RazorpayPaymentService
{
    public function __construct(
        private RazorpayClient $client,
        private OrderService $orders,
        private SettingsService $settings,
    ) {}

    /** Public options for `new Razorpay(options)` — contains no secrets. */
    public function checkoutOptions(Order $order, Payment $payment): array
    {
        $order->loadMissing('user');
        $address = $order->shipping_address ?? [];

        return [
            'key' => $this->client->keyId(),
            'order_id' => $payment->gateway_order_id,
            'amount' => RazorpayGateway::toPaise((float) $payment->amount),
            'currency' => $payment->currency,
            'name' => (string) $this->settings->get('store_name'),
            'description' => 'Order '.$order->order_number,
            'prefill' => [
                'name' => $order->customer_name ?? $order->user?->name ?? ($address['name'] ?? ''),
                'email' => $order->customer_email ?? $order->user?->email ?? '',
                'contact' => self::e164((string) ($order->customer_phone ?: $order->user?->phone ?: ($address['phone'] ?? ''))),
            ],
            // Store logo in the Razorpay window (square icon, served by the storefront).
            'image' => rtrim((string) config('app.frontend_url'), '/').'/brand/icon-192.png',
            'notes' => ['order_number' => $order->order_number],
            'theme' => ['color' => config('payments.razorpay.theme_color')],
            'test_mode' => $this->client->isTestMode(),
        ];
    }

    /** Browser handler returned razorpay_order_id / razorpay_payment_id / razorpay_signature. */
    public function confirmFromCheckout(Order $order, string $rzpOrderId, string $rzpPaymentId, string $signature, ?User $by): Payment
    {
        $payment = $this->findPayment($order, $rzpOrderId);
        if (! $this->client->verifyPaymentSignature($rzpOrderId, $rzpPaymentId, $signature)) {
            Log::warning('Razorpay signature mismatch', ['order' => $order->order_number, 'rzp_order' => $rzpOrderId]);
            throw new BusinessException('We could not verify this payment. If money was deducted it will be confirmed automatically or refunded.', 422, ['razorpay_signature' => ['Invalid payment signature.']]);
        }
        if ($payment->status === PaymentStatus::Success) {
            return $payment;
        }

        $meta = [];
        try {
            $remote = $this->client->fetchPayment($rzpPaymentId);
            $this->assertMatches($remote, $payment);
            if (($remote['status'] ?? null) === 'authorized') {
                $remote = $this->client->capture($rzpPaymentId, RazorpayGateway::toPaise((float) $payment->amount), $payment->currency);
            }
            if (($remote['status'] ?? null) === 'failed') {
                return $this->orders->failOnlinePayment($payment, $remote['error_description'] ?? 'Payment failed at Razorpay.', [], $by);
            }
            $meta = $this->describe($remote);
        } catch (RazorpayException $e) {
            // Signature already proves Razorpay issued this payment id for our order; the webhook
            // will fill in method details (and capture) once the API is reachable again.
            $meta = ['verification' => 'signature_only'];
        }

        return $this->orders->completeOnlinePayment($payment, $rzpPaymentId, $meta, $by, 'checkout');
    }

    /** Checkout's `payment.failed` event (the modal stays open so the customer can try again there). */
    public function reportFailure(Order $order, string $rzpOrderId, ?string $reason, ?User $by): Payment
    {
        $payment = $this->findPayment($order, $rzpOrderId);

        return $this->orders->failOnlinePayment($payment, $reason ?: 'Payment was not completed.', [], $by);
    }

    /**
     * Ask Razorpay whether a pending Razorpay payment was actually paid (shopper closed the
     * window before we heard back, webhook not delivered yet…) and confirm the order if so.
     * Spaced out per payment so page loads can call it freely.
     */
    public function reconcile(Payment $payment, bool $force = false): Payment
    {
        if ($payment->gateway !== 'razorpay' || ! $payment->gateway_order_id
            || ! in_array($payment->status, [PaymentStatus::Initiated, PaymentStatus::Pending], true)
            || (! $force && ! Cache::add('rzp-reconcile:'.$payment->id, 1, 15))) {
            return $payment;
        }
        if ($payment->method === PaymentMethod::Upi->value) {
            // In-page UPI QR codes aren't Razorpay orders: ask about the QR codes instead.
            $check = app(RazorpayUpiProvider::class)->check($payment);

            return $check->status === 'paid'
                ? $this->orders->completeOnlinePayment($payment, $check->transactionId, $check->meta, null, 'reconcile')
                : $payment;
        }
        try {
            $attempts = $this->client->fetchOrderPayments($payment->gateway_order_id);
        } catch (RazorpayException) {
            return $payment;
        }
        $expected = RazorpayGateway::toPaise((float) $payment->amount);
        foreach ($attempts as $p) {
            if (! in_array($p['status'] ?? null, ['captured', 'authorized'], true) || (int) ($p['amount'] ?? -1) !== $expected) {
                continue;
            }
            if ($p['status'] === 'authorized') {
                try {
                    $this->client->capture($p['id'], $expected, $payment->currency);
                } catch (RazorpayException) {
                    return $payment;
                }
            }

            return $this->orders->completeOnlinePayment($payment, $p['id'], $this->describe($p), null, 'reconcile');
        }

        return $payment;
    }

    /** Reconcile every pending Razorpay attempt of an order (used when an order page is opened). */
    public function reconcileOrder(Order $order): void
    {
        if ($order->payment_status === \App\Enums\OrderPaymentStatus::Paid || $order->status !== \App\Enums\OrderStatus::Pending) {
            return;
        }
        $order->payments()->where('gateway', 'razorpay')->whereIn('status', [PaymentStatus::Initiated, PaymentStatus::Pending])
            ->latest('id')->take(3)->get()->each(fn (Payment $p) => $this->reconcile($p));
    }

    /** POST /webhooks/razorpay — signature already verified by the controller. */
    public function handleWebhook(array $event): string
    {
        $type = $event['event'] ?? '';
        $entity = $event['payload']['payment']['entity'] ?? null;
        if ($type === 'qr_code.credited') {
            return $this->handleQrCredit($entity, $event['payload']['qr_code']['entity'] ?? null);
        }
        if (! $entity || ! in_array($type, ['payment.captured', 'order.paid', 'payment.authorized', 'payment.failed'], true)) {
            return 'ignored';
        }
        $payment = Payment::where('gateway', 'razorpay')->where('gateway_order_id', $entity['order_id'] ?? '')->first();
        if (! $payment) {
            return 'unknown_order';
        }

        if ($type === 'payment.failed') {
            $this->orders->failOnlinePayment($payment, $entity['error_description'] ?? 'Payment failed at Razorpay.');

            return 'failed_recorded';
        }
        if ((int) ($entity['amount'] ?? 0) !== RazorpayGateway::toPaise((float) $payment->amount)) {
            Log::error('Razorpay webhook amount mismatch', ['payment' => $payment->id, 'amount' => $entity['amount'] ?? null]);

            return 'amount_mismatch';
        }
        if ($type === 'payment.authorized') {
            try {
                $this->client->capture($entity['id'], (int) $entity['amount'], $payment->currency);
            } catch (RazorpayException) {
                return 'capture_failed'; // Razorpay retries; payment.captured will follow once captured.
            }
        }
        $this->orders->completeOnlinePayment($payment, $entity['id'], $this->describe($entity), null, 'webhook');

        return 'paid';
    }

    /** A UPI QR code shown on our payment page was paid (qr_code.credited). */
    private function handleQrCredit(?array $entity, ?array $qr): string
    {
        $qrId = (string) ($qr['id'] ?? '');
        if (! $entity || ! preg_match('/^qr_[A-Za-z0-9]+$/', $qrId)) {
            return 'ignored';
        }
        $payment = Payment::where('gateway', 'razorpay')->where('method', PaymentMethod::Upi->value)
            ->whereKey((int) ($qr['notes']['payment_id'] ?? 0))->first();
        // Notes can't be trusted to be present on every event; fall back to the codes we recorded.
        $payment ??= Payment::where('gateway', 'razorpay')->where('method', PaymentMethod::Upi->value)
            ->where('meta', 'like', '%"'.$qrId.'"%')->latest('id')->first();
        if (! $payment || ! in_array($qrId, RazorpayUpiProvider::references($payment), true)) {
            return 'unknown_order';
        }
        if (! in_array($entity['status'] ?? null, ['captured', 'authorized'], true)) {
            return 'ignored';
        }
        if ((int) ($entity['amount'] ?? 0) !== RazorpayGateway::toPaise((float) $payment->amount)) {
            Log::error('Razorpay QR webhook amount mismatch', ['payment' => $payment->id, 'amount' => $entity['amount'] ?? null]);

            return 'amount_mismatch';
        }
        if (($entity['status'] ?? null) === 'authorized') {
            try {
                $this->client->capture($entity['id'], (int) $entity['amount'], $payment->currency);
            } catch (RazorpayException) {
                return 'capture_failed';
            }
        }
        $this->orders->completeOnlinePayment($payment, $entity['id'], $this->describe($entity) + ['qr_code_id' => $qrId], null, 'webhook');

        return 'paid';
    }

    private function findPayment(Order $order, string $rzpOrderId): Payment
    {
        $payment = $order->payments()->where('method', PaymentMethod::Razorpay->value)->where('gateway_order_id', $rzpOrderId)->latest('id')->first();
        if (! $payment) {
            throw new BusinessException('This payment does not belong to the order.', 422, ['razorpay_order_id' => ['Unknown Razorpay order.']]);
        }

        return $payment;
    }

    private function assertMatches(array $remote, Payment $payment): void
    {
        if (($remote['order_id'] ?? null) !== $payment->gateway_order_id || (int) ($remote['amount'] ?? -1) !== RazorpayGateway::toPaise((float) $payment->amount)) {
            Log::error('Razorpay payment does not match order', ['payment' => $payment->id, 'remote_order' => $remote['order_id'] ?? null, 'amount' => $remote['amount'] ?? null]);
            throw new BusinessException('Payment details do not match this order.', 422);
        }
    }

    /** Non-sensitive details for the admin/customer UI (never full card numbers). */
    private function describe(array $p): array
    {
        return array_filter([
            'rzp_method' => $p['method'] ?? null,
            'brand' => $p['card']['network'] ?? null,
            'last4' => $p['card']['last4'] ?? null,
            'vpa' => $p['vpa'] ?? null,
            'bank' => $p['bank'] ?? null,
            'wallet' => $p['wallet'] ?? null,
        ]);
    }

    /** Indian mobile numbers as +91XXXXXXXXXX so Razorpay pre-fills them. */
    private static function e164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+'.$digits;
        }

        return $digits !== '' && str_starts_with(trim($phone), '+') ? '+'.$digits : $digits;
    }
}

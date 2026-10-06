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
use App\Services\SettingsService;
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
                'name' => $order->user?->name ?? ($address['name'] ?? ''),
                'email' => $order->user?->email ?? '',
                'contact' => preg_replace('/[^0-9+]/', '', (string) ($order->user?->phone ?: ($address['phone'] ?? ''))),
            ],
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

    /** POST /webhooks/razorpay — signature already verified by the controller. */
    public function handleWebhook(array $event): string
    {
        $type = $event['event'] ?? '';
        $entity = $event['payload']['payment']['entity'] ?? null;
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
}

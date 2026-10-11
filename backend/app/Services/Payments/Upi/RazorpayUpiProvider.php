<?php

namespace App\Services\Payments\Upi;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\Razorpay\RazorpayClient;
use App\Services\Payments\Razorpay\RazorpayException;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\PaymentResult;
use Illuminate\Support\Facades\Log;

/**
 * UPI through Razorpay's S2S API. Each attempt is a Razorpay order; QR codes (intent) and
 * collect requests are payments against it, so the existing Razorpay webhook
 * (payment.captured / payment.failed, matched by order_id) confirms them too.
 */
class RazorpayUpiProvider implements UpiProvider
{
    public function __construct(private RazorpayClient $client, private RazorpayGateway $gateway) {}

    public function name(): string
    {
        return 'razorpay';
    }

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    public function isDemo(): bool
    {
        return false;
    }

    public function openOrder(Order $order, Payment $payment): string
    {
        try {
            $rzp = $this->client->createOrder(
                RazorpayGateway::toPaise((float) $payment->amount),
                $order->order_number.'-'.$payment->id,
                ['order_number' => $order->order_number, 'order_id' => $order->id, 'payment_id' => $payment->id, 'flow' => 'upi'],
                $payment->currency,
            );
        } catch (RazorpayException $e) {
            throw new UpiException('Could not start the UPI payment. Please try again in a moment.', 0, $e);
        }

        return (string) ($rzp['id'] ?? '');
    }

    public function intent(Order $order, Payment $payment, array $client): array
    {
        $res = $this->create($order, $payment, ['flow' => 'intent'], $client);
        $link = $res['link'] ?? collect($res['next'] ?? [])->firstWhere('action', 'intent')['url'] ?? null;
        if (! is_string($link) || ! str_starts_with($link, 'upi://')) {
            Log::warning('Razorpay UPI intent returned no link', ['order' => $order->order_number, 'keys' => array_keys($res)]);
            throw new UpiException('Could not create a UPI QR code right now.');
        }

        return ['link' => $link, 'reference' => $res['razorpay_payment_id'] ?? null];
    }

    public function collect(Order $order, Payment $payment, string $vpa, array $client): array
    {
        $minutes = max(1, min(15, (int) config('payments.upi.collect_ttl_minutes', 5)));
        $res = $this->create($order, $payment, ['flow' => 'collect', 'vpa' => $vpa, 'expiry_time' => $minutes], $client);

        return ['reference' => $res['razorpay_payment_id'] ?? null];
    }

    public function validateVpa(string $vpa): ?array
    {
        try {
            $res = $this->client->validateVpa($vpa);
        } catch (RazorpayException $e) {
            // 400 = Razorpay says the VPA is invalid; anything else = can't verify, let the request decide.
            return $e->getCode() === 400 ? ['valid' => false, 'name' => null] : null;
        }

        return ['valid' => (bool) ($res['success'] ?? false), 'name' => $res['customer_name'] ?? null];
    }

    public function check(Payment $payment): UpiCheck
    {
        if (! $payment->gateway_order_id) {
            return UpiCheck::pending();
        }
        try {
            $attempts = $this->client->fetchOrderPayments($payment->gateway_order_id);
        } catch (RazorpayException) {
            return UpiCheck::pending(); // try again on the next poll; the webhook also confirms
        }

        $expected = RazorpayGateway::toPaise((float) $payment->amount);
        foreach ($attempts as $p) {
            $status = $p['status'] ?? null;
            if (! in_array($status, ['captured', 'authorized'], true)) {
                continue;
            }
            if ((int) ($p['amount'] ?? -1) !== $expected) {
                Log::error('Razorpay UPI amount mismatch', ['payment' => $payment->id, 'amount' => $p['amount'] ?? null]);

                continue;
            }
            if ($status === 'authorized') {
                try {
                    $this->client->capture($p['id'], $expected, $payment->currency);
                } catch (RazorpayException) {
                    return UpiCheck::pending(); // the webhook captures/confirms it later
                }
            }

            return UpiCheck::paid($p['id'], array_filter(['rzp_method' => $p['method'] ?? 'upi', 'vpa' => $p['vpa'] ?? null]));
        }

        // Only the attempt the customer is looking at decides "failed" — an abandoned QR doesn't.
        $current = collect($attempts)->firstWhere('id', $payment->meta['upi_ref'] ?? null);
        if (($current['status'] ?? null) === 'failed') {
            return UpiCheck::failed($current['error_description'] ?? 'The UPI payment was not completed.');
        }

        return UpiCheck::pending();
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        return $this->gateway->refund($payment, $amount);
    }

    private function create(Order $order, Payment $payment, array $upi, array $client): array
    {
        if (! $payment->gateway_order_id) {
            throw new UpiException('This payment has expired. Please start again.');
        }
        $address = $order->shipping_address ?? [];
        $contact = preg_replace('/[^0-9+]/', '', (string) ($order->customer_phone ?: ($address['phone'] ?? '')));
        try {
            return $this->client->createUpiPayment(array_filter([
                'amount' => RazorpayGateway::toPaise((float) $payment->amount),
                'currency' => $payment->currency,
                'order_id' => $payment->gateway_order_id,
                'email' => $order->customer_email ?: $order->user?->email,
                'contact' => $contact ?: null,
                'method' => 'upi',
                'description' => 'Order '.$order->order_number,
                'notes' => ['order_number' => $order->order_number],
                'ip' => $client['ip'] ?? null,
                'user_agent' => $client['user_agent'] ?? null,
                'referer' => $client['referer'] ?? null,
                'upi' => $upi,
            ], fn ($v) => $v !== null && $v !== ''));
        } catch (RazorpayException $e) {
            $message = $upi['flow'] === 'collect' && $e->getCode() === 400
                ? 'Could not send a payment request to this UPI ID. Check the ID, or scan the QR code instead.'
                : 'UPI payments are temporarily unavailable.';

            throw new UpiException($message, $e->getCode(), $e);
        }
    }
}

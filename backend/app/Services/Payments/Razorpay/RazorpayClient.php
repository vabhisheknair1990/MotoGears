<?php

namespace App\Services\Payments\Razorpay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Razorpay REST client (Orders, Payments, Refunds) plus signature helpers.
 * Amounts are in paise. Credentials come from config/payments.php — never from the client.
 */
class RazorpayClient
{
    public function isConfigured(): bool
    {
        return filled($this->keyId()) && filled($this->keySecret());
    }

    public function keyId(): ?string
    {
        return config('payments.razorpay.key_id');
    }

    public function isTestMode(): bool
    {
        return str_starts_with((string) $this->keyId(), 'rzp_test_');
    }

    /** POST /orders */
    public function createOrder(int $amountPaise, string $receipt, array $notes = [], string $currency = 'INR'): array
    {
        return $this->send('post', 'orders', [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => substr($receipt, 0, 40),
            'notes' => array_slice(array_map(fn ($v) => substr((string) $v, 0, 256), $notes), 0, 15, true),
        ]);
    }

    /** GET /payments/{id} */
    public function fetchPayment(string $paymentId): array
    {
        return $this->send('get', 'payments/'.rawurlencode($paymentId));
    }

    /** POST /payments/{id}/capture — only needed when auto-capture is off in the Dashboard. */
    public function capture(string $paymentId, int $amountPaise, string $currency = 'INR'): array
    {
        return $this->send('post', 'payments/'.rawurlencode($paymentId).'/capture', ['amount' => $amountPaise, 'currency' => $currency]);
    }

    /** POST /payments/{id}/refund */
    public function refund(string $paymentId, int $amountPaise, array $notes = []): array
    {
        return $this->send('post', 'payments/'.rawurlencode($paymentId).'/refund', array_filter(['amount' => $amountPaise, 'notes' => $notes ?: null]));
    }

    /** Checkout success callback: HMAC-SHA256(order_id|payment_id, key_secret). */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (! $this->keySecret() || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->keySecret());

        return hash_equals($expected, $signature);
    }

    /** Webhooks: HMAC-SHA256(raw request body, webhook_secret) sent in X-Razorpay-Signature. */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = config('payments.razorpay.webhook_secret');
        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    private function keySecret(): ?string
    {
        return config('payments.razorpay.key_secret');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('payments.razorpay.base_url'), '/'))
            ->withBasicAuth((string) $this->keyId(), (string) $this->keySecret())
            ->acceptJson()
            ->asJson()
            ->timeout(config('payments.razorpay.timeout', 15))
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function send(string $method, string $path, array $data = []): array
    {
        if (! $this->isConfigured()) {
            throw new RazorpayException('Razorpay keys are not configured.');
        }
        try {
            /** @var Response $response */
            $response = $method === 'get' ? $this->http()->get($path, $data) : $this->http()->post($path, $data);
        } catch (ConnectionException $e) {
            Log::warning('Razorpay connection failed', ['path' => $path, 'error' => $e->getMessage()]);
            throw new RazorpayException('Could not reach Razorpay.', 0, $e);
        }
        if ($response->failed()) {
            $error = $response->json('error.description') ?? $response->reason();
            Log::warning('Razorpay API error', ['path' => $path, 'status' => $response->status(), 'error' => $error]);
            throw new RazorpayException('Razorpay: '.$error, $response->status());
        }

        return $response->json() ?? [];
    }
}

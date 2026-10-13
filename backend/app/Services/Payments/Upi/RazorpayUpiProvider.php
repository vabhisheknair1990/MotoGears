<?php

namespace App\Services\Payments\Upi;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\Razorpay\RazorpayClient;
use App\Services\Payments\Razorpay\RazorpayException;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * UPI through Razorpay's UPI QR Codes API. Each QR code is single-use, for the exact order
 * total, and closes itself when it expires. We read the upi://pay link out of Razorpay's QR
 * image and draw it on our own page; the payment is confirmed by polling the QR code's
 * payments and by the qr_code.credited webhook.
 *
 * Typing a UPI ID (collect) isn't offered here: NPCI retired UPI Collect for web checkouts,
 * so customers who prefer their UPI ID or an app button pay in Razorpay's window instead.
 */
class RazorpayUpiProvider implements UpiProvider
{
    /** Set when Razorpay refuses QR codes (feature not activated): stop offering them for a while. */
    private const OFF_FOR_SECONDS = 3600;

    public function __construct(private RazorpayClient $client, private RazorpayGateway $gateway, private UpiQrReader $reader) {}

    public function name(): string
    {
        return 'razorpay';
    }

    /** Only when Razorpay has UPI switched on and hasn't refused QR codes recently. */
    public function isAvailable(): bool
    {
        return $this->client->isConfigured() && $this->client->upiEnabled() && ! Cache::has($this->offKey());
    }

    public function isDemo(): bool
    {
        return false;
    }

    public function supportsCollect(): bool
    {
        return false;
    }

    /** The QR code itself is created when the customer opens the payment page. */
    public function openOrder(Order $order, Payment $payment): string
    {
        return 'upi_'.$payment->id;
    }

    public function intent(Order $order, Payment $payment, array $client, array $previous = []): array
    {
        $this->closeAll($previous);

        $amount = RazorpayGateway::toPaise((float) $payment->amount);
        $minutes = max(2, min(120, (int) config('payments.upi.qr_ttl_minutes', 10)));
        try {
            $qr = $this->client->createQrCode($amount, now()->addMinutes($minutes)->getTimestamp(), 'Order '.$order->order_number, [
                'order_number' => $order->order_number, 'order_id' => $order->id, 'payment_id' => $payment->id,
            ]);
        } catch (RazorpayException $e) {
            if (self::isFeatureOff($e)) {
                Cache::put($this->offKey(), true, self::OFF_FOR_SECONDS);
                Log::warning('Razorpay UPI QR codes are not enabled for this account', ['error' => $e->getMessage()]);
            }
            throw new UpiException('UPI QR codes are temporarily unavailable.', $e->getCode(), $e);
        }

        $id = (string) ($qr['id'] ?? '');
        $link = null;
        try {
            $link = $this->reader->read($this->client->downloadQrImage((string) ($qr['image_url'] ?? '')));
        } catch (RazorpayException $e) {
            Log::warning('Razorpay QR image download failed', ['qr' => $id, 'error' => $e->getMessage()]);
        }
        // The link must be for exactly this payment's amount, or nobody should scan it.
        if (! $link || abs((UpiQrReader::amount($link) ?? -1) - $amount / 100) > 0.001) {
            $this->closeAll([$id]);
            throw new UpiException('Could not create a UPI QR code right now.');
        }

        return [
            'link' => $link,
            'reference' => $id,
            'expires_at' => isset($qr['close_by']) ? (int) $qr['close_by'] : null,
            // A QR-code link (mode 22) isn't meant to be opened as an app intent; phones use
            // the Razorpay window's UPI app buttons instead.
            'open_app' => false,
        ];
    }

    public function collect(Order $order, Payment $payment, string $vpa, array $client): array
    {
        throw new UpiException('Paying with a UPI ID happens in the Razorpay window.');
    }

    public function validateVpa(string $vpa): ?array
    {
        return null;
    }

    public function check(Payment $payment): UpiCheck
    {
        $expected = RazorpayGateway::toPaise((float) $payment->amount);
        foreach (self::references($payment) as $qrId) {
            try {
                $attempts = $this->client->fetchQrPayments($qrId);
            } catch (RazorpayException) {
                continue; // try again on the next poll; the webhook also confirms
            }
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

                return UpiCheck::paid($p['id'], array_filter(['rzp_method' => $p['method'] ?? 'upi', 'vpa' => $p['vpa'] ?? null, 'qr_code_id' => $qrId]));
            }
        }

        // A declined attempt leaves the QR code open for another try, so it isn't "failed".
        return UpiCheck::pending();
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        return $this->gateway->refund($payment, $amount);
    }

    /** Every QR code shown for this payment (an older one may still be paid just before it closes). */
    public static function references(Payment $payment): array
    {
        $meta = $payment->meta ?? [];

        return array_values(array_unique(array_filter([...(array) ($meta['upi_refs'] ?? []), $meta['upi_ref'] ?? null],
            fn ($r) => is_string($r) && str_starts_with($r, 'qr_'))));
    }

    /** Close every QR code of a payment that is being replaced (another method, a retry…). */
    public function closeCodes(Payment $payment): void
    {
        $this->closeAll(self::references($payment));
    }

    /** Best effort: an already-closed or paid code just answers with an error. */
    private function closeAll(array $ids): void
    {
        foreach ($ids as $id) {
            if (is_string($id) && str_starts_with($id, 'qr_')) {
                try {
                    $this->client->closeQrCode($id);
                } catch (RazorpayException) {
                }
            }
        }
    }

    private function offKey(): string
    {
        return 'razorpay.upi_qr_off.'.md5((string) $this->client->keyId());
    }

    /** Razorpay's answer when UPI QR codes aren't activated for the account. */
    private static function isFeatureOff(RazorpayException $e): bool
    {
        $m = strtolower($e->getMessage());

        return in_array($e->getCode(), [400, 401, 403, 404], true)
            && (str_contains($m, 'not found on the server') || str_contains($m, 'not enabled') || str_contains($m, 'not activated')
                || str_contains($m, 'feature') || str_contains($m, 'not allowed') || str_contains($m, 'access'));
    }
}

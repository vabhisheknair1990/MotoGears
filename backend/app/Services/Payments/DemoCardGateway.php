<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Simulated card processor. Card data is validated and immediately discarded — only the
 * brand and last four digits are persisted. Any valid card number succeeds except numbers
 * ending in 0002, which simulate an issuer decline.
 */
class DemoCardGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'demo';
    }

    public function rules(): array
    {
        return [
            'payment_details.card_number' => ['required', 'string', 'regex:/^[0-9 ]{13,23}$/'],
            'payment_details.card_name' => ['required', 'string', 'max:100'],
            'payment_details.expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'],
            'payment_details.cvv' => ['required', 'string', 'regex:/^[0-9]{3,4}$/'],
        ];
    }

    public function charge(Order $order, Payment $payment, array $details): PaymentResult
    {
        $number = preg_replace('/\D/', '', $details['card_number'] ?? '');
        $meta = ['brand' => $this->brand($number), 'last4' => substr($number, -4)];

        if (! $this->luhn($number)) {
            return PaymentResult::failed('Invalid card number.', null, $meta);
        }
        if (! $this->notExpired($details['expiry'] ?? '')) {
            return PaymentResult::failed('Card has expired.', null, $meta);
        }
        if (str_ends_with($number, '0002')) {
            return PaymentResult::failed('Card declined by issuing bank (demo decline).', 'DEMO-'.Str::upper(Str::random(12)), $meta);
        }

        return PaymentResult::success('DEMO-'.Str::upper(Str::random(12)), $meta);
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        return PaymentResult::success('DEMO-RF-'.Str::upper(Str::random(10)));
    }

    private function luhn(string $n): bool
    {
        if (strlen($n) < 13 || strlen($n) > 19) {
            return false;
        }
        $sum = 0;
        $alt = false;
        for ($i = strlen($n) - 1; $i >= 0; $i--) {
            $d = (int) $n[$i];
            if ($alt) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    private function notExpired(string $expiry): bool
    {
        if (! preg_match('/^(\d{2})\/?(\d{2})$/', $expiry, $m)) {
            return false;
        }
        $end = \Carbon\Carbon::createFromDate(2000 + (int) $m[2], (int) $m[1], 1)->endOfMonth();

        return $end->isFuture();
    }

    private function brand(string $n): string
    {
        return match (true) {
            str_starts_with($n, '4') => 'Visa',
            (bool) preg_match('/^5[1-5]/', $n) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $n) => 'Amex',
            (bool) preg_match('/^(60|65|81|82|508)/', $n) => 'RuPay',
            default => 'Card',
        };
    }
}

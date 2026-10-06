<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/** Simulated UPI collect flow. The VPA "failure@upi" (or any VPA starting with "fail") is declined. */
class DemoUpiGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'demo';
    }

    public function rules(): array
    {
        return ['payment_details.upi_id' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/']];
    }

    public function charge(Order $order, Payment $payment, array $details): PaymentResult
    {
        $vpa = strtolower($details['upi_id'] ?? '');
        $meta = ['vpa' => $this->mask($vpa)];
        if (str_starts_with($vpa, 'fail')) {
            return PaymentResult::failed('UPI request was declined by the payer (demo failure).', 'UPI'.Str::upper(Str::random(12)), $meta);
        }

        return PaymentResult::success('UPI'.Str::upper(Str::random(12)), $meta);
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        return PaymentResult::success('UPI-RF-'.Str::upper(Str::random(10)));
    }

    private function mask(string $vpa): string
    {
        [$user, $bank] = array_pad(explode('@', $vpa, 2), 2, '');

        return substr($user, 0, 2).str_repeat('*', max(0, strlen($user) - 2)).'@'.$bank;
    }
}

<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'method_label' => PaymentMethod::tryFrom($this->method)?->label() ?? $this->method,
            'gateway' => $this->gateway,
            'transaction_id' => $this->transaction_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'details' => collect($this->meta ?? [])->only(['brand', 'last4', 'vpa', 'bank', 'wallet', 'rzp_method', 'collect_on_delivery', 'refund_id']),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;

final class PaymentResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $failureReason = null,
        public readonly array $meta = [],
    ) {}

    public static function success(string $txn, array $meta = []): self
    {
        return new self(PaymentStatus::Success, $txn, null, $meta);
    }

    public static function pending(?string $txn = null, array $meta = []): self
    {
        return new self(PaymentStatus::Pending, $txn, null, $meta);
    }

    public static function failed(string $reason, ?string $txn = null, array $meta = []): self
    {
        return new self(PaymentStatus::Failed, $txn, $reason, $meta);
    }
}

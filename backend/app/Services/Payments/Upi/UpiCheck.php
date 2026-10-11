<?php

namespace App\Services\Payments\Upi;

/** Result of asking the provider about a UPI attempt. */
final class UpiCheck
{
    private function __construct(
        public readonly string $status, // pending | paid | failed
        public readonly ?string $transactionId = null,
        public readonly ?string $reason = null,
        public readonly array $meta = [],
    ) {}

    public static function pending(): self
    {
        return new self('pending');
    }

    public static function paid(string $transactionId, array $meta = []): self
    {
        return new self('paid', $transactionId, null, $meta);
    }

    public static function failed(string $reason, array $meta = []): self
    {
        return new self('failed', null, $reason, $meta);
    }
}

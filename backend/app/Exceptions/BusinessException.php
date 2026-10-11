<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A domain rule was violated (out of stock, invalid coupon, illegal status change...).
 * Rendered as a JSON error response with the given HTTP status.
 */
class BusinessException extends RuntimeException
{
    /** @param  array  $extra  additional top-level fields for the client (e.g. a fallback hint) */
    public function __construct(string $message, public readonly int $status = 422, public readonly array $errors = [], public readonly array $extra = [])
    {
        parent::__construct($message);
    }
}

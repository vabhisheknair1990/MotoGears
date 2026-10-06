<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

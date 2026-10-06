<?php

namespace App\Support;

class Money
{
    public static function round(float|int|string $amount): float
    {
        return round((float) $amount, 2);
    }
}

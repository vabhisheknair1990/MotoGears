<?php

namespace App\Enums;

enum VehicleType: string
{
    case Car = 'car';
    case Motorcycle = 'motorcycle';
    case Universal = 'universal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

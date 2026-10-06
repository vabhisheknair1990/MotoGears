<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case StockReceived = 'stock_received';
    case OrderReserved = 'order_reserved';
    case OrderCancelled = 'order_cancelled';
    case OrderShipped = 'order_shipped';
    case ManualAdjustment = 'manual_adjustment';
    case Return = 'return';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** Types an admin may record by hand. */
    public static function manualTypes(): array
    {
        return [self::StockReceived, self::ManualAdjustment, self::Return];
    }
}

<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::OutForDelivery => 'Out for Delivery',
            default => ucfirst($this->value),
        };
    }

    /** Statuses an order may move to from the current one. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Packed, self::Shipped, self::Cancelled],
            self::Processing => [self::Packed, self::Shipped, self::Cancelled],
            self::Packed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::OutForDelivery, self::Delivered, self::Returned],
            self::OutForDelivery => [self::Delivered, self::Returned],
            self::Delivered => [self::Returned],
            self::Returned => [self::Refunded],
            self::Cancelled => [self::Refunded],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Processing], true);
    }

    /** Stock is still reserved (not yet shipped) in these statuses. */
    public function holdsReservation(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Processing, self::Packed], true);
    }

    /** Statuses that count as revenue in reports. */
    public static function revenueStatuses(): array
    {
        return [self::Confirmed, self::Processing, self::Packed, self::Shipped, self::OutForDelivery, self::Delivered];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

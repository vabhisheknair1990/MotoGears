<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single source of truth for money: turns (product, quantity) pairs plus an optional coupon
 * and shipping method into line-level and order-level totals. Prices always come from the
 * database — never from the client. Catalogue prices are exclusive of GST.
 */
class PricingService
{
    public function __construct(
        private CouponService $coupons,
        private ShippingService $shipping,
        private SettingsService $settings,
    ) {}

    /**
     * @param  iterable<array{product: Product, quantity: int, key?: mixed}>  $items
     */
    public function calculate(iterable $items, ?Coupon $coupon, string $shippingMethod, ?User $user): array
    {
        $lines = collect($items)->values()->mapWithKeys(function (array $item, int $i) {
            /** @var Product $p */
            $p = $item['product'];
            $qty = (int) $item['quantity'];
            $price = (float) $p->price;

            return [$i => [
                'key' => $item['key'] ?? $i,
                'product' => $p,
                'quantity' => $qty,
                'unit_price' => $price,
                'mrp' => (float) $p->mrp,
                'tax_rate' => (float) $p->tax_rate,
                'line_subtotal' => round($price * $qty, 2),
                'line_mrp' => round((float) $p->mrp * $qty, 2),
                'discount' => 0.0,
            ]];
        });

        $subtotal = round($lines->sum('line_subtotal'), 2);
        $couponResult = null;
        $couponError = null;

        if ($coupon && $lines->isNotEmpty()) {
            try {
                $couponResult = $this->coupons->evaluate($coupon, $lines, $user);
                $lines = $this->allocateDiscount($lines, $couponResult['eligible_keys'], $couponResult['discount']);
            } catch (BusinessException $e) {
                $couponError = $e->getMessage();
            }
        }

        $discount = round($lines->sum('discount'), 2);
        $net = round($subtotal - $discount, 2);
        $shippingCost = $this->shipping->cost($shippingMethod, $net, (bool) ($couponResult['free_shipping'] ?? false));
        $shippingTaxRate = $this->settings->float('shipping_tax_rate');

        $lines = $lines->map(function (array $l) {
            $taxable = $l['line_subtotal'] - $l['discount'];
            $l['tax_amount'] = round($taxable * $l['tax_rate'] / 100, 2);
            $l['line_total'] = round($taxable + $l['tax_amount'], 2);

            return $l;
        });

        $shippingTax = round($shippingCost * $shippingTaxRate / 100, 2);
        $tax = round($lines->sum('tax_amount') + $shippingTax, 2);
        $grand = round($net + $shippingCost + $tax, 2);
        $mrpTotal = round($lines->sum('line_mrp'), 2);

        return [
            'lines' => $lines->values(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shippingCost,
            'shipping_tax' => $shippingTax,
            'tax' => $tax,
            'grand_total' => $grand,
            'mrp_total' => $mrpTotal,
            'savings' => round($mrpTotal - $subtotal + $discount, 2),
            'coupon' => $couponResult && $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type->value,
                'description' => $coupon->description,
                'discount' => $couponResult['discount'],
                'free_shipping' => $couponResult['free_shipping'],
            ] : null,
            'coupon_error' => $couponError,
            'shipping_method' => $shippingMethod,
            'free_shipping_threshold' => $this->settings->float('free_shipping_threshold'),
            'amount_to_free_shipping' => max(0, round($this->settings->float('free_shipping_threshold') - $net, 2)),
        ];
    }

    /** Spread a discount across eligible lines proportionally; the last line absorbs rounding. */
    private function allocateDiscount(Collection $lines, array $keys, float $discount): Collection
    {
        if ($discount <= 0 || ! $keys) {
            return $lines;
        }
        $base = $lines->only($keys)->sum('line_subtotal');
        $remaining = $discount;
        $last = end($keys);
        foreach ($keys as $k) {
            $line = $lines[$k];
            $share = $k === $last ? $remaining : round($discount * $line['line_subtotal'] / $base, 2);
            $share = min($share, $line['line_subtotal']);
            $line['discount'] = $share;
            $remaining = round($remaining - $share, 2);
            $lines[$k] = $line;
        }

        return $lines;
    }
}

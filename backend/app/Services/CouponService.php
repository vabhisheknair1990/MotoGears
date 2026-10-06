<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Illuminate\Support\Collection;

class CouponService
{
    public function findByCode(string $code): Coupon
    {
        $coupon = Coupon::with(['products:id', 'categories:id,parent_id'])->where('code', strtoupper(trim($code)))->first();
        if (! $coupon) {
            throw new BusinessException('This coupon code is not valid.', 422, ['code' => ['This coupon code is not valid.']]);
        }

        return $coupon;
    }

    /**
     * Evaluate a coupon against cart lines. Returns the discount and which lines it applies to,
     * or throws a BusinessException explaining why it cannot be used.
     *
     * @param  Collection<int, array{product: \App\Models\Product, quantity: int, line_subtotal: float}>  $lines
     * @return array{discount: float, free_shipping: bool, eligible_keys: array<int>}
     */
    public function evaluate(Coupon $coupon, Collection $lines, ?User $user): array
    {
        $fail = fn (string $msg) => throw new BusinessException($msg, 422, ['code' => [$msg]]);

        if (! $coupon->is_active) {
            $fail('This coupon is no longer active.');
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            $fail('This coupon is not active yet.');
        }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            $fail('This coupon has expired.');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            $fail('This coupon has reached its usage limit.');
        }
        if ($user && $coupon->per_user_limit !== null) {
            $used = CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count();
            if ($used >= $coupon->per_user_limit) {
                $fail('You have already used this coupon the maximum number of times.');
            }
        }

        $eligible = $this->eligibleLines($coupon, $lines);
        if ($eligible->isEmpty()) {
            $fail('This coupon does not apply to any item in your cart.');
        }

        $eligibleSubtotal = round($eligible->sum('line_subtotal'), 2);
        $min = (float) $coupon->min_order_amount;
        if ($eligibleSubtotal < $min) {
            $fail('Add items worth ₹'.number_format($min - $eligibleSubtotal, 2).' more to use this coupon (minimum ₹'.number_format($min).').');
        }

        $discount = match ($coupon->type) {
            CouponType::Percentage => $eligibleSubtotal * ((float) $coupon->value / 100),
            CouponType::Fixed => min((float) $coupon->value, $eligibleSubtotal),
            CouponType::FreeShipping => 0.0,
        };
        if ($coupon->max_discount !== null && $coupon->type !== CouponType::FreeShipping) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return [
            'discount' => round($discount, 2),
            'free_shipping' => $coupon->type === CouponType::FreeShipping,
            'eligible_keys' => $eligible->keys()->all(),
        ];
    }

    private function eligibleLines(Coupon $coupon, Collection $lines): Collection
    {
        $productIds = $coupon->products->pluck('id');
        $categoryIds = $coupon->categories->pluck('id');
        if ($productIds->isEmpty() && $categoryIds->isEmpty()) {
            return $lines;
        }

        return $lines->filter(function (array $line) use ($productIds, $categoryIds) {
            $p = $line['product'];

            return $productIds->contains($p->id)
                || $categoryIds->contains($p->category_id)
                || ($p->category?->parent_id && $categoryIds->contains($p->category->parent_id));
        });
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            ['WELCOME10', 'Welcome offer', '10% off your order (max ₹1,000) on orders above ₹999', 'percentage', 10, 999, 1000, null, 1],
            ['SAVE500', 'Flat ₹500 off', 'Flat ₹500 off on orders above ₹4,999', 'fixed', 500, 4999, null, null, null],
            ['FREESHIP', 'Free shipping', 'Free delivery on any order', 'free_shipping', 0, 0, null, null, null],
            ['BRAKES15', 'Brake week', '15% off brake parts (max ₹1,500)', 'percentage', 15, 1500, 1500, ['brake-system', 'brake-parts'], null],
            ['RIDE20', 'Touring season', '20% off touring gear for riders', 'percentage', 20, 2000, 2500, ['touring'], null],
            ['MONSOON300', 'Monsoon ready', '₹300 off wipers & lighting (expired)', 'fixed', 300, 1500, null, ['lighting', 'exterior-accessories'], null],
        ];
        foreach ($coupons as [$code, $name, $desc, $type, $value, $min, $max, $cats, $perUser]) {
            $coupon = Coupon::withTrashed()->updateOrCreate(['code' => $code], [
                'name' => $name, 'description' => $desc, 'type' => $type, 'value' => $value, 'min_order_amount' => $min,
                'max_discount' => $max, 'per_user_limit' => $perUser, 'is_active' => true,
                'starts_at' => now()->subMonths(6),
                'expires_at' => $code === 'MONSOON300' ? now()->subDays(10) : now()->addYear(),
                'usage_limit' => $code === 'BRAKES15' ? 500 : null,
            ]);
            if ($cats) {
                $coupon->categories()->sync(Category::whereIn('slug', $cats)->pluck('id'));
            }
        }
    }
}

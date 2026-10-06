<?php

namespace Database\Seeders;

use App\Enums\InventoryTransactionType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus as S;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Historical orders that flow through the real pricing and inventory services, so dashboard
 * figures, stock levels, inventory history and reviews are all mutually consistent.
 */
class OrderSeeder extends Seeder
{
    private const REVIEW_TEXT = [
        5 => [
            ['Perfect fit, excellent quality', 'Fitted in 20 minutes at home. Exactly like the OE part, no modifications needed. Delivery was quick too.'],
            ['Worth every rupee', 'Genuine product with hologram and invoice. Noticeable improvement straight away. Highly recommended.'],
            ['Great upgrade', 'Did my research and this was the best option for my vehicle. Packaging was solid and it arrived a day early.'],
            ['Excellent', 'Second time buying from MotoGears. Compatibility checker was spot on for my variant.'],
        ],
        4 => [
            ['Good product, minor delay', 'Quality is very good and fitment is perfect. Delivery took one extra day but support kept me updated.'],
            ['Solid value', 'Does the job well. Instructions could be clearer, but my mechanic had no issues installing it.'],
            ['Happy with purchase', 'Good build quality and genuine part. Price was better than my local shop.'],
        ],
        3 => [
            ['Average', 'Works fine, nothing exceptional. Expected slightly better finish for the price.'],
            ['Okay product', 'Fitment was correct but needed a little adjustment. Performance is as expected.'],
        ],
        2 => [['Not as expected', 'Product is genuine but it made a slight noise for the first few days. Settled down later.']],
        1 => [['Disappointed', 'Box was damaged in transit. Support arranged a replacement, but it took a week.']],
    ];

    public function run(): void
    {
        mt_srand(4242);
        $pricing = app(PricingService::class);
        $inventory = app(InventoryService::class);
        $customers = User::customers()->with('addresses')->get();
        $demo = $customers->firstWhere('email', 'customer@example.com');
        $products = Product::with(['brand', 'category', 'primaryImage', 'inventory'])->where('price', '<', 40000)->get();
        $coupons = Coupon::whereIn('code', ['WELCOME10', 'SAVE500', 'FREESHIP'])->get()->keyBy('code');
        $admin = User::where('email', 'orders@example.com')->first();

        $plans = [];
        // Guaranteed history for the demo customer.
        foreach ([[92, S::Delivered], [41, S::Delivered], [9, S::Shipped], [2, S::Confirmed], [60, S::Cancelled]] as [$days, $status]) {
            $plans[] = [$demo, $days, $status];
        }
        for ($i = 0; $i < 85; $i++) {
            $days = (int) round(pow(mt_rand(0, 1000) / 1000, 1.6) * 150); // more recent orders are more frequent
            $plans[] = [$customers->random(), $days, $this->statusFor($days)];
        }
        usort($plans, fn ($a, $b) => $b[1] <=> $a[1]);
        $realNow = now()->copy();

        foreach ($plans as $n => [$user, $days, $status]) {
            Carbon::setTestNow();
            $placed = $realNow->copy()->subDays($days)->setTime(mt_rand(8, 22), mt_rand(0, 59));
            if ($placed->greaterThan($realNow)) {
                $placed = $realNow->copy()->subMinutes(mt_rand(30, 300));
            }
            Carbon::setTestNow($placed);

            $lines = $products->random(mt_rand(1, 4))->map(fn ($p) => ['product' => $p, 'quantity' => mt_rand(1, 10) > 8 ? 2 : 1]);
            $coupon = mt_rand(1, 100) <= 22 ? $coupons->random() : null;
            $method = [PaymentMethod::CashOnDelivery, PaymentMethod::DemoCard, PaymentMethod::DemoCard, PaymentMethod::DemoUpi, PaymentMethod::DemoUpi][mt_rand(0, 4)];
            $shipping = mt_rand(1, 100) <= 18 ? 'express' : 'standard';
            $totals = $pricing->calculate($lines, $coupon, $shipping, $user);
            $address = $user->addresses->firstWhere('is_default', true)?->toSnapshot() ?? $user->addresses->first()->toSnapshot();

            DB::transaction(function () use ($user, $status, $placed, $totals, $method, $shipping, $address, $inventory, $admin, $n, $realNow) {
                $order = Order::create([
                    'order_number' => 'MG'.$placed->format('ymd').'-'.str_pad((string) (10000 + $n * 137 % 90000), 5, '0', STR_PAD_LEFT),
                    'user_id' => $user->id,
                    'status' => S::Pending,
                    'payment_status' => OrderPaymentStatus::Pending,
                    'payment_method' => $method,
                    'shipping_method' => $shipping,
                    'subtotal' => $totals['subtotal'], 'discount' => $totals['discount'], 'shipping_amount' => $totals['shipping'],
                    'tax' => $totals['tax'], 'grand_total' => $totals['grand_total'],
                    'coupon_id' => $totals['coupon']['id'] ?? null, 'coupon_code' => $totals['coupon']['code'] ?? null,
                    'shipping_address' => $address, 'billing_address' => $address,
                    'placed_at' => $placed, 'ip_address' => '10.0.'.mt_rand(0, 255).'.'.mt_rand(1, 254),
                ]);
                foreach ($totals['lines'] as $l) {
                    $p = $l['product'];
                    $order->items()->create([
                        'product_id' => $p->id, 'product_name' => $p->name, 'product_slug' => $p->slug, 'sku' => $p->sku,
                        'part_number' => $p->part_number, 'brand_name' => $p->brand->name, 'image_path' => $p->primaryImage?->path,
                        'mrp' => $l['mrp'], 'unit_price' => $l['unit_price'], 'quantity' => $l['quantity'], 'discount' => $l['discount'],
                        'tax_rate' => $l['tax_rate'], 'tax_amount' => $l['tax_amount'], 'line_total' => $l['line_total'],
                    ]);
                }
                if ($order->coupon_id) {
                    CouponUsage::create(['coupon_id' => $order->coupon_id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => $order->discount]);
                    Coupon::whereKey($order->coupon_id)->increment('used_count');
                }

                $inventory->reserveForOrder($order, $user);
                $history = [[S::Pending, 'Order placed', $placed, $user->id]];
                $t = $placed->copy();
                $paid = $method !== PaymentMethod::CashOnDelivery;
                $failed = $status === S::Pending && $paid; // online payment attempt that failed and awaits retry
                $txn = $paid ? ($method === PaymentMethod::DemoUpi ? 'UPI' : 'DEMO-').Str::upper(Str::random(12)) : 'COD-'.$order->order_number;
                $payment = $order->payments()->create([
                    'method' => $method->value, 'gateway' => $paid ? 'demo' : 'cod', 'transaction_id' => $txn, 'amount' => $order->grand_total,
                    'status' => $failed ? PaymentStatus::Failed : ($paid ? PaymentStatus::Success : PaymentStatus::Pending),
                    'failure_reason' => $failed ? 'Card declined by issuing bank (demo decline).' : null,
                    'meta' => $method === PaymentMethod::DemoCard ? ['brand' => 'Visa', 'last4' => (string) mt_rand(1000, 9999)] : ($method === PaymentMethod::DemoUpi ? ['vpa' => 'us***@okhdfc'] : ['collect_on_delivery' => true]),
                    'paid_at' => $paid && ! $failed ? $placed : null,
                ]);
                if ($failed) {
                    $history[] = [S::Pending, 'Payment failed: Card declined by issuing bank (demo decline). You can retry payment from your order page.', $placed->copy()->addMinute(), null];
                    $order->payment_status = OrderPaymentStatus::Failed;
                }
                $payment->forceFill(['created_at' => $placed, 'updated_at' => $placed])->saveQuietly();

                if ($status === S::Cancelled) {
                    $t->addHours(mt_rand(1, 20));
                    $history[] = [S::Confirmed, $paid ? 'Payment received via '.$method->label() : 'Order confirmed — cash on delivery', $placed->copy()->addMinutes(1), null];
                    $history[] = [S::Cancelled, ['Cancelled by customer: ordered by mistake', 'Cancelled by customer: found a better price', 'Cancelled — address not serviceable'][mt_rand(0, 2)], $t->copy(), $user->id];
                    Carbon::setTestNow($t);
                    $inventory->releaseForOrder($order, $user);
                    $order->status = S::Cancelled;
                    $order->cancelled_at = $t->copy();
                    $order->payment_status = $paid ? OrderPaymentStatus::Refunded : OrderPaymentStatus::Pending;
                    if ($paid) {
                        $payment->update(['status' => PaymentStatus::Refunded]);
                        $history[] = [S::Cancelled, 'Refund of ₹'.number_format((float) $order->grand_total, 2).' issued to original payment method.', $t->copy()->addMinutes(5), null];
                    }
                    CouponUsage::where('order_id', $order->id)->delete();
                } else {
                    $flow = [S::Confirmed, S::Processing, S::Packed, S::Shipped, S::OutForDelivery, S::Delivered, S::Returned, S::Refunded];
                    $target = array_search($status, $flow, true);
                    if (! $failed) {
                        $order->payment_status = $paid ? OrderPaymentStatus::Paid : OrderPaymentStatus::Pending;
                    }
                    foreach ($flow as $idx => $step) {
                        if ($idx > $target || $status === S::Pending) {
                            break;
                        }
                        $t = $idx === 0 ? $placed->copy()->addMinutes(1) : $t->copy()->addHours([0, 6, 14, 10, 30, 8, 72, 48][$idx] + mt_rand(0, 6));
                        if ($t->greaterThan($realNow)) {
                            $t = $realNow->copy()->subMinutes(mt_rand(5, 90));
                        }
                        Carbon::setTestNow($t);
                        $comment = match ($step) {
                            S::Confirmed => $paid ? 'Payment of ₹'.number_format((float) $order->grand_total, 2).' received via '.$method->label()." (Txn {$txn})" : 'Order confirmed — pay ₹'.number_format((float) $order->grand_total, 2).' in cash on delivery',
                            S::Processing => 'Order is being processed at our Bengaluru warehouse',
                            S::Packed => 'Packed and quality-checked',
                            S::Shipped => 'Shipped via BlueDart',
                            S::OutForDelivery => 'Out for delivery',
                            S::Delivered => 'Delivered — signed by customer',
                            S::Returned => 'Returned: item did not match vehicle trim',
                            S::Refunded => 'Refund processed',
                            default => null,
                        };
                        $history[] = [$step, $comment, $t->copy(), $step === S::Confirmed ? null : $admin?->id];
                        if ($step === S::Shipped) {
                            $inventory->shipOrder($order, $admin);
                            $order->shipped_at = $t->copy();
                            $order->tracking_number = 'BD'.mt_rand(10000000, 99999999).'IN';
                            $order->carrier = 'BlueDart';
                        }
                        if ($step === S::Delivered) {
                            $order->delivered_at = $t->copy();
                            if (! $paid) {
                                $payment->update(['status' => PaymentStatus::Success, 'paid_at' => $t->copy()]);
                                $order->payment_status = OrderPaymentStatus::Paid;
                            }
                        }
                        if ($step === S::Returned) {
                            $inventory->returnOrder($order, $admin);
                        }
                        if ($step === S::Refunded) {
                            $payment->update(['status' => PaymentStatus::Refunded]);
                            $order->payment_status = OrderPaymentStatus::Refunded;
                        }
                        $order->status = $step;
                    }
                }

                $order->created_at = $placed;
                $order->updated_at = $t ?? $placed;
                $order->saveQuietly();
                foreach ($history as [$st, $comment, $at, $by]) {
                    $order->statusHistory()->create(['status' => $st, 'comment' => $comment, 'user_id' => $by, 'created_at' => $at]);
                }
                if (! in_array($order->status, [S::Cancelled], true)) {
                    foreach ($order->items as $it) {
                        Product::whereKey($it->product_id)->increment('sold_count', $it->quantity);
                    }
                }
            });
        }
        Carbon::setTestNow();

        $this->seedReviews();
        $this->settleInventory();
    }

    private function statusFor(int $days): S
    {
        $r = mt_rand(1, 100);
        if ($days <= 1) {
            return [S::Pending, S::Confirmed, S::Confirmed, S::Processing][mt_rand(0, 3)];
        }
        if ($days <= 4) {
            return [S::Confirmed, S::Processing, S::Packed, S::Shipped, S::OutForDelivery][mt_rand(0, 4)];
        }
        if ($days <= 8) {
            return $r <= 50 ? S::Shipped : ($r <= 85 ? S::Delivered : S::Cancelled);
        }

        return match (true) {
            $r <= 80 => S::Delivered,
            $r <= 91 => S::Cancelled,
            $r <= 96 => S::Returned,
            default => S::Refunded,
        };
    }

    private function seedReviews(): void
    {
        $orders = Order::with('items')->whereIn('status', [S::Delivered->value, S::Returned->value])->orderBy('delivered_at')->get();
        $count = 0;
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (mt_rand(1, 100) > 85 || Review::where('product_id', $item->product_id)->where('user_id', $order->user_id)->exists()) {
                    continue;
                }
                $rating = [5, 5, 5, 5, 4, 4, 4, 3, 2, 1][mt_rand(0, $order->status === S::Returned ? 9 : 7)];
                [$title, $comment] = self::REVIEW_TEXT[$rating][mt_rand(0, count(self::REVIEW_TEXT[$rating]) - 1)];
                $at = ($order->delivered_at ?? $order->placed_at)->copy()->addDays(mt_rand(1, 10));
                if ($at->isFuture()) {
                    $at = now()->subHours(mt_rand(1, 48));
                }
                $count++;
                $review = new Review([
                    'product_id' => $item->product_id, 'user_id' => $order->user_id, 'order_id' => $order->id,
                    'rating' => $rating, 'title' => $title, 'comment' => $comment,
                    'status' => $count % 13 === 0 ? ReviewStatus::Pending : ReviewStatus::Approved,
                    'is_verified_purchase' => true, 'is_featured' => $rating === 5 && $count % 7 === 0,
                ]);
                $review->helpful_count = mt_rand(0, 25);
                $review->created_at = $at;
                $review->updated_at = $at;
                $review->save();
            }
        }

        // Fill up to a meaningful review count on popular products from repeat customers.
        $customers = User::customers()->pluck('id');
        $popular = Product::orderByDesc('sold_count')->limit(40)->pluck('id');
        while (Review::count() < 130) {
            $pid = $popular->random();
            $uid = $customers->random();
            if (Review::where('product_id', $pid)->where('user_id', $uid)->exists()) {
                continue;
            }
            $rating = [5, 5, 5, 4, 4, 4, 3][mt_rand(0, 6)];
            [$title, $comment] = self::REVIEW_TEXT[$rating][mt_rand(0, count(self::REVIEW_TEXT[$rating]) - 1)];
            $review = new Review(['product_id' => $pid, 'user_id' => $uid, 'rating' => $rating, 'title' => $title, 'comment' => $comment, 'status' => ReviewStatus::Approved, 'is_verified_purchase' => false]);
            $review->created_at = now()->subDays(mt_rand(5, 200));
            $review->save();
        }
    }

    /** Bring each product to its intended demo stock level with a logged cycle-count adjustment. */
    private function settleInventory(): void
    {
        $by = User::where('email', 'inventory@example.com')->first();
        foreach (ProductSeeder::$targetStock as $productId => $target) {
            $inv = Inventory::where('product_id', $productId)->first();
            if (! $inv) {
                continue;
            }
            $delta = ($target + $inv->reserved) - $inv->quantity;
            if ($delta !== 0) {
                $inv->quantity += $delta;
                $inv->save();
                $t = InventoryTransaction::create([
                    'inventory_id' => $inv->id, 'product_id' => $productId, 'type' => InventoryTransactionType::ManualAdjustment,
                    'quantity' => $delta, 'quantity_after' => $inv->quantity, 'reserved_after' => $inv->reserved,
                    'reference_label' => 'CC-'.now()->format('ymd'), 'note' => 'Quarterly cycle count', 'user_id' => $by?->id,
                ]);
                $t->forceFill(['created_at' => now()->subDays(1)])->save();
            }
        }
    }
}

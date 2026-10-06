<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartService
{
    public const TOKEN_HEADER = 'X-Cart-Token';

    public function __construct(
        private PricingService $pricing,
        private CouponService $coupons,
        private SettingsService $settings,
    ) {}

    /** Find the cart for the current user or guest token. Returns null if none exists yet. */
    public function current(Request $request): ?Cart
    {
        $user = $request->user('sanctum');
        if ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }
        $token = $request->header(self::TOKEN_HEADER);

        return $token ? Cart::whereNull('user_id')->where('token', $token)->first() : null;
    }

    /** Find or create the cart for the request (guests get a new token). */
    public function resolve(Request $request): Cart
    {
        return $this->current($request) ?? Cart::create(['token' => Str::random(48)]);
    }

    public function forUser(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function addItem(Cart $cart, int $productId, int $quantity): Cart
    {
        $product = Product::with('inventory')->active()->find($productId);
        if (! $product) {
            throw new BusinessException('This product is not available.', 404);
        }

        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $newQty = ($item->exists ? $item->quantity : 0) + $quantity;
        $this->assertQuantity($product, $newQty);

        $item->quantity = $newQty;
        $item->save();
        $cart->touch();

        return $cart;
    }

    public function updateItem(Cart $cart, CartItem $item, int $quantity): Cart
    {
        $this->assertOwns($cart, $item);
        if ($quantity <= 0) {
            $item->delete();
        } else {
            $this->assertQuantity($item->product()->with('inventory')->firstOrFail(), $quantity);
            $item->update(['quantity' => $quantity]);
        }
        $cart->touch();

        return $cart;
    }

    public function removeItem(Cart $cart, CartItem $item): Cart
    {
        $this->assertOwns($cart, $item);
        $item->delete();
        $cart->touch();

        return $cart;
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_id' => null]);
    }

    public function applyCoupon(Cart $cart, string $code, ?User $user): array
    {
        $coupon = $this->coupons->findByCode($code);
        $summary = $this->summaryWithCoupon($cart, $coupon, $user);
        if ($summary['coupon_error']) {
            throw new BusinessException($summary['coupon_error'], 422, ['code' => [$summary['coupon_error']]]);
        }
        $cart->update(['coupon_id' => $coupon->id]);

        return $this->summary($cart->fresh(), $user);
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_id' => null]);
    }

    public function setShippingMethod(Cart $cart, string $method): void
    {
        $cart->update(['shipping_method' => $method]);
    }

    /** Move a guest cart's lines into the user's cart (called on login / register). */
    public function mergeGuestCart(?string $token, User $user): void
    {
        if (! $token) {
            return;
        }
        $guest = Cart::with('items')->whereNull('user_id')->where('token', $token)->first();
        if (! $guest) {
            return;
        }

        DB::transaction(function () use ($guest, $user) {
            $cart = $this->forUser($user);
            $max = (int) $this->settings->get('max_quantity_per_item');
            foreach ($guest->items as $gi) {
                $item = $cart->items()->firstOrNew(['product_id' => $gi->product_id]);
                $item->quantity = min($max, ($item->exists ? $item->quantity : 0) + $gi->quantity);
                $item->save();
            }
            if ($guest->coupon_id && ! $cart->coupon_id) {
                $cart->update(['coupon_id' => $guest->coupon_id]);
            }
            $guest->delete();
        });
    }

    public function summary(Cart $cart, ?User $user = null): array
    {
        $cart->loadMissing('coupon');

        return $this->summaryWithCoupon($cart, $cart->coupon, $user);
    }

    private function summaryWithCoupon(Cart $cart, $coupon, ?User $user): array
    {
        $cart->load(['items.product' => fn ($q) => $q->with(['brand:id,name,slug', 'category:id,name,slug,parent_id', 'inventory', 'primaryImage'])]);

        $warnings = [];
        $valid = $cart->items->filter(function (CartItem $item) use (&$warnings) {
            if (! $item->product || ! $item->product->is_active) {
                $warnings[] = 'An item in your cart is no longer available and was removed.';
                $item->delete();

                return false;
            }

            return true;
        });

        $totals = $this->pricing->calculate(
            $valid->map(fn (CartItem $i) => ['product' => $i->product, 'quantity' => $i->quantity, 'key' => $i->id]),
            $coupon,
            $cart->shipping_method ?? 'standard',
            $user,
        );

        if ($cart->coupon_id && $coupon && $coupon->id === $cart->coupon_id && $totals['coupon_error']) {
            $warnings[] = 'Coupon '.$cart->coupon?->code.' was removed: '.$totals['coupon_error'];
            $cart->update(['coupon_id' => null]);
        }

        $items = $totals['lines']->map(function (array $l) use ($valid, &$warnings) {
            /** @var CartItem $ci */
            $ci = $valid->firstWhere('id', $l['key']);
            $available = $l['product']->inventory?->available() ?? 0;
            $backorder = (bool) $l['product']->inventory?->allow_backorder;
            $inStock = $backorder || $available >= $l['quantity'];
            if (! $inStock) {
                $warnings[] = $l['product']->name.': only '.$available.' left in stock.';
            }

            return [
                'id' => $ci->id,
                'product' => $l['product'],
                'quantity' => $l['quantity'],
                'unit_price' => $l['unit_price'],
                'mrp' => $l['mrp'],
                'line_subtotal' => $l['line_subtotal'],
                'discount' => $l['discount'],
                'tax_rate' => $l['tax_rate'],
                'tax_amount' => $l['tax_amount'],
                'line_total' => $l['line_total'],
                'available_stock' => $available,
                'in_stock' => $inStock,
                'max_quantity' => $backorder ? (int) $this->settings->get('max_quantity_per_item') : min($available, (int) $this->settings->get('max_quantity_per_item')),
            ];
        });

        return array_merge($totals, [
            'id' => $cart->id,
            'token' => $cart->user_id ? null : $cart->token,
            'items' => $items->values(),
            'item_count' => $items->count(),
            'total_quantity' => (int) $items->sum('quantity'),
            'warnings' => array_values(array_unique($warnings)),
            'is_checkout_ready' => $items->isNotEmpty() && $items->every(fn ($i) => $i['in_stock']),
        ]);
    }

    private function assertQuantity(Product $product, int $qty): void
    {
        $max = (int) $this->settings->get('max_quantity_per_item');
        if ($qty > $max) {
            throw new BusinessException("You can buy at most {$max} units of this item per order.", 422, ['quantity' => ["Maximum {$max} per order."]]);
        }
        if (! $product->isPurchasable($qty)) {
            $available = $product->availableStock();
            $msg = $available > 0 ? "Only {$available} unit(s) of {$product->name} are available." : "{$product->name} is out of stock.";
            throw new BusinessException($msg, 422, ['quantity' => [$msg]]);
        }
    }

    private function assertOwns(Cart $cart, CartItem $item): void
    {
        if ($item->cart_id !== $cart->id) {
            throw new BusinessException('Cart item not found.', 404);
        }
    }
}

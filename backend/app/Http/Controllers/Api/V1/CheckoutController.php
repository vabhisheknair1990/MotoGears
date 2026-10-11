<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use App\Services\Payments\PaymentManager;
use App\Services\SettingsService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(private CartService $carts, private ShippingService $shipping, private SettingsService $settings) {}

    /** Everything the checkout wizard needs in one call. */
    public function show(Request $request): JsonResponse
    {
        $request->validate(['shipping_method' => ['nullable', Rule::in(['standard', 'express'])]]);

        return $this->ok($this->build($request, $this->carts->forUser($request->user()), $request->user()), 'Checkout details retrieved');
    }

    /** Guest checkout: the cart comes from the X-Cart-Token header; there is no address book. */
    public function guest(Request $request): JsonResponse
    {
        $request->validate(['shipping_method' => ['nullable', Rule::in(['standard', 'express'])]]);
        if (! $this->settings->get('guest_checkout_enabled')) {
            return response()->json(['success' => false, 'message' => 'Please log in or create an account to check out.', 'data' => ['guest_checkout_enabled' => false]], 403);
        }
        $cart = $this->carts->current($request) ?? $this->carts->resolve($request);

        return $this->ok($this->build($request, $cart, null), 'Checkout details retrieved');
    }

    private function build(Request $request, \App\Models\Cart $cart, ?\App\Models\User $user): array
    {
        if ($request->filled('shipping_method')) {
            $this->carts->setShippingMethod($cart, $request->shipping_method);
        }
        $summary = $this->carts->summary($cart->fresh(), $user);
        $net = $summary['subtotal'] - $summary['discount'];
        $freeShip = (bool) ($summary['coupon']['free_shipping'] ?? false);

        $payments = collect(PaymentManager::methodOptions())->map(function ($m) use ($summary) {
            $m['available'] = true;
            if ($m['code'] === 'cod') {
                $m['available'] = (bool) $this->settings->get('cod_enabled') && $summary['grand_total'] <= $this->settings->float('cod_max_order_value');
                if (! $m['available']) {
                    $m['unavailable_reason'] = 'Available for orders up to ₹'.number_format($this->settings->float('cod_max_order_value'));
                }
            }

            return $m;
        });

        return [
            'cart' => (new CartResource($summary))->resolve($request),
            'addresses' => $user ? AddressResource::collection($user->addresses()->orderByDesc('is_default')->latest()->get())->resolve($request) : [],
            'shipping_methods' => $this->shipping->options($net, $freeShip),
            'payment_methods' => $payments->values(),
            'is_guest' => $user === null,
        ];
    }
}

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
        $user = $request->user();
        $cart = $this->carts->forUser($user);
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

        return $this->ok([
            'cart' => (new CartResource($summary))->resolve($request),
            'addresses' => AddressResource::collection($user->addresses()->orderByDesc('is_default')->latest()->get())->resolve($request),
            'shipping_methods' => $this->shipping->options($net, $freeShip),
            'payment_methods' => $payments->values(),
        ], 'Checkout details retrieved');
    }
}

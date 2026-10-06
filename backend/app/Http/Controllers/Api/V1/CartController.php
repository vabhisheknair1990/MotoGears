<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddCartItemRequest;
use App\Http\Requests\Shop\ApplyCouponRequest;
use App\Http\Requests\Shop\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Works for guests (identified by the X-Cart-Token header) and logged-in customers
 * (Bearer token). The guest token is returned in the payload and as a response header.
 */
class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->current($request);
        if (! $cart) {
            return $this->ok($this->emptyCart(), 'Cart retrieved successfully');
        }

        return $this->respond($request, $cart, 'Cart retrieved successfully');
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->carts->resolve($request);
        $this->carts->addItem($cart, $request->integer('product_id'), $request->integer('quantity', 1) ?: 1);

        return $this->respond($request, $cart, 'Product added to cart', 201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        $cart = $this->requireCart($request);
        $this->carts->updateItem($cart, $item, $request->integer('quantity'));

        return $this->respond($request, $cart, $request->integer('quantity') > 0 ? 'Cart updated' : 'Item removed from cart');
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->requireCart($request);
        $this->carts->removeItem($cart, $item);

        return $this->respond($request, $cart, 'Item removed from cart');
    }

    public function clear(Request $request): JsonResponse
    {
        if ($cart = $this->carts->current($request)) {
            $this->carts->clear($cart);

            return $this->respond($request, $cart, 'Cart cleared');
        }

        return $this->ok($this->emptyCart(), 'Cart cleared');
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $cart = $this->requireCart($request);
        $summary = $this->carts->applyCoupon($cart, $request->code, $request->user('sanctum'));

        return $this->ok(new CartResource($summary), 'Coupon '.strtoupper($request->code).' applied');
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->requireCart($request);
        $this->carts->removeCoupon($cart);

        return $this->respond($request, $cart, 'Coupon removed');
    }

    public function setShipping(Request $request): JsonResponse
    {
        $request->validate(['shipping_method' => ['required', Rule::in(['standard', 'express'])]]);
        $cart = $this->requireCart($request);
        $this->carts->setShippingMethod($cart, $request->shipping_method);

        return $this->respond($request, $cart, 'Shipping method updated');
    }

    private function requireCart(Request $request): Cart
    {
        $cart = $this->carts->current($request);
        abort_if(! $cart, 404, 'Cart not found.');

        return $cart;
    }

    private function respond(Request $request, Cart $cart, string $message, int $status = 200): JsonResponse
    {
        $summary = $this->carts->summary($cart->fresh(), $request->user('sanctum'));
        $response = $this->ok(new CartResource($summary), $message, [], $status);
        if (! $cart->user_id) {
            $response->headers->set(CartService::TOKEN_HEADER, $cart->token);
        }

        return $response;
    }

    private function emptyCart(): array
    {
        return [
            'id' => null, 'token' => null, 'items' => [], 'item_count' => 0, 'total_quantity' => 0,
            'subtotal' => 0, 'discount' => 0, 'shipping' => 0, 'tax' => 0, 'grand_total' => 0, 'mrp_total' => 0, 'savings' => 0,
            'coupon' => null, 'shipping_method' => 'standard', 'free_shipping_threshold' => (float) app(\App\Services\SettingsService::class)->get('free_shipping_threshold'),
            'amount_to_free_shipping' => (float) app(\App\Services\SettingsService::class)->get('free_shipping_threshold'),
            'warnings' => [], 'is_checkout_ready' => false,
        ];
    }
}

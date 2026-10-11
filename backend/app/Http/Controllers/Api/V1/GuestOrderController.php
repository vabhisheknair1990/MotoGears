<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\GuestPlaceOrderRequest;
use App\Http\Requests\Shop\PayOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\AuthTokenService;
use App\Services\CartService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\Payments\Razorpay\RazorpayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Orders placed without an account. A guest proves access with the order's secret token
 * (X-Order-Token header or ?token=), which comes back when the order is placed and is in
 * the confirmation email; "Track order" (order number + email) recovers it.
 * Unknown order + wrong token both answer 404, so order numbers can't be probed.
 */
class GuestOrderController extends Controller
{
    public const TOKEN_HEADER = 'X-Order-Token';

    public function __construct(
        private OrderService $orders,
        private CartService $carts,
        private InvoiceService $invoices,
        private RazorpayPaymentService $razorpay,
    ) {}

    public function store(GuestPlaceOrderRequest $request): JsonResponse
    {
        if ($request->user('sanctum')) {
            throw new BusinessException('You are logged in — your order will be saved to your account. Please use the normal checkout.', 409);
        }
        $cart = $this->carts->current($request);
        if (! $cart) {
            throw new BusinessException('Your cart is empty.', 422, ['cart' => ['Your cart is empty.']]);
        }

        $result = $this->orders->placeFromCart(null, $request->validated(), $request->ip(), $cart);
        $order = $result['order']->load(['items', 'statusHistory', 'payment']);
        $payment = $result['payment'];
        $awaitingGateway = $payment->status === PaymentStatus::Pending && PaymentMethod::from($payment->method)->isRedirectFlow();

        return response()->json([
            'success' => true,
            'message' => match (true) {
                $payment->status === PaymentStatus::Success => 'Order placed successfully',
                $awaitingGateway => 'Order created — complete the payment in the Razorpay window',
                $payment->status === PaymentStatus::Pending => 'Order placed successfully — pay on delivery',
                default => 'Order created but payment failed: '.$payment->failure_reason,
            },
            'data' => [
                'order' => (new OrderResource($order))->resolve($request),
                'payment' => (new PaymentResource($payment))->resolve($request),
                'payment_successful' => $payment->status !== PaymentStatus::Failed && ! $awaitingGateway,
                'razorpay' => $awaitingGateway ? $this->razorpay->checkoutOptions($order, $payment) : null,
                'guest_token' => $order->guest_token,
            ],
        ], 201);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        $model = $this->authorizeGuest($request, $order)->load(['items', 'statusHistory', 'payment']);

        return $this->ok(new OrderResource($model), 'Order retrieved successfully');
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $model = $this->authorizeGuest($request, $order);
        $updated = $this->orders->cancelByCustomer($model, null, $request->reason);

        return $this->ok(new OrderResource($updated->load(['items', 'statusHistory', 'payment'])), 'Order cancelled');
    }

    public function pay(PayOrderRequest $request, string $order): JsonResponse
    {
        $model = $this->authorizeGuest($request, $order);
        $payment = $this->orders->pay($model, PaymentMethod::from($request->payment_method), $request->input('payment_details') ?? [], null);
        $ok = $payment->status === PaymentStatus::Success;
        $awaitingGateway = $payment->status === PaymentStatus::Pending && PaymentMethod::from($payment->method)->isRedirectFlow();

        return response()->json([
            'success' => $ok || $awaitingGateway,
            'message' => $ok ? 'Payment successful' : ($awaitingGateway ? 'Complete the payment in the Razorpay window' : 'Payment failed: '.$payment->failure_reason),
            'data' => [
                'order' => (new OrderResource($model->fresh()->load(['items', 'statusHistory', 'payment'])))->resolve($request),
                'payment' => (new PaymentResource($payment))->resolve($request),
                'payment_successful' => $ok,
                'razorpay' => $awaitingGateway ? $this->razorpay->checkoutOptions($model, $payment) : null,
            ],
        ], $ok || $awaitingGateway ? 200 : 402);
    }

    public function razorpayVerify(Request $request, string $order): JsonResponse
    {
        $model = $this->authorizeGuest($request, $order);
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);
        $payment = $this->razorpay->confirmFromCheckout($model, $data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'], null);
        $ok = $payment->status->value === 'success';

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Payment successful' : 'Payment failed: '.$payment->failure_reason,
            'data' => [
                'order' => (new OrderResource($model->fresh()->load(['items', 'statusHistory', 'payment'])))->resolve($request),
                'payment' => (new PaymentResource($payment))->resolve($request),
                'payment_successful' => $ok,
            ],
        ], $ok ? 200 : 402);
    }

    public function razorpayFailed(Request $request, string $order): JsonResponse
    {
        $model = $this->authorizeGuest($request, $order);
        $data = $request->validate(['razorpay_order_id' => ['required', 'string', 'max:64'], 'reason' => ['nullable', 'string', 'max:250']]);
        $payment = $this->razorpay->reportFailure($model, $data['razorpay_order_id'], $data['reason'] ?? null, null);

        return $this->ok(['payment' => (new PaymentResource($payment))->resolve($request)], 'Payment failure recorded');
    }

    public function invoice(Request $request, string $order): JsonResponse
    {
        return $this->ok($this->invoices->data($this->authorizeGuest($request, $order)), 'Invoice retrieved successfully');
    }

    public function downloadInvoice(Request $request, string $order): Response
    {
        $model = $this->authorizeGuest($request, $order);

        return response($this->invoices->html($model), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invoice-'.$model->order_number.'.html"',
        ]);
    }

    /** "Track order": order number + checkout email → the order's secret link. */
    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);
        $order = Order::where('order_number', strtoupper(trim($data['order_number'])))
            ->where('customer_email', strtolower(trim($data['email'])))
            ->first();
        if (! $order) {
            throw new BusinessException("We couldn't find an order with that number and email. Check both and try again.", 404);
        }
        if (! $order->guest_token) {
            return response()->json([
                'success' => false,
                'message' => 'This order belongs to an account. Log in to see it under My orders.',
                'data' => ['requires_login' => true, 'order_number' => $order->order_number],
            ], 409);
        }

        return $this->ok(['order_number' => $order->order_number, 'token' => $order->guest_token], 'Order found');
    }

    /**
     * Turn a guest order into an account: creates the account with the order's email,
     * moves the order into it and logs the customer in.
     */
    public function createAccount(Request $request, string $order, AuthTokenService $tokens): JsonResponse
    {
        $model = $this->authorizeGuest($request, $order);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ]);
        if (! $model->is_guest) {
            throw new BusinessException('This order is already in an account. Log in to see it.', 409);
        }
        if (User::withTrashed()->where('email', $model->customer_email)->exists()) {
            throw new BusinessException('An account with '.$model->customer_email.' already exists. Log in, then open this order link again and choose "Add to my account".', 409);
        }

        $user = DB::transaction(function () use ($model, $data) {
            $user = User::create([
                'name' => $data['name'] ?? $model->customer_name ?? 'Customer',
                'email' => $model->customer_email,
                'phone' => $model->customer_phone,
                'password' => $data['password'],
                'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
            ]);
            $user->assignRole(Role::CUSTOMER);
            Wishlist::create(['user_id' => $user->id]);
            $this->orders->attachGuestOrder($model, $user);
            // Save the delivery address for next time.
            $address = collect($model->shipping_address)->only(['name', 'phone', 'line1', 'line2', 'landmark', 'city', 'state', 'postal_code', 'country'])->all();
            $user->addresses()->create($address + ['label' => 'Home', 'is_default' => true]);

            return $user;
        });

        return $this->created($tokens->issue($user, $request) + ['order_id' => $model->id], 'Account created — your order is now in My orders');
    }

    /** Logged-in customer: add a guest order (opened with its link) to their account. */
    public function attach(Request $request, string $order): JsonResponse
    {
        $model = $this->authorizeGuest($request, $order);
        $updated = $this->orders->attachGuestOrder($model, $request->user());

        return $this->ok(new OrderResource($updated->load(['items', 'statusHistory', 'payment'])), 'Order added to your account');
    }

    private function authorizeGuest(Request $request, string $number): Order
    {
        $token = $request->header(self::TOKEN_HEADER) ?: $request->query('token');
        $order = Order::where('order_number', strtoupper($number))->first();
        if (! $order || ! $order->matchesGuestToken(is_string($token) ? $token : null)) {
            throw new BusinessException('Order not found. Please use the link from your confirmation email, or Track order.', 404);
        }

        return $order;
    }
}

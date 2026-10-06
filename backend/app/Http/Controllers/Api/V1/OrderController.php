<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\PayOrderRequest;
use App\Http\Requests\Shop\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Review;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\Payments\Razorpay\RazorpayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private InvoiceService $invoices, private RazorpayPaymentService $razorpay) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'string'], 'search' => ['nullable', 'string', 'max:50']]);
        $page = $request->user()->orders()
            ->with('items')->withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('order_number', 'like', '%'.$s.'%'))
            ->latest()->paginate($this->perPage(10, 50));

        return $this->paginated($page, OrderResource::class, 'Orders retrieved successfully');
    }

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $result = $this->orders->placeFromCart($request->user(), $request->validated(), $request->ip());
        $order = $result['order']->load(['items', 'statusHistory', 'payment']);
        $payment = $result['payment'];

        $awaitingGateway = $payment->status === PaymentStatus::Pending && PaymentMethod::from($payment->method)->isRedirectFlow();
        $message = match (true) {
            $payment->status === PaymentStatus::Success => 'Order placed successfully',
            $awaitingGateway => 'Order created — complete the payment in the Razorpay window',
            $payment->status === PaymentStatus::Pending => 'Order placed successfully — pay on delivery',
            default => 'Order created but payment failed: '.$payment->failure_reason,
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'order' => (new OrderResource($order))->resolve($request),
                'payment' => (new PaymentResource($payment))->resolve($request),
                'payment_successful' => $payment->status !== PaymentStatus::Failed && ! $awaitingGateway,
                'razorpay' => $awaitingGateway ? $this->razorpay->checkoutOptions($order, $payment) : null,
            ],
        ], 201);
    }

    /** Accepts the numeric id or the order number. */
    public function show(Request $request, string $order): JsonResponse
    {
        $model = $this->find($order);
        $this->authorize('view', $model);
        $model->load(['items', 'statusHistory', 'payment']);

        $reviewed = Review::where('user_id', $request->user()->id)->whereIn('product_id', $model->items->pluck('product_id'))->pluck('product_id');
        $model->items->each(fn ($i) => $i->reviewed = $reviewed->contains($i->product_id));

        return $this->ok(new OrderResource($model), 'Order retrieved successfully');
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $model = $this->find($order);
        $this->authorize('cancel', $model);
        $updated = $this->orders->cancelByCustomer($model, $request->user(), $request->reason);

        return $this->ok(new OrderResource($updated->load(['items', 'statusHistory', 'payment'])), 'Order cancelled');
    }

    public function pay(PayOrderRequest $request, string $order): JsonResponse
    {
        $model = $this->find($order);
        $this->authorize('pay', $model);
        $payment = $this->orders->pay($model, PaymentMethod::from($request->payment_method), $request->input('payment_details') ?? [], $request->user());
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

    /** Structured invoice data (Angular renders it for view / print). */
    public function invoice(Request $request, string $order): JsonResponse
    {
        $model = $this->find($order);
        $this->authorize('view', $model);

        return $this->ok($this->invoices->data($model), 'Invoice retrieved successfully');
    }

    /** Printable, PDF-ready HTML invoice as a file download. */
    public function downloadInvoice(Request $request, string $order): Response
    {
        $model = $this->find($order);
        $this->authorize('view', $model);

        return response($this->invoices->html($model), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invoice-'.$model->order_number.'.html"',
        ]);
    }

    private function find(string $key): Order
    {
        return Order::where(is_numeric($key) ? 'id' : 'order_number', $key)->firstOrFail();
    }
}

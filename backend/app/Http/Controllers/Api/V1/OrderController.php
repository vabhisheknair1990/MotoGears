<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\PayOrderRequest;
use App\Http\Requests\Shop\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Review;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\Payments\PaymentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private InvoiceService $invoices, private PaymentPayload $payload) {}

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

        return $this->payload->placed($request, $result['order'], $result['payment']);
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

        return $this->payload->retried($request, $model, $payment);
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

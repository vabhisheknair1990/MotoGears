<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private InvoiceService $invoices, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
            'payment_method' => ['nullable', Rule::in(\App\Enums\PaymentMethod::values())],
            'customer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'min_total' => ['nullable', 'numeric'], 'max_total' => ['nullable', 'numeric'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'total_high', 'total_low'])],
        ]);

        $q = Order::with(['user:id,name,email,phone', 'items'])->withCount('items')
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($w) => $w->where('order_number', 'like', "%{$s}%")
                    ->orWhere('tracking_number', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
            })
            ->when($request->status, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->payment_method, fn ($q, $s) => $q->where('payment_method', $s))
            ->when($request->customer_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->from, fn ($q, $d) => $q->where('placed_at', '>=', \Carbon\Carbon::parse($d)->startOfDay()))
            ->when($request->to, fn ($q, $d) => $q->where('placed_at', '<=', \Carbon\Carbon::parse($d)->endOfDay()))
            ->when($request->filled('min_total'), fn ($q) => $q->where('grand_total', '>=', $request->min_total))
            ->when($request->filled('max_total'), fn ($q) => $q->where('grand_total', '<=', $request->max_total));

        match ($request->sort) {
            'oldest' => $q->orderBy('id'),
            'total_high' => $q->orderByDesc('grand_total'),
            'total_low' => $q->orderBy('grand_total'),
            default => $q->orderByDesc('id'),
        };

        return $this->paginated($q->paginate($this->perPage(20)), OrderResource::class, 'Orders retrieved successfully', [
            'status_counts' => Order::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
            'statuses' => OrderStatus::options(),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'items', 'statusHistory.user', 'payment', 'payments']);

        return $this->ok(new OrderResource($order), 'Order retrieved successfully');
    }

    public function updateStatus(OrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('updateStatus', $order);
        $updated = $this->orders->transition(
            $order,
            OrderStatus::from($request->status),
            $request->user(),
            $request->comment,
            ['tracking_number' => $request->tracking_number, 'carrier' => $request->carrier],
        );

        return $this->ok(new OrderResource($updated->load(['user', 'items', 'statusHistory.user', 'payment', 'payments'])), 'Order status updated to '.$updated->status->label());
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'tracking_number' => ['nullable', 'string', 'max:60'],
            'carrier' => ['nullable', 'string', 'max:60'],
        ]);
        $order->update($data);
        $this->audit->changes('order.updated', $order);

        return $this->ok(new OrderResource($order->load(['user', 'items', 'statusHistory.user', 'payment', 'payments'])), 'Order updated');
    }

    public function invoice(Order $order): JsonResponse
    {
        return $this->ok($this->invoices->data($order), 'Invoice retrieved successfully');
    }

    public function downloadInvoice(Order $order): \Illuminate\Http\Response
    {
        return response($this->invoices->html($order), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invoice-'.$order->order_number.'.html"',
        ]);
    }
}

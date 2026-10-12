<?php

namespace App\Http\Resources;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->is('v1/admin/*');

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'shipping_method' => $this->shipping_method,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'shipping' => (float) $this->shipping_amount,
            'tax' => (float) $this->tax,
            'grand_total' => (float) $this->grand_total,
            'currency' => $this->currency,
            'coupon_code' => $this->coupon_code,
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'tracking_number' => $this->tracking_number,
            'carrier' => $this->carrier,
            'customer_notes' => $this->customer_notes,
            'admin_notes' => $this->when($isAdmin, $this->admin_notes),
            'items_count' => $this->whenCounted('items', fn () => $this->items_count),
            'total_quantity' => $this->when($this->relationLoaded('items'), fn () => (int) $this->items->sum('quantity')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'preview_items' => $this->when($this->relationLoaded('items') && ! $request->routeIs('*.show'), fn () => $this->items->take(3)->map(fn ($i) => [
                'name' => $i->product_name, 'image' => \App\Support\Media::url($i->image_path), 'quantity' => $i->quantity,
            ])),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'payments' => $this->when($isAdmin && $this->relationLoaded('payments'), fn () => PaymentResource::collection($this->payments)),
            'timeline' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory
                ->when(! $isAdmin, fn ($c) => $c->where('is_customer_visible', true))
                ->map(fn ($h) => [
                    'status' => $h->status->value,
                    'label' => $h->status->label(),
                    'comment' => $h->comment,
                    'by' => $isAdmin ? $h->user?->name : null,
                    'at' => $h->created_at?->toIso8601String(),
                ])->values()),
            'is_guest' => (bool) $this->is_guest,
            // Contact snapshot taken at checkout (the account's details for older orders).
            'customer' => [
                'id' => $this->user_id,
                'name' => $this->customer_name ?? ($this->relationLoaded('user') ? $this->user?->name : null),
                'email' => $this->customer_email ?? ($this->relationLoaded('user') ? $this->user?->email : null),
                'phone' => $this->customer_phone ?? ($this->relationLoaded('user') ? $this->user?->phone : null),
                'is_guest' => (bool) $this->is_guest,
            ],
            'can_cancel' => $this->status->isCancellableByCustomer(),
            'can_pay' => $this->status === OrderStatus::Pending && $this->payment_status !== OrderPaymentStatus::Paid && $this->payment_method->value !== 'cod',
            'can_review' => ! $this->is_guest && in_array($this->status, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Packed, OrderStatus::Shipped, OrderStatus::OutForDelivery, OrderStatus::Delivered], true),
            // Cancelled / returned but the money is still with us (automatic refund failed).
            'refund_pending' => $this->when($isAdmin, fn () => in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true) && $this->payment_status === OrderPaymentStatus::Paid),
            'allowed_transitions' => $this->when($isAdmin, fn () => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], $this->status->allowedTransitions())),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

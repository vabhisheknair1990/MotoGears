<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Exceptions\BusinessException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private CartService $carts,
        private PricingService $pricing,
        private InventoryService $inventory,
        private PaymentManager $payments,
        private SettingsService $settings,
        private AuditLogger $audit,
    ) {}

    /**
     * Turn the user's cart into an order. Prices, discounts, tax and stock are all recomputed
     * server-side inside a transaction; the client only chooses address, shipping and payment.
     *
     * @return array{order: Order, payment: \App\Models\Payment}
     */
    public function placeFromCart(User $user, array $data, ?string $ip = null): array
    {
        $cart = $this->carts->forUser($user);
        $cart->load(['coupon', 'items.product' => fn ($q) => $q->with(['brand', 'category', 'inventory', 'primaryImage'])]);
        if ($cart->items->isEmpty()) {
            throw new BusinessException('Your cart is empty.', 422, ['cart' => ['Your cart is empty.']]);
        }
        foreach ($cart->items as $item) {
            if (! $item->product || ! $item->product->is_active) {
                throw new BusinessException('An item in your cart is no longer available. Please review your cart.', 422);
            }
        }

        $method = PaymentMethod::from($data['payment_method']);
        $this->assertMethodOffered($method);
        $shippingMethod = $data['shipping_method'] ?? 'standard';

        $shippingAddress = $this->resolveAddress($user, $data, 'shipping');
        $billingAddress = ! empty($data['billing_same_as_shipping']) || empty($data['billing_address_id']) && empty($data['billing_address'])
            ? $shippingAddress
            : $this->resolveAddress($user, $data, 'billing');

        $totals = $this->pricing->calculate(
            $cart->items->map(fn ($i) => ['product' => $i->product, 'quantity' => $i->quantity]),
            $cart->coupon,
            $shippingMethod,
            $user,
        );
        if ($cart->coupon && $totals['coupon_error']) {
            $cart->update(['coupon_id' => null]);
            throw new BusinessException('Coupon '.$cart->coupon->code.' can no longer be applied: '.$totals['coupon_error'], 422, ['coupon' => [$totals['coupon_error']]]);
        }
        if ($method === PaymentMethod::CashOnDelivery) {
            if (! $this->settings->get('cod_enabled')) {
                throw new BusinessException('Cash on Delivery is currently unavailable.', 422, ['payment_method' => ['Cash on Delivery is unavailable.']]);
            }
            if ($totals['grand_total'] > $this->settings->float('cod_max_order_value')) {
                $msg = 'Cash on Delivery is available for orders up to ₹'.number_format($this->settings->float('cod_max_order_value')).'.';
                throw new BusinessException($msg, 422, ['payment_method' => [$msg]]);
            }
        }

        $order = DB::transaction(function () use ($user, $cart, $totals, $method, $shippingMethod, $shippingAddress, $billingAddress, $data, $ip) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'payment_status' => OrderPaymentStatus::Pending,
                'payment_method' => $method,
                'shipping_method' => $shippingMethod,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'shipping_amount' => $totals['shipping'],
                'tax' => $totals['tax'],
                'grand_total' => $totals['grand_total'],
                'currency' => 'INR',
                'coupon_id' => $totals['coupon']['id'] ?? null,
                'coupon_code' => $totals['coupon']['code'] ?? null,
                'shipping_address' => $shippingAddress,
                'billing_address' => $billingAddress,
                'customer_notes' => $data['notes'] ?? null,
                'ip_address' => $ip,
                'placed_at' => now(),
            ]);

            foreach ($totals['lines'] as $l) {
                /** @var Product $p */
                $p = $l['product'];
                $order->items()->create([
                    'product_id' => $p->id,
                    'product_name' => $p->name,
                    'product_slug' => $p->slug,
                    'sku' => $p->sku,
                    'part_number' => $p->part_number,
                    'brand_name' => $p->brand?->name,
                    'image_path' => $p->primaryImage?->path,
                    'mrp' => $l['mrp'],
                    'unit_price' => $l['unit_price'],
                    'quantity' => $l['quantity'],
                    'discount' => $l['discount'],
                    'tax_rate' => $l['tax_rate'],
                    'tax_amount' => $l['tax_amount'],
                    'line_total' => $l['line_total'],
                ]);
                Product::whereKey($p->id)->increment('sold_count', $l['quantity']);
            }

            $this->inventory->reserveForOrder($order, $user);

            if ($order->coupon_id) {
                $coupon = Coupon::whereKey($order->coupon_id)->lockForUpdate()->first();
                if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                    throw new BusinessException('This coupon has reached its usage limit.', 422, ['coupon' => ['Usage limit reached.']]);
                }
                $coupon->increment('used_count');
                CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => $order->discount]);
            }

            $order->statusHistory()->create(['status' => OrderStatus::Pending, 'comment' => 'Order placed', 'user_id' => $user->id]);
            $this->carts->clear($cart);

            return $order;
        });

        $payment = $this->pay($order, $method, $data['payment_details'] ?? [], $user);
        OrderPlaced::dispatch($order);

        return ['order' => $order->fresh(), 'payment' => $payment];
    }

    /** Run (or retry) payment for a pending order and move it forward on success. */
    public function pay(Order $order, PaymentMethod $method, array $details, ?User $by = null): \App\Models\Payment
    {
        if ($order->status !== OrderStatus::Pending || $order->payment_status === OrderPaymentStatus::Paid) {
            throw new BusinessException('This order does not require payment.', 422);
        }
        $this->assertMethodOffered($method);
        if ($order->payment_method !== $method) {
            $order->update(['payment_method' => $method]);
        }
        // A new attempt supersedes any unfinished popup from an earlier one.
        $order->payments()->whereIn('status', [PaymentStatus::Initiated, PaymentStatus::Pending])
            ->where('method', '!=', PaymentMethod::CashOnDelivery->value)
            ->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'Superseded by a new payment attempt']);

        $payment = $this->payments->process($order, $method, $details);

        DB::transaction(function () use ($order, $payment, $method, $by) {
            if ($payment->status === PaymentStatus::Success) {
                $order->update(['payment_status' => OrderPaymentStatus::Paid, 'status' => OrderStatus::Confirmed]);
                $order->statusHistory()->create([
                    'status' => OrderStatus::Confirmed,
                    'comment' => 'Payment of ₹'.number_format((float) $payment->amount, 2).' received via '.$method->label().' (Txn '.$payment->transaction_id.')',
                    'user_id' => $by?->id,
                ]);
            } elseif ($payment->status === PaymentStatus::Pending && $method->isRedirectFlow()) {
                // Customer completes payment in the Razorpay popup; confirmed by signature check or webhook.
                $order->update(['payment_status' => OrderPaymentStatus::Pending]);
            } elseif ($payment->status === PaymentStatus::Pending && $method === PaymentMethod::CashOnDelivery) {
                $order->update(['payment_status' => OrderPaymentStatus::Pending, 'status' => OrderStatus::Confirmed]);
                $order->statusHistory()->create(['status' => OrderStatus::Confirmed, 'comment' => 'Order confirmed — pay ₹'.number_format((float) $order->grand_total, 2).' in cash on delivery', 'user_id' => $by?->id]);
            } else {
                $order->update(['payment_status' => OrderPaymentStatus::Failed]);
                $order->statusHistory()->create([
                    'status' => OrderStatus::Pending,
                    'comment' => 'Payment failed: '.$payment->failure_reason.' You can retry payment from your order page.',
                    'user_id' => $by?->id,
                ]);
            }
        });

        return $payment;
    }

    public function cancelByCustomer(Order $order, User $user, ?string $reason): Order
    {
        if (! $order->status->isCancellableByCustomer()) {
            throw new BusinessException('This order can no longer be cancelled. Please contact support.', 422);
        }

        return $this->transition($order, OrderStatus::Cancelled, $user, 'Cancelled by customer'.($reason ? ': '.$reason : ''));
    }

    /**
     * Admin status change with all inventory / payment side-effects.
     */
    public function transition(Order $order, OrderStatus $to, ?User $by, ?string $comment = null, array $extra = []): Order
    {
        $from = $order->status;
        if ($from === $to) {
            if ($extra) {
                $order->update(array_filter($extra, fn ($v) => $v !== null));
            }

            return $order;
        }
        if (! $from->canTransitionTo($to)) {
            throw new BusinessException("An order cannot move from {$from->label()} to {$to->label()}.", 422, ['status' => ['Invalid status transition.']]);
        }

        DB::transaction(function () use ($order, $from, $to, $by, $comment, $extra) {
            $updates = array_filter($extra, fn ($v) => $v !== null);

            switch ($to) {
                case OrderStatus::Cancelled:
                    if ($from->holdsReservation()) {
                        $this->inventory->releaseForOrder($order, $by);
                    }
                    $updates['cancelled_at'] = now();
                    $this->releaseCoupon($order);
                    break;
                case OrderStatus::Shipped:
                    $this->inventory->shipOrder($order, $by);
                    $updates['shipped_at'] = now();
                    $updates['tracking_number'] = $updates['tracking_number'] ?? $order->tracking_number ?? 'MGX'.strtoupper(Str::random(10));
                    $updates['carrier'] = $updates['carrier'] ?? $order->carrier ?? 'BlueDart';
                    break;
                case OrderStatus::Delivered:
                    $updates['delivered_at'] = now();
                    if ($order->payment_method === PaymentMethod::CashOnDelivery) {
                        $order->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Success, 'paid_at' => now()]);
                        $updates['payment_status'] = OrderPaymentStatus::Paid;
                    }
                    break;
                case OrderStatus::Returned:
                    $this->inventory->returnOrder($order, $by);
                    break;
                default:
                    break;
            }

            $updates['status'] = $to;
            $order->update($updates);
            $order->statusHistory()->create(['status' => $to, 'comment' => $comment ?: 'Status changed to '.$to->label(), 'user_id' => $by?->id]);

            if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Refunded], true) && $order->payment_status === OrderPaymentStatus::Paid) {
                $payment = $this->payments->refund($order);
                if ($payment) {
                    $refunded = $payment->status === PaymentStatus::Refunded;
                    $order->statusHistory()->create([
                        'status' => $to,
                        'comment' => $refunded
                            ? 'Refund of ₹'.number_format((float) $payment->amount, 2).' issued to original payment method.'
                            : 'Automatic refund failed ('.($payment->meta['refund_error'] ?? 'gateway error').'). Please refund manually from the payment dashboard.',
                        'user_id' => $by?->id,
                    ]);
                }
            }
        });

        $this->audit->log('order.status_changed', $order, ['status' => $from->value], ['status' => $to->value], $by);
        OrderStatusChanged::dispatch($order, $from, $to);

        return $order->fresh();
    }

    /**
     * Mark an online (popup/webhook) payment as captured. Idempotent and safe under concurrent
     * calls: the payment row is locked, and a second confirmation is a no-op. If the order was
     * cancelled meanwhile (e.g. timeout), the money is refunded automatically.
     */
    public function completeOnlinePayment(Payment $payment, string $transactionId, array $meta = [], ?User $by = null, string $source = 'checkout'): Payment
    {
        $refundNeeded = false;
        $payment = DB::transaction(function () use ($payment, $transactionId, $meta, $by, $source, &$refundNeeded) {
            /** @var Payment $locked */
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === PaymentStatus::Success || $locked->status === PaymentStatus::Refunded) {
                return $locked;
            }
            $locked->update([
                'status' => PaymentStatus::Success,
                'transaction_id' => $transactionId,
                'failure_reason' => null,
                'paid_at' => now(),
                'meta' => array_merge($locked->meta ?? [], $meta, ['confirmed_via' => $source]),
            ]);

            /** @var Order $order */
            $order = Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $method = PaymentMethod::from($locked->method);
            $paidByAnotherAttempt = $order->payments()->whereKeyNot($locked->id)->where('status', PaymentStatus::Success)->exists();
            if ($paidByAnotherAttempt) {
                $refundNeeded = true; // duplicate payment (e.g. an old popup completed late)
            } elseif ($order->status === OrderStatus::Pending) {
                $order->update(['payment_status' => OrderPaymentStatus::Paid, 'status' => OrderStatus::Confirmed, 'payment_method' => $method]);
                $order->statusHistory()->create([
                    'status' => OrderStatus::Confirmed,
                    'comment' => 'Payment of ₹'.number_format((float) $locked->amount, 2).' received via '.$method->label().' (Txn '.$transactionId.')',
                    'user_id' => $by?->id,
                ]);
            } else {
                $order->update(['payment_status' => OrderPaymentStatus::Paid]);
                $refundNeeded = $order->status === OrderStatus::Cancelled;
            }

            return $locked;
        });

        if ($refundNeeded) {
            $order = $payment->order()->first();
            $refunded = $this->payments->refundPayment($payment->fresh(), $order);
            $order->statusHistory()->create([
                'status' => $order->status,
                'comment' => $refunded->status === PaymentStatus::Refunded
                    ? 'An extra payment of ₹'.number_format((float) $payment->amount, 2).' (Txn '.$transactionId.') was received for a cancelled or already-paid order and refunded automatically.'
                    : 'An extra payment (Txn '.$transactionId.') was received for a cancelled or already-paid order; the automatic refund failed. Please refund it manually.',
            ]);
        }

        return $payment->fresh();
    }

    /** Record a failed online attempt; the order stays pending so the customer can retry. */
    public function failOnlinePayment(Payment $payment, string $reason, array $meta = [], ?User $by = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $meta, $by) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [PaymentStatus::Initiated, PaymentStatus::Pending], true)) {
                return $locked; // already succeeded / failed / superseded
            }
            $locked->update(['status' => PaymentStatus::Failed, 'failure_reason' => substr($reason, 0, 250), 'meta' => array_merge($locked->meta ?? [], $meta)]);
            $order = $locked->order;
            if ($order->status === OrderStatus::Pending && $order->payment_status !== OrderPaymentStatus::Paid) {
                $order->update(['payment_status' => OrderPaymentStatus::Failed]);
                $order->statusHistory()->create([
                    'status' => OrderStatus::Pending,
                    'comment' => 'Payment failed: '.$reason.' You can retry payment from your order page.',
                    'user_id' => $by?->id,
                ]);
            }

            return $locked;
        });
    }

    /** Cancel orders that were never paid within the timeout, releasing their stock. */
    public function cancelStaleUnpaid(): int
    {
        $minutes = (int) $this->settings->get('unpaid_order_timeout_minutes');
        $orders = Order::where('status', OrderStatus::Pending)
            ->where('payment_method', '!=', PaymentMethod::CashOnDelivery->value)
            ->where('payment_status', '!=', OrderPaymentStatus::Paid->value)
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->get();
        foreach ($orders as $order) {
            $this->transition($order, OrderStatus::Cancelled, null, 'Automatically cancelled — payment not completed within '.$minutes.' minutes');
        }

        return $orders->count();
    }

    private function assertMethodOffered(PaymentMethod $method): void
    {
        if (! PaymentManager::isOffered($method)) {
            throw new BusinessException($method->label().' is currently unavailable. Please choose another payment method.', 422, ['payment_method' => ['This payment method is unavailable.']]);
        }
    }

    private function releaseCoupon(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }
        $deleted = CouponUsage::where('order_id', $order->id)->delete();
        if ($deleted) {
            Coupon::withTrashed()->whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
        }
    }

    private function resolveAddress(User $user, array $data, string $kind): array
    {
        $id = $data["{$kind}_address_id"] ?? null;
        if ($id) {
            $address = Address::where('user_id', $user->id)->find($id);
            if (! $address) {
                throw new BusinessException('The selected address was not found.', 422, ["{$kind}_address_id" => ['Invalid address.']]);
            }

            return $address->toSnapshot();
        }
        $inline = $data["{$kind}_address"] ?? null;
        if (! $inline) {
            throw new BusinessException('Please select a delivery address.', 422, ["{$kind}_address_id" => ['An address is required.']]);
        }
        if (! empty($data['save_address'])) {
            $user->addresses()->create($inline + ['is_default' => ! $user->addresses()->exists()]);
        }

        return collect($inline)->only(['name', 'phone', 'line1', 'line2', 'landmark', 'city', 'state', 'postal_code', 'country'])
            ->put('country', $inline['country'] ?? 'IN')->all();
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'MG'.now()->format('ymd').'-'.random_int(10000, 99999);
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}

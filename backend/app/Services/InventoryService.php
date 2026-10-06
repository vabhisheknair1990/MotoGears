<?php

namespace App\Services;

use App\Enums\InventoryTransactionType as T;
use App\Events\LowStockDetected;
use App\Exceptions\BusinessException;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * All stock movements go through here so that every change is validated and logged.
 *
 *   quantity  = units physically on the shelf
 *   reserved  = units promised to open (unshipped) orders
 *   available = quantity − reserved
 */
class InventoryService
{
    public function forProduct(Product $product): Inventory
    {
        return Inventory::firstOrCreate(['product_id' => $product->id], ['sku' => $product->sku, 'quantity' => 0, 'reserved' => 0]);
    }

    /** Lock inventory rows for the given product IDs (call inside a transaction). */
    public function lock(array $productIds): \Illuminate\Support\Collection
    {
        return Inventory::whereIn('product_id', $productIds)->lockForUpdate()->get()->keyBy('product_id');
    }

    public function reserveForOrder(Order $order, ?User $by = null): void
    {
        $order->loadMissing('items');
        $inventories = $this->lock($order->items->pluck('product_id')->filter()->all());
        foreach ($order->items as $item) {
            $inv = $inventories[$item->product_id] ?? null;
            if (! $inv || ! $inv->canFulfil($item->quantity)) {
                $available = $inv?->available() ?? 0;
                throw new BusinessException("Only {$available} unit(s) of {$item->product_name} are available.", 422, [
                    'items' => ["{$item->product_name} is out of stock or has insufficient quantity."],
                ]);
            }
            $inv->reserved += $item->quantity;
            $inv->save();
            $this->log($inv, T::OrderReserved, $item->quantity, $order, $order->order_number, 'Reserved for order', $by);
        }
    }

    public function releaseForOrder(Order $order, ?User $by = null, string $note = 'Order cancelled'): void
    {
        $order->loadMissing('items');
        $inventories = $this->lock($order->items->pluck('product_id')->filter()->all());
        foreach ($order->items as $item) {
            if (! $inv = $inventories[$item->product_id] ?? null) {
                continue;
            }
            $inv->reserved = max(0, $inv->reserved - $item->quantity);
            $inv->save();
            $this->log($inv, T::OrderCancelled, $item->quantity, $order, $order->order_number, $note, $by);
        }
    }

    public function shipOrder(Order $order, ?User $by = null): void
    {
        $order->loadMissing('items');
        $inventories = $this->lock($order->items->pluck('product_id')->filter()->all());
        foreach ($order->items as $item) {
            if (! $inv = $inventories[$item->product_id] ?? null) {
                continue;
            }
            $inv->quantity -= $item->quantity;
            $inv->reserved = max(0, $inv->reserved - $item->quantity);
            $inv->save();
            $this->log($inv, T::OrderShipped, -$item->quantity, $order, $order->order_number, 'Shipped to customer', $by);
        }
    }

    public function returnOrder(Order $order, ?User $by = null): void
    {
        $order->loadMissing('items');
        $inventories = $this->lock($order->items->pluck('product_id')->filter()->all());
        foreach ($order->items as $item) {
            if (! $inv = $inventories[$item->product_id] ?? null) {
                continue;
            }
            $inv->quantity += $item->quantity;
            $inv->save();
            $this->log($inv, T::Return, $item->quantity, $order, $order->order_number, 'Returned by customer', $by);
        }
    }

    /**
     * Admin-initiated movement. For stock_received / return the quantity must be positive;
     * manual_adjustment may be negative but can never push stock below what is reserved.
     */
    public function adjust(Inventory $inventory, T $type, int $quantity, ?string $note, ?string $reference, ?User $by): Inventory
    {
        if (! in_array($type, T::manualTypes(), true)) {
            throw new BusinessException('This movement type cannot be recorded manually.', 422, ['type' => ['Invalid movement type.']]);
        }
        if ($quantity === 0 || ($type !== T::ManualAdjustment && $quantity < 0)) {
            throw new BusinessException('Quantity must be a positive number.', 422, ['quantity' => ['Quantity must be a positive number.']]);
        }

        return DB::transaction(function () use ($inventory, $type, $quantity, $note, $reference, $by) {
            $inv = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $newQty = $inv->quantity + $quantity;
            if ($newQty < $inv->reserved) {
                throw new BusinessException("Stock cannot go below the {$inv->reserved} unit(s) reserved for open orders.", 422, [
                    'quantity' => ['Adjustment would leave less stock than is reserved.'],
                ]);
            }
            $inv->quantity = $newQty;
            $inv->save();
            $this->log($inv, $type, $quantity, null, $reference, $note, $by);

            return $inv;
        });
    }

    public function setInitialStock(Product $product, int $quantity, int $threshold = 5, bool $backorder = false, ?User $by = null): Inventory
    {
        $inv = $this->forProduct($product);
        $inv->fill(['sku' => $product->sku, 'low_stock_threshold' => $threshold, 'allow_backorder' => $backorder])->save();
        if ($quantity > 0) {
            $inv->quantity += $quantity;
            $inv->save();
            $this->log($inv, T::StockReceived, $quantity, null, 'Opening stock', 'Initial stock', $by);
        }

        return $inv;
    }

    private function log(Inventory $inv, T $type, int $qty, ?Model $reference, ?string $label, ?string $note, ?User $by): void
    {
        InventoryTransaction::create([
            'inventory_id' => $inv->id,
            'product_id' => $inv->product_id,
            'type' => $type,
            'quantity' => $qty,
            'quantity_after' => $inv->quantity,
            'reserved_after' => $inv->reserved,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reference_label' => $label,
            'note' => $note,
            'user_id' => $by?->id,
        ]);

        if (in_array($inv->status(), ['low_stock', 'out_of_stock'], true) && in_array($type, [T::OrderReserved, T::OrderShipped, T::ManualAdjustment], true)) {
            LowStockDetected::dispatch($inv);
        }
    }
}

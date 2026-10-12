<?php

namespace App\Services;

use App\Models\Order;
use App\Support\GstStates;

class InvoiceService
{
    public function __construct(private SettingsService $settings) {}

    public function data(Order $order): array
    {
        $order->loadMissing(['items', 'user', 'payment']);
        $gstin = (string) $this->settings->get('gstin');
        $sellerState = GstStates::fromGstin($gstin);
        $placeOfSupply = (string) ($order->shipping_address['state'] ?? '');
        $seller = [
            'name' => $this->settings->get('store_name'),
            'trade_name' => $this->settings->get('trade_name'),
            'legal_name' => $this->settings->get('legal_name'),
            'state' => $sellerState,
            'state_code' => $sellerState ? substr($gstin, 0, 2) : null,
            'address' => $this->settings->get('store_address'),
            'gstin' => $this->settings->get('gstin'),
            'email' => $this->settings->get('support_email'),
            'phone' => $this->settings->get('support_phone'),
        ];
        $shippingTax = round((float) $order->shipping_amount * $this->settings->float('shipping_tax_rate') / 100, 2);
        // Supply within the seller's state (from the GSTIN, e.g. 32 = Kerala) splits GST into CGST + SGST;
        // supply to any other state is IGST.
        $intraState = GstStates::same($sellerState, $placeOfSupply);

        return [
            'invoice_number' => 'INV-'.$order->order_number,
            'invoice_date' => ($order->placed_at ?? $order->created_at)->toDateString(),
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'status' => $order->status->label(),
            'payment_method' => $order->payment_method->label(),
            'payment_status' => $order->payment_status->label(),
            'transaction_id' => $order->payment?->transaction_id,
            'seller' => $seller,
            'customer' => ['name' => $order->customer_name ?? $order->user?->name, 'email' => $order->customer_email ?? $order->user?->email, 'phone' => $order->customer_phone ?? $order->user?->phone],
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->product_name,
                'sku' => $i->sku,
                'part_number' => $i->part_number,
                'hsn' => '8708',
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'discount' => (float) $i->discount,
                'taxable_value' => round((float) $i->unit_price * $i->quantity - (float) $i->discount, 2),
                'tax_rate' => (float) $i->tax_rate,
                'tax_amount' => (float) $i->tax_amount,
                'total' => (float) $i->line_total,
            ])->values(),
            'tax_split' => $intraState ? 'CGST+SGST' : 'IGST',
            'place_of_supply' => $placeOfSupply !== '' ? $placeOfSupply.(($c = GstStates::codeOf($placeOfSupply)) ? ' ('.$c.')' : '') : null,
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'shipping' => (float) $order->shipping_amount,
                'shipping_tax' => $shippingTax,
                'tax' => (float) $order->tax,
                'grand_total' => (float) $order->grand_total,
            ],
            'coupon_code' => $order->coupon_code,
            'currency' => $order->currency,
        ];
    }

    public function html(Order $order): string
    {
        return view('invoice', ['invoice' => $this->data($order)])->render();
    }
}

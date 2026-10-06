<?php

namespace App\Notifications;

use App\Models\Inventory;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    public function __construct(public Inventory $inventory) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status = $this->inventory->status() === 'out_of_stock' ? 'out of stock' : 'running low';

        return [
            'title' => 'Low stock alert',
            'message' => ($this->inventory->product?->name ?? $this->inventory->sku).' is '.$status.' ('.$this->inventory->available().' available).',
            'inventory_id' => $this->inventory->id,
            'product_id' => $this->inventory->product_id,
            'url' => '/admin/inventory/'.$this->inventory->id,
        ];
    }
}

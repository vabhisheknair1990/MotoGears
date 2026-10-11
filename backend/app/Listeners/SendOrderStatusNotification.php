<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderStatusChangedNotification;

class SendOrderStatusNotification
{
    public function handle(OrderStatusChanged $event): void
    {
        $event->order->loadMissing('user')->notifiable()?->notify(new OrderStatusChangedNotification($event->order, $event->to));
    }
}

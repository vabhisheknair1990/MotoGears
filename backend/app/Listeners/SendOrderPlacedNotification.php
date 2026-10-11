<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderPlacedNotification;

class SendOrderPlacedNotification
{
    public function handle(OrderPlaced $event): void
    {
        $event->order->loadMissing('user')->notifiable()?->notify(new OrderPlacedNotification($event->order));
    }
}

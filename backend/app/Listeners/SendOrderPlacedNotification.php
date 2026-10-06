<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderPlacedNotification;

class SendOrderPlacedNotification
{
    public function handle(OrderPlaced $event): void
    {
        $event->order->user?->notify(new OrderPlacedNotification($event->order));
    }
}

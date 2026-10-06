<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotifyStaffOfLowStock
{
    public function handle(LowStockDetected $event): void
    {
        // At most one alert per product per 6 hours.
        if (! Cache::add('low-stock-alert:'.$event->inventory->product_id, true, now()->addHours(6))) {
            return;
        }
        $staff = User::whereHas('roles.permissions', fn ($q) => $q->where('name', 'inventory.manage'))
            ->orWhereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->get();
        Notification::send($staff, new LowStockNotification($event->inventory->loadMissing('product')));
    }
}

<?php

namespace App\Events;

use App\Models\Inventory;
use Illuminate\Foundation\Events\Dispatchable;

class LowStockDetected
{
    use Dispatchable;

    public function __construct(public Inventory $inventory) {}
}

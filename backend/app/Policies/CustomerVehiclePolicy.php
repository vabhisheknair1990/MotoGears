<?php

namespace App\Policies;

use App\Models\CustomerVehicle;
use App\Models\User;

class CustomerVehiclePolicy
{
    public function update(User $user, CustomerVehicle $vehicle): bool
    {
        return $vehicle->user_id === $user->id;
    }

    public function delete(User $user, CustomerVehicle $vehicle): bool
    {
        return $vehicle->user_id === $user->id;
    }
}

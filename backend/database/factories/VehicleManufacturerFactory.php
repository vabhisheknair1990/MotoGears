<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VehicleManufacturerFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Motors';

        return ['name' => $name, 'slug' => Str::slug($name), 'vehicle_type' => 'car', 'is_active' => true];
    }
}

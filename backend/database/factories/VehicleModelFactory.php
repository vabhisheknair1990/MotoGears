<?php

namespace Database\Factories;

use App\Models\VehicleManufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VehicleModelFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return ['vehicle_manufacturer_id' => VehicleManufacturer::factory(), 'name' => $name, 'slug' => Str::slug($name), 'vehicle_type' => 'car', 'is_active' => true];
    }
}

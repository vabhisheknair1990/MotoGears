<?php

namespace Database\Factories;

use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_model_id' => VehicleModel::factory(),
            'name' => fake()->randomElement(['1.2 Petrol MT', '1.5 Diesel MT', '2.0 Petrol AT']),
            'year_from' => 2020, 'year_to' => null, 'fuel_type' => 'Petrol', 'transmission' => 'MT', 'is_active' => true,
        ];
    }
}

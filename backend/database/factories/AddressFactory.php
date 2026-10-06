<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => 'Home', 'name' => fake()->name(), 'phone' => '9876543210', 'line1' => '12 MG Road',
            'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560001', 'country' => 'IN', 'is_default' => true,
        ];
    }
}

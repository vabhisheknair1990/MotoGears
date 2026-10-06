<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'vehicle_type' => 'car', 'is_active' => true, 'sort_order' => 0];
    }
}

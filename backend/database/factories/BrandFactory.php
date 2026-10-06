<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Auto';

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'is_active' => true, 'country' => 'India'];
    }
}

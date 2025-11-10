<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SubService>
 */
class SubServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 200),
            'service_id' => Service::factory(),
        ];
    }
}


<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 500),
            'establishment_id' => Establishment::factory(),
            'user_id' => User::factory()->state(['role' => 'employee']),
        ];
    }
}


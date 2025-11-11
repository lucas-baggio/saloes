<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Scheduling>
 */
class SchedulingFactory extends Factory
{
    public function definition(): array
    {
        $dateTime = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'scheduled_date' => $dateTime->format('Y-m-d'),
            'scheduled_time' => $dateTime->format('H:i'),
            'service_id' => Service::factory(),
            'client_name' => fake()->name(),
            'status' => fake()->randomElement(['pending', 'confirmed', 'completed', 'cancelled']),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function ($scheduling) {
            if (!$scheduling->establishment_id && $scheduling->service_id) {
                $scheduling->refresh();
                $service = $scheduling->service;
                if ($service && $service->establishment_id) {
                    $scheduling->establishment_id = $service->establishment_id;
                    $scheduling->save();
                }
            }
        });
    }
}


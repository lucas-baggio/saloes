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
        ];
    }
}


<?php

namespace Tests\Feature\Api;

use App\Models\Scheduling;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchedulingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_double_book_same_slot(): void
    {
        $service = Service::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
        ];

        $first = $this->postJson('/api/schedulings', $payload);
        $first->assertCreated();

        $second = $this->postJson('/api/schedulings', $payload);
        $second->assertUnprocessable()->assertJsonValidationErrors(['scheduled_date']);
    }

    public function test_can_update_scheduling_slot(): void
    {
        $scheduling = Scheduling::factory()->create([
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '09:00',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->putJson("/api/schedulings/{$scheduling->id}", [
            'scheduled_time' => '11:30',
        ]);

        $response->assertOk()->assertJsonFragment([
            'scheduled_time' => '11:30',
        ]);

        $this->assertDatabaseHas('schedulings', [
            'id' => $scheduling->id,
            'scheduled_time' => '11:30:00',
        ]);
    }
}


<?php

namespace Tests\Feature\Api;

use App\Models\Establishment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_service_for_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $payload = [
            'name' => 'Corte Completo',
            'description' => 'Corte masculino com lavagem',
            'price' => 120.50,
            'establishment_id' => $establishment->id,
            'user_id' => $employee->id,
        ];

        $response = $this->postJson('/api/services', $payload);

        $response->assertCreated()->assertJsonFragment([
            'name' => $payload['name'],
            'price' => number_format($payload['price'], 2, '.', ''),
            'establishment_id' => $establishment->id,
            'user_id' => $employee->id,
        ]);

        $this->assertDatabaseHas('services', [
            'name' => $payload['name'],
            'establishment_id' => $establishment->id,
            'user_id' => $employee->id,
        ]);
    }

    public function test_can_filter_services_by_establishment(): void
    {
        $establishmentA = Establishment::factory()->create();
        $establishmentB = Establishment::factory()->create();

        Sanctum::actingAs($establishmentA->owner);

        Service::factory()->count(2)->for($establishmentA)->create();
        Service::factory()->count(3)->for($establishmentB)->create();

        $response = $this->getJson('/api/services?establishment_id=' . $establishmentA->id);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}


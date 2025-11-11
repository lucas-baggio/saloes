<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\SubService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_sub_services(): void
    {
        $user = User::factory()->create();
        SubService::factory()->count(5)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/sub-services');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_can_filter_sub_services_by_service(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();
        SubService::factory()->count(3)->for($service)->create();
        SubService::factory()->count(2)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sub-services?service_id={$service->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_sub_service(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Subserviço Teste',
            'description' => 'Descrição do subserviço',
            'price' => 25.50,
            'service_id' => $service->id,
        ];

        $response = $this->postJson('/api/sub-services', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => $payload['name'],
                'price' => number_format($payload['price'], 2, '.', ''),
                'service_id' => $service->id,
            ]);

        $this->assertDatabaseHas('sub_services', [
            'name' => $payload['name'],
            'service_id' => $service->id,
            'price' => 25.50,
        ]);
    }

    public function test_create_sub_service_validates_required_fields(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/sub-services', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price', 'service_id']);
    }

    public function test_create_sub_service_validates_price_range(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/sub-services', [
            'name' => 'Subserviço',
            'price' => -10.00,
            'service_id' => $service->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    public function test_create_sub_service_validates_service_exists(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/sub-services', [
            'name' => 'Subserviço',
            'price' => 25.00,
            'service_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_can_view_sub_service(): void
    {
        $user = User::factory()->create();
        $subService = SubService::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sub-services/{$subService->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'price',
                'service_id',
                'service',
            ])
            ->assertJsonFragment(['id' => $subService->id]);
    }

    public function test_sub_service_show_includes_service_relationship(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();
        $subService = SubService::factory()->for($service)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sub-services/{$subService->id}");

        $response->assertOk();
        $this->assertArrayHasKey('service', $response->json());
        $this->assertEquals($service->id, $response->json('service.id'));
    }

    public function test_can_update_sub_service(): void
    {
        $user = User::factory()->create();
        $subService = SubService::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/sub-services/{$subService->id}", [
            'name' => 'Nome Atualizado',
            'description' => 'Nova descrição',
            'price' => 30.00,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Nome Atualizado',
                'description' => 'Nova descrição',
            ]);

        $this->assertDatabaseHas('sub_services', [
            'id' => $subService->id,
            'name' => 'Nome Atualizado',
            'price' => 30.00,
        ]);
    }

    public function test_can_update_sub_service_partially(): void
    {
        $user = User::factory()->create();
        $subService = SubService::factory()->create(['name' => 'Nome Original']);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/sub-services/{$subService->id}", [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Nome Atualizado']);

        $this->assertDatabaseHas('sub_services', [
            'id' => $subService->id,
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_can_change_sub_service_service(): void
    {
        $user = User::factory()->create();
        $oldService = Service::factory()->create();
        $newService = Service::factory()->create();
        $subService = SubService::factory()->for($oldService)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/sub-services/{$subService->id}", [
            'service_id' => $newService->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('sub_services', [
            'id' => $subService->id,
            'service_id' => $newService->id,
        ]);
    }

    public function test_can_delete_sub_service(): void
    {
        $user = User::factory()->create();
        $subService = SubService::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/sub-services/{$subService->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('sub_services', [
            'id' => $subService->id,
        ]);
    }

    public function test_sub_service_list_includes_service_relationship(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();
        $subService = SubService::factory()->for($service)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/sub-services');

        $response->assertOk();
        $subServiceData = collect($response->json('data'))->firstWhere('id', $subService->id);
        $this->assertArrayHasKey('service', $subServiceData);
    }

    public function test_sub_service_list_paginates_results(): void
    {
        $user = User::factory()->create();
        SubService::factory()->count(20)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/sub-services?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
    }
}


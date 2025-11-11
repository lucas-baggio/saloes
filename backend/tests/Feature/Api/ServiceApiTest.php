<?php

namespace Tests\Feature\Api;

use App\Models\Establishment;
use App\Models\Service;
use App\Models\SubService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_services(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Service::factory()->count(5)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/services');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_owner_can_list_services_from_own_establishments(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $establishment1 = Establishment::factory()->for($owner, 'owner')->create();
        $establishment2 = Establishment::factory()->for($otherOwner, 'owner')->create();

        Service::factory()->count(3)->for($establishment1)->create();
        Service::factory()->count(2)->for($establishment2)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/services');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_employee_can_list_only_assigned_services(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $otherEmployee = User::factory()->create(['role' => 'employee']);

        Service::factory()->count(3)->create(['user_id' => $employee->id]);
        Service::factory()->count(2)->create(['user_id' => $otherEmployee->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/services');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_service_for_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        // Associar employee ao estabelecimento
        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'establishment_id' => $establishment->id,
            'user_id' => $employee->id,
        ]);

        $this->assertDatabaseHas('services', [
            'name' => $payload['name'],
            'establishment_id' => $establishment->id,
            'user_id' => $employee->id,
        ]);
    }

    public function test_can_create_service_with_sub_services(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $payload = [
            'name' => 'Pacote Completo',
            'description' => 'Pacote com múltiplos serviços',
            'establishment_id' => $establishment->id,
            'sub_services' => [
                [
                    'name' => 'Corte',
                    'description' => 'Corte de cabelo',
                    'price' => 50.00,
                ],
                [
                    'name' => 'Barba',
                    'description' => 'Aparar barba',
                    'price' => 30.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/services', $payload);

        $response->assertCreated();

        $service = Service::where('name', 'Pacote Completo')->first();
        $this->assertNotNull($service);
        $this->assertEquals(80.00, $service->price); // Soma dos subserviços

        $this->assertDatabaseHas('sub_services', [
            'service_id' => $service->id,
            'name' => 'Corte',
            'price' => 50.00,
        ]);

        $this->assertDatabaseHas('sub_services', [
            'service_id' => $service->id,
            'name' => 'Barba',
            'price' => 30.00,
        ]);
    }

    public function test_cannot_create_service_without_price_or_sub_services(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $payload = [
            'name' => 'Serviço sem preço',
            'establishment_id' => $establishment->id,
        ];

        $response = $this->postJson('/api/services', $payload);

        $response->assertUnprocessable()
            ->assertJson(['message' => 'É necessário fornecer um preço ou criar subserviços.']);
    }

    public function test_employee_cannot_create_service(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/services', [
            'name' => 'Serviço',
            'price' => 100.00,
            'establishment_id' => 1,
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Não autorizado. Funcionários não podem criar serviços.']);
    }

    public function test_owner_cannot_create_service_for_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/services', [
            'name' => 'Serviço',
            'price' => 100.00,
            'establishment_id' => $establishment->id,
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Não autorizado. Estabelecimento não pertence a você.']);
    }

    public function test_cannot_assign_employee_not_in_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/services', [
            'name' => 'Serviço',
            'price' => 100.00,
            'establishment_id' => $establishment->id,
            'user_id' => $employee->id,
        ]);

        $response->assertUnprocessable()
            ->assertJson(['message' => 'O funcionário selecionado não trabalha neste estabelecimento.']);
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

    public function test_admin_can_filter_services_by_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);

        Service::factory()->count(3)->create(['user_id' => $employee->id]);
        Service::factory()->count(2)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/services?user_id={$employee->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_view_any_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'price',
                'establishment',
                'user',
                'subServices',
                'schedulings',
            ]);
    }

    public function test_owner_can_view_service_from_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $service->id]);
    }

    public function test_owner_cannot_view_service_from_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertForbidden();
    }

    public function test_employee_can_view_assigned_service(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $service = Service::factory()->create(['user_id' => $employee->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $service->id]);
    }

    public function test_employee_cannot_view_unassigned_service(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $service = Service::factory()->create();

        Sanctum::actingAs($employee);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_update_any_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/services/{$service->id}", [
            'name' => 'Nome Atualizado',
            'price' => 150.00,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Nome Atualizado']);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Nome Atualizado',
            'price' => 150.00,
        ]);
    }

    public function test_owner_can_update_service_from_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/services/{$service->id}", [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertOk();
    }

    public function test_owner_cannot_update_service_from_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/services/{$service->id}", [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertForbidden();
    }

    public function test_employee_can_update_assigned_service(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $service = Service::factory()->create(['user_id' => $employee->id]);

        Sanctum::actingAs($employee);

        $response = $this->putJson("/api/services/{$service->id}", [
            'description' => 'Nova descrição',
        ]);

        $response->assertOk();
    }

    public function test_employee_cannot_update_unassigned_service(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $service = Service::factory()->create();

        Sanctum::actingAs($employee);

        $response = $this->putJson("/api/services/{$service->id}", [
            'description' => 'Nova descrição',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_any_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/services/{$service->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
        ]);
    }

    public function test_owner_can_delete_service_from_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/services/{$service->id}");

        $response->assertNoContent();
    }

    public function test_owner_cannot_delete_service_from_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/services/{$service->id}");

        $response->assertForbidden();
    }

    public function test_employee_can_delete_assigned_service(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $service = Service::factory()->create(['user_id' => $employee->id]);

        Sanctum::actingAs($employee);

        $response = $this->deleteJson("/api/services/{$service->id}");

        $response->assertNoContent();
    }

    public function test_service_list_includes_relationships(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/services');

        $response->assertOk();
        $serviceData = collect($response->json('data'))->firstWhere('id', $service->id);
        $this->assertArrayHasKey('establishment', $serviceData);
        $this->assertArrayHasKey('user', $serviceData);
        $this->assertArrayHasKey('subServices', $serviceData);
    }

    public function test_service_show_includes_schedulings(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertOk();
        $this->assertArrayHasKey('schedulings', $response->json());
    }
}



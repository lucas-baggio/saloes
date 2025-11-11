<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Establishment;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EstablishmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_establishments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Establishment::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/establishments');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_owner_can_list_only_own_establishments(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);

        Establishment::factory()->count(2)->for($owner, 'owner')->create();
        Establishment::factory()->count(3)->for($otherOwner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/establishments');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_employee_cannot_list_establishments(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/establishments');

        $response->assertForbidden()
            ->assertJson(['message' => 'Não autorizado.']);
    }

    public function test_admin_can_filter_establishments_by_owner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        Establishment::factory()->count(2)->for($owner, 'owner')->create();
        Establishment::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/establishments?owner_id={$owner->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_establishment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/establishments', [
            'name' => 'Salão Novo',
            'description' => 'Descrição do salão',
            'owner_id' => $owner->id,
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Salão Novo',
                'description' => 'Descrição do salão',
            ]);

        $this->assertDatabaseHas('establishments', [
            'name' => 'Salão Novo',
            'owner_id' => $owner->id,
        ]);
    }

    public function test_owner_can_create_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/establishments', [
            'name' => 'Meu Salão',
            'description' => 'Descrição',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Meu Salão']);

        $this->assertDatabaseHas('establishments', [
            'name' => 'Meu Salão',
            'owner_id' => $owner->id,
        ]);
    }

    public function test_employee_cannot_create_establishment(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/establishments', [
            'name' => 'Salão',
            'description' => 'Descrição',
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Não autorizado. Funcionários não podem criar estabelecimentos.']);
    }

    public function test_create_establishment_validates_required_fields(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/establishments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_view_any_establishment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment = Establishment::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/establishments/{$establishment->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $establishment->id]);
    }

    public function test_owner_can_view_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/establishments/{$establishment->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $establishment->id])
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'owner',
                'services',
            ]);
    }

    public function test_owner_cannot_view_other_owner_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/establishments/{$establishment->id}");

        $response->assertForbidden()
            ->assertJson(['message' => 'Não autorizado.']);
    }

    public function test_establishment_show_includes_services(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        Service::factory()->count(3)->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/establishments/{$establishment->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('services'));
    }

    public function test_admin_can_update_any_establishment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment = Establishment::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/establishments/{$establishment->id}", [
            'name' => 'Nome Atualizado',
            'description' => 'Nova descrição',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Nome Atualizado']);

        $this->assertDatabaseHas('establishments', [
            'id' => $establishment->id,
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_owner_can_update_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/establishments/{$establishment->id}", [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Nome Atualizado']);
    }

    public function test_owner_cannot_update_other_owner_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/establishments/{$establishment->id}", [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_change_establishment_owner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $oldOwner = User::factory()->create(['role' => 'owner']);
        $newOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($oldOwner, 'owner')->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/establishments/{$establishment->id}", [
            'owner_id' => $newOwner->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('establishments', [
            'id' => $establishment->id,
            'owner_id' => $newOwner->id,
        ]);
    }

    public function test_owner_cannot_change_establishment_owner(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $newOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/establishments/{$establishment->id}", [
            'owner_id' => $newOwner->id,
        ]);

        $response->assertOk();

        // Owner não deve mudar
        $this->assertDatabaseHas('establishments', [
            'id' => $establishment->id,
            'owner_id' => $owner->id,
        ]);
    }

    public function test_admin_can_delete_any_establishment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment = Establishment::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/establishments/{$establishment->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('establishments', [
            'id' => $establishment->id,
        ]);
    }

    public function test_owner_can_delete_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/establishments/{$establishment->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('establishments', [
            'id' => $establishment->id,
        ]);
    }

    public function test_owner_cannot_delete_other_owner_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/establishments/{$establishment->id}");

        $response->assertForbidden();
    }

    public function test_establishment_list_includes_services_count(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        Service::factory()->count(5)->for($establishment)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/establishments');

        $response->assertOk();
        $establishmentData = collect($response->json('data'))->firstWhere('id', $establishment->id);
        $this->assertEquals(5, $establishmentData['services_count']);
    }

    public function test_establishment_list_paginates_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Establishment::factory()->count(20)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/establishments?per_page=10');

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


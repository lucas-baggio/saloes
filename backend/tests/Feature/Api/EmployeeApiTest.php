<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Establishment;
use App\Models\Service;
use App\Models\Scheduling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_employees(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $employee1 = User::factory()->create(['role' => 'employee']);
        $employee2 = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            ['user_id' => $employee1->id, 'establishment_id' => $establishment->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $employee2->id, 'establishment_id' => $establishment->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/employees');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_list_employees(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $employee = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/employees');

        $response->assertOk();
    }

    public function test_employee_cannot_list_employees(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/employees');

        $response->assertForbidden();
    }

    public function test_owner_can_create_employee(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $payload = [
            'name' => 'Novo Funcionário',
            'email' => 'employee@example.com',
            'password' => 'password123',
            'establishment_id' => $establishment->id,
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'role' => 'employee',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'role' => 'employee',
        ]);

        $employee = User::where('email', $payload['email'])->first();
        $this->assertTrue($employee->establishments->contains($establishment));
    }

    public function test_owner_cannot_create_employee_for_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/employees', [
            'name' => 'Funcionário',
            'email' => 'employee@example.com',
            'password' => 'password123',
            'establishment_id' => $establishment->id,
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Estabelecimento não pertence a você.']);
    }

    public function test_employee_list_includes_statistics(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $employee = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = Service::factory()->create([
            'establishment_id' => $establishment->id,
            'user_id' => $employee->id,
            'price' => 100.00,
        ]);

        Scheduling::factory()->count(3)->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/employees');

        $response->assertOk();
        $employeeData = collect($response->json('data'))->firstWhere('id', $employee->id);
        $this->assertArrayHasKey('services_count', $employeeData);
        $this->assertArrayHasKey('revenue', $employeeData);
        $this->assertArrayHasKey('schedulings_count', $employeeData);
        $this->assertEquals(300.00, $employeeData['revenue']);
        $this->assertEquals(3, $employeeData['schedulings_count']);
    }

    public function test_owner_can_view_employee(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $employee = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $employee->id])
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'role',
                'services_count',
                'revenue',
                'schedulings_count',
            ]);
    }

    public function test_owner_cannot_view_employee_from_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();
        $employee = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/employees/{$employee->id}");

        $response->assertNotFound();
    }

    public function test_owner_can_update_employee(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $employee = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => 'Nome Atualizado',
            'email' => 'novoemail@example.com',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Nome Atualizado',
                'email' => 'novoemail@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_owner_can_update_employee_password(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $employee = User::factory()->create([
            'role' => 'employee',
            'password' => Hash::make('oldpassword'),
        ]);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'password' => 'newpassword123',
        ]);

        $response->assertOk();

        $employee->refresh();
        $this->assertTrue(Hash::check('newpassword123', $employee->password));
    }

    public function test_owner_can_delete_employee(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $employee = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('users', [
            'id' => $employee->id,
        ]);
    }

    public function test_employee_list_returns_empty_when_no_establishments(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/employees');

        $response->assertOk()
            ->assertJson(['data' => [], 'total' => 0]);
    }

    public function test_employee_list_returns_empty_when_no_employees(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Establishment::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/employees');

        $response->assertOk()
            ->assertJson(['data' => [], 'total' => 0]);
    }

    public function test_employee_list_sorted_by_revenue(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        $employee1 = User::factory()->create(['role' => 'employee']);
        $employee2 = User::factory()->create(['role' => 'employee']);

        DB::table('employee_establishment')->insert([
            ['user_id' => $employee1->id, 'establishment_id' => $establishment->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $employee2->id, 'establishment_id' => $establishment->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service1 = Service::factory()->create([
            'establishment_id' => $establishment->id,
            'user_id' => $employee1->id,
            'price' => 50.00,
        ]);

        $service2 = Service::factory()->create([
            'establishment_id' => $establishment->id,
            'user_id' => $employee2->id,
            'price' => 100.00,
        ]);

        Scheduling::factory()->create([
            'service_id' => $service1->id,
            'establishment_id' => $establishment->id,
        ]);

        Scheduling::factory()->create([
            'service_id' => $service2->id,
            'establishment_id' => $establishment->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/employees');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($employee2->id, $data[0]['id']); // Maior receita primeiro
    }
}


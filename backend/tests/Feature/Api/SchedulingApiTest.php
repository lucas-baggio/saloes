<?php

namespace Tests\Feature\Api;

use App\Models\Scheduling;
use App\Models\Service;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SchedulingConfirmationNotification;
use App\Notifications\StatusChangeNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchedulingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_schedulings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Scheduling::factory()->count(5)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/schedulings');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_owner_can_list_schedulings_from_own_establishments(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $establishment1 = Establishment::factory()->for($owner, 'owner')->create();
        $establishment2 = Establishment::factory()->for($otherOwner, 'owner')->create();

        $service1 = Service::factory()->for($establishment1)->create();
        $service2 = Service::factory()->for($establishment2)->create();

        Scheduling::factory()->count(3)->create([
            'service_id' => $service1->id,
            'establishment_id' => $establishment1->id,
        ]);
        Scheduling::factory()->count(2)->create([
            'service_id' => $service2->id,
            'establishment_id' => $establishment2->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/schedulings');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_employee_can_list_schedulings_from_own_establishments(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee']);

        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = Service::factory()->for($establishment)->create();
        Scheduling::factory()->count(3)->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/schedulings');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_schedulings_by_establishment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment1 = Establishment::factory()->create();
        $establishment2 = Establishment::factory()->create();

        $service1 = Service::factory()->for($establishment1)->create();
        $service2 = Service::factory()->for($establishment2)->create();

        Scheduling::factory()->count(2)->create([
            'service_id' => $service1->id,
            'establishment_id' => $establishment1->id,
        ]);
        Scheduling::factory()->count(3)->create([
            'service_id' => $service2->id,
            'establishment_id' => $establishment2->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/schedulings?establishment_id={$establishment1->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_schedulings_by_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();

        Scheduling::factory()->create([
            'service_id' => $service->id,
            'scheduled_date' => '2024-01-15',
        ]);
        Scheduling::factory()->create([
            'service_id' => $service->id,
            'scheduled_date' => '2024-01-20',
        ]);
        Scheduling::factory()->create([
            'service_id' => $service->id,
            'scheduled_date' => '2024-02-01',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/schedulings?from=2024-01-15&to=2024-01-25');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_scheduling(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment = Establishment::factory()->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($admin);

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente Teste',
            'status' => 'pending',
        ];

        $response = $this->postJson('/api/schedulings', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'client_name' => 'Cliente Teste',
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('schedulings', [
            'client_name' => 'Cliente Teste',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
        ]);

        if ($establishment->owner_id) {
            Notification::assertSentTo(
                $establishment->owner,
                SchedulingConfirmationNotification::class
            );
        }
    }

    public function test_owner_can_create_scheduling_for_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente Teste',
        ];

        $response = $this->postJson('/api/schedulings', $payload);

        $response->assertCreated();
    }

    public function test_owner_cannot_create_scheduling_for_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($owner);

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente Teste',
        ];

        $response = $this->postJson('/api/schedulings', $payload);

        $response->assertForbidden();
    }

    public function test_employee_can_create_scheduling_for_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $employee = User::factory()->create(['role' => 'employee']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        DB::table('employee_establishment')->insert([
            'user_id' => $employee->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($employee);

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente Teste',
        ];

        $response = $this->postJson('/api/schedulings', $payload);

        $response->assertCreated();
    }

    public function test_employee_cannot_create_scheduling_for_unassociated_establishment(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $establishment = Establishment::factory()->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($employee);

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente Teste',
        ];

        $response = $this->postJson('/api/schedulings', $payload);

        $response->assertForbidden();
    }

    public function test_cannot_create_scheduling_with_service_from_different_establishment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment1 = Establishment::factory()->create();
        $establishment2 = Establishment::factory()->create();
        $service = Service::factory()->for($establishment1)->create();

        Sanctum::actingAs($admin);

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment2->id,
            'client_name' => 'Cliente Teste',
        ];

        $response = $this->postJson('/api/schedulings', $payload);

        $response->assertUnprocessable()
            ->assertJson(['message' => 'O serviço selecionado não pertence ao estabelecimento informado.']);
    }

    public function test_cannot_double_book_same_slot(): void
    {
        $service = Service::factory()->create();
        $establishment = $service->establishment;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente 1',
        ];

        $first = $this->postJson('/api/schedulings', $payload);
        $first->assertCreated();

        $payload['client_name'] = 'Cliente 2';
        $second = $this->postJson('/api/schedulings', $payload);
        $second->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_time']);
    }

    public function test_can_create_scheduling_with_cancelled_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment = Establishment::factory()->create();
        $service = Service::factory()->for($establishment)->create();

        Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($admin);

        $payload = [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente Novo',
        ];

        $response = $this->postJson('/api/schedulings', $payload);

        // Deve permitir porque o anterior está cancelado
        $response->assertCreated();
    }

    public function test_create_scheduling_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->postJson('/api/schedulings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'scheduled_date',
                'scheduled_time',
                'service_id',
                'establishment_id',
                'client_name',
            ]);
    }

    public function test_create_scheduling_validates_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $establishment = Establishment::factory()->create();
        $service = Service::factory()->for($establishment)->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/schedulings', [
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '10:00',
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'client_name' => 'Cliente',
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_can_view_any_scheduling(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $scheduling = Scheduling::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/schedulings/{$scheduling->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $scheduling->id]);
    }

    public function test_owner_can_view_scheduling_from_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();
        $scheduling = Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/schedulings/{$scheduling->id}");

        $response->assertOk();
    }

    public function test_owner_cannot_view_scheduling_from_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();
        $scheduling = Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/schedulings/{$scheduling->id}");

        $response->assertForbidden();
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
            'scheduled_time' => '11:30',
        ]);
    }

    public function test_status_change_sends_notification(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();
        $scheduling = Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->putJson("/api/schedulings/{$scheduling->id}", [
            'status' => 'confirmed',
        ]);

        $response->assertOk();

        Notification::assertSentTo(
            $owner,
            StatusChangeNotification::class
        );
    }

    public function test_admin_can_delete_any_scheduling(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $scheduling = Scheduling::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/schedulings/{$scheduling->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('schedulings', [
            'id' => $scheduling->id,
        ]);
    }

    public function test_owner_can_delete_scheduling_from_own_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();
        $scheduling = Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/schedulings/{$scheduling->id}");

        $response->assertNoContent();
    }

    public function test_owner_cannot_delete_scheduling_from_other_establishment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($otherOwner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create();
        $scheduling = Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/schedulings/{$scheduling->id}");

        $response->assertForbidden();
    }

    public function test_scheduling_list_includes_relationships(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $scheduling = Scheduling::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/schedulings');

        $response->assertOk();
        $schedulingData = collect($response->json('data'))->firstWhere('id', $scheduling->id);
        $this->assertArrayHasKey('service', $schedulingData);
        $this->assertArrayHasKey('establishment', $schedulingData);
    }

    public function test_scheduling_list_is_ordered_by_date_and_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();
        $establishment = $service->establishment;

        Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => '2030-01-02',
            'scheduled_time' => '10:00',
        ]);
        Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '15:00',
        ]);
        Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => '2030-01-01',
            'scheduled_time' => '09:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/schedulings');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('2030-01-01', $data[0]['scheduled_date']);
        $this->assertEquals('09:00', $data[0]['scheduled_time']);
        $this->assertEquals('2030-01-02', $data[2]['scheduled_date']);
    }
}



<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Establishment;
use App\Models\Service;
use App\Models\Scheduling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_stats(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Establishment::factory()->count(3)->create();
        Service::factory()->count(5)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'start_date',
                'end_date',
                'establishments',
                'services',
                'schedulings' => ['total', 'growth', 'previous'],
                'revenue' => ['total', 'growth', 'previous'],
                'average_ticket',
            ]);
    }

    public function test_owner_can_get_stats_for_own_establishments(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $establishment1 = Establishment::factory()->for($owner, 'owner')->create();
        $establishment2 = Establishment::factory()->for($otherOwner, 'owner')->create();

        Service::factory()->count(3)->for($establishment1)->create();
        Service::factory()->count(2)->for($establishment2)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk();
        $this->assertEquals(1, $response->json('establishments'));
        $this->assertEquals(3, $response->json('services'));
    }

    public function test_employee_can_get_stats_for_assigned_services(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        Service::factory()->count(3)->create(['user_id' => $employee->id]);
        Service::factory()->count(2)->create();

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk();
        $this->assertEquals(3, $response->json('services'));
    }

    public function test_stats_calculates_revenue_correctly(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service1 = Service::factory()->for($establishment)->create(['price' => 100.00]);
        $service2 = Service::factory()->for($establishment)->create(['price' => 50.00]);

        Scheduling::factory()->create([
            'service_id' => $service1->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);
        Scheduling::factory()->create([
            'service_id' => $service2->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/stats?period=month');

        $response->assertOk();
        $this->assertEquals(150.00, $response->json('revenue.total'));
        $this->assertEquals(2, $response->json('schedulings.total'));
        $this->assertEquals(75.00, $response->json('average_ticket'));
    }

    public function test_stats_calculates_growth(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create(['price' => 100.00]);

        // Agendamentos do mês anterior
        Scheduling::factory()->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now()->subMonth(),
        ]);

        // Agendamentos do mês atual
        Scheduling::factory()->count(2)->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/stats?period=month');

        $response->assertOk();
        $this->assertEquals(2, $response->json('schedulings.total'));
        $this->assertEquals(1, $response->json('schedulings.previous'));
        $this->assertGreaterThan(0, $response->json('schedulings.growth'));
    }

    public function test_stats_supports_different_periods(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $periods = ['day', 'week', 'month', 'year'];

        foreach ($periods as $period) {
            $response = $this->getJson("/api/dashboard/stats?period={$period}");

            $response->assertOk()
                ->assertJson(['period' => $period]);
        }
    }

    public function test_revenue_chart_returns_data(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create(['price' => 100.00]);

        Scheduling::factory()->count(3)->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/revenue-chart?period=month');

        $response->assertOk()
            ->assertJsonStructure([
                'labels',
                'revenue',
                'count',
            ]);

        $this->assertIsArray($response->json('labels'));
        $this->assertIsArray($response->json('revenue'));
        $this->assertIsArray($response->json('count'));
    }

    public function test_revenue_chart_groups_by_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create(['price' => 100.00]);

        Scheduling::factory()->create([
            'service_id' => $service->id,
            'scheduled_date' => now()->subDays(2),
        ]);
        Scheduling::factory()->create([
            'service_id' => $service->id,
            'scheduled_date' => now()->subDays(1),
        ]);
        Scheduling::factory()->create([
            'service_id' => $service->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/dashboard/revenue-chart?period=week');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('labels')));
    }

    public function test_revenue_chart_respects_user_permissions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $establishment1 = Establishment::factory()->for($owner, 'owner')->create();
        $establishment2 = Establishment::factory()->for($otherOwner, 'owner')->create();

        $service1 = Service::factory()->for($establishment1)->create(['price' => 100.00]);
        $service2 = Service::factory()->for($establishment2)->create(['price' => 200.00]);

        Scheduling::factory()->create([
            'service_id' => $service1->id,
            'establishment_id' => $establishment1->id,
            'scheduled_date' => now(),
        ]);
        Scheduling::factory()->create([
            'service_id' => $service2->id,
            'establishment_id' => $establishment2->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/revenue-chart?period=month');

        $response->assertOk();
        // Deve incluir apenas receita do estabelecimento do owner
        $totalRevenue = array_sum($response->json('revenue'));
        $this->assertEquals(100.00, $totalRevenue);
    }

    public function test_top_services_returns_ordered_list(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();

        $service1 = Service::factory()->for($establishment)->create(['price' => 50.00]);
        $service2 = Service::factory()->for($establishment)->create(['price' => 100.00]);
        $service3 = Service::factory()->for($establishment)->create(['price' => 75.00]);

        Scheduling::factory()->count(5)->create([
            'service_id' => $service1->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);
        Scheduling::factory()->count(3)->create([
            'service_id' => $service2->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);
        Scheduling::factory()->count(1)->create([
            'service_id' => $service3->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/top-services?limit=5&period=month');

        $response->assertOk();
        $services = $response->json();
        $this->assertCount(3, $services);
        $this->assertEquals($service1->id, $services[0]['id']); // Mais agendamentos
        $this->assertEquals(5, $services[0]['schedulings']);
    }

    public function test_top_services_respects_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();

        Scheduling::factory()->count(10)->create([
            'service_id' => $service->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/dashboard/top-services?limit=3');

        $response->assertOk();
        $this->assertLessThanOrEqual(3, count($response->json()));
    }

    public function test_top_services_includes_revenue(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create(['price' => 100.00]);

        Scheduling::factory()->count(3)->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/top-services');

        $response->assertOk();
        $serviceData = collect($response->json())->firstWhere('id', $service->id);
        $this->assertNotNull($serviceData);
        $this->assertEquals(300.00, $serviceData['revenue']);
        $this->assertEquals(3, $serviceData['schedulings']);
    }

    public function test_top_services_respects_user_permissions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $establishment1 = Establishment::factory()->for($owner, 'owner')->create();
        $establishment2 = Establishment::factory()->for($otherOwner, 'owner')->create();

        $service1 = Service::factory()->for($establishment1)->create();
        $service2 = Service::factory()->for($establishment2)->create();

        Scheduling::factory()->count(5)->create([
            'service_id' => $service1->id,
            'establishment_id' => $establishment1->id,
            'scheduled_date' => now(),
        ]);
        Scheduling::factory()->count(10)->create([
            'service_id' => $service2->id,
            'establishment_id' => $establishment2->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/top-services');

        $response->assertOk();
        $serviceIds = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($service1->id, $serviceIds);
        $this->assertNotContains($service2->id, $serviceIds);
    }

    public function test_employee_can_get_top_services_for_assigned_services(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $service1 = Service::factory()->create(['user_id' => $employee->id]);
        $service2 = Service::factory()->create();

        Scheduling::factory()->count(3)->create([
            'service_id' => $service1->id,
            'scheduled_date' => now(),
        ]);
        Scheduling::factory()->count(5)->create([
            'service_id' => $service2->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/dashboard/top-services');

        $response->assertOk();
        $serviceIds = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($service1->id, $serviceIds);
        $this->assertNotContains($service2->id, $serviceIds);
    }

    public function test_average_ticket_calculation(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $establishment = Establishment::factory()->for($owner, 'owner')->create();
        $service = Service::factory()->for($establishment)->create(['price' => 100.00]);

        Scheduling::factory()->count(4)->create([
            'service_id' => $service->id,
            'establishment_id' => $establishment->id,
            'scheduled_date' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/stats?period=month');

        $response->assertOk();
        $this->assertEquals(100.00, $response->json('average_ticket'));
    }

    public function test_average_ticket_is_zero_when_no_schedulings(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/dashboard/stats?period=month');

        $response->assertOk();
        $this->assertEquals(0, $response->json('average_ticket'));
    }
}


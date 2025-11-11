<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\User;
use App\Models\Plan;
use App\Models\UserPlan;
use App\Models\Establishment;
use App\Models\Service;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\DB;

class PlanLimitsTest extends BaseTestCase
{
    use CreatesApplication;

    protected $planLimitService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planLimitService = new PlanLimitService();
    }

    /**
     * Testa os limites de estabelecimentos
     */
    public function test_establishment_limits()
    {
        // Cria um plano básico com limite de 1 estabelecimento
        $plan = Plan::create([
            'name' => 'Plano Básico Teste',
            'description' => 'Plano para teste',
            'price' => 29.90,
            'interval' => 'monthly',
            'max_establishments' => 1,
            'max_services' => 10,
            'max_employees' => 3,
            'is_active' => true,
        ]);

        // Cria um usuário owner
        $user = User::create([
            'name' => 'Owner Teste',
            'email' => 'owner@teste.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        // Ativa o plano
        UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Verifica se pode criar o primeiro estabelecimento
        $check = $this->planLimitService->canCreateEstablishment($user);
        $this->assertTrue($check['allowed'], 'Deveria permitir criar o primeiro estabelecimento');

        // Cria o primeiro estabelecimento
        Establishment::create([
            'name' => 'Estabelecimento 1',
            'description' => 'Descrição',
            'owner_id' => $user->id,
        ]);

        // Verifica se NÃO pode criar o segundo estabelecimento
        $check = $this->planLimitService->canCreateEstablishment($user);
        $this->assertFalse($check['allowed'], 'NÃO deveria permitir criar o segundo estabelecimento');
        $this->assertEquals(1, $check['current'], 'Deve ter 1 estabelecimento');
        $this->assertEquals(1, $check['limit'], 'O limite deve ser 1');

        // Limpa
        Establishment::where('owner_id', $user->id)->delete();
        UserPlan::where('user_id', $user->id)->delete();
        Plan::where('id', $plan->id)->delete();
        User::where('id', $user->id)->delete();
    }

    /**
     * Testa os limites de serviços
     */
    public function test_service_limits()
    {
        // Cria um plano básico com limite de 2 serviços
        $plan = Plan::create([
            'name' => 'Plano Básico Teste',
            'description' => 'Plano para teste',
            'price' => 29.90,
            'interval' => 'monthly',
            'max_establishments' => 1,
            'max_services' => 2,
            'max_employees' => 3,
            'is_active' => true,
        ]);

        // Cria um usuário owner
        $user = User::create([
            'name' => 'Owner Teste',
            'email' => 'owner2@teste.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        // Ativa o plano
        UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Cria um estabelecimento
        $establishment = Establishment::create([
            'name' => 'Estabelecimento Teste',
            'description' => 'Descrição',
            'owner_id' => $user->id,
        ]);

        // Verifica se pode criar o primeiro serviço
        $check = $this->planLimitService->canCreateService($user);
        $this->assertTrue($check['allowed'], 'Deveria permitir criar o primeiro serviço');

        // Cria os dois primeiros serviços
        Service::create([
            'name' => 'Serviço 1',
            'description' => 'Descrição',
            'price' => 50.00,
            'establishment_id' => $establishment->id,
        ]);

        Service::create([
            'name' => 'Serviço 2',
            'description' => 'Descrição',
            'price' => 50.00,
            'establishment_id' => $establishment->id,
        ]);

        // Verifica se NÃO pode criar o terceiro serviço
        $check = $this->planLimitService->canCreateService($user);
        $this->assertFalse($check['allowed'], 'NÃO deveria permitir criar o terceiro serviço');
        $this->assertEquals(2, $check['current'], 'Deve ter 2 serviços');
        $this->assertEquals(2, $check['limit'], 'O limite deve ser 2');

        // Limpa
        Service::where('establishment_id', $establishment->id)->delete();
        Establishment::where('id', $establishment->id)->delete();
        UserPlan::where('user_id', $user->id)->delete();
        Plan::where('id', $plan->id)->delete();
        User::where('id', $user->id)->delete();
    }

    /**
     * Testa os limites de funcionários
     */
    public function test_employee_limits()
    {
        // Cria um plano básico com limite de 2 funcionários
        $plan = Plan::create([
            'name' => 'Plano Básico Teste',
            'description' => 'Plano para teste',
            'price' => 29.90,
            'interval' => 'monthly',
            'max_establishments' => 1,
            'max_services' => 10,
            'max_employees' => 2,
            'is_active' => true,
        ]);

        // Cria um usuário owner
        $user = User::create([
            'name' => 'Owner Teste',
            'email' => 'owner3@teste.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        // Ativa o plano
        UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Cria um estabelecimento
        $establishment = Establishment::create([
            'name' => 'Estabelecimento Teste',
            'description' => 'Descrição',
            'owner_id' => $user->id,
        ]);

        // Verifica se pode adicionar o primeiro funcionário
        $check = $this->planLimitService->canAddEmployee($user);
        $this->assertTrue($check['allowed'], 'Deveria permitir adicionar o primeiro funcionário');

        // Cria os dois primeiros funcionários
        $employee1 = User::create([
            'name' => 'Funcionário 1',
            'email' => 'employee1@teste.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $employee2 = User::create([
            'name' => 'Funcionário 2',
            'email' => 'employee2@teste.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        // Associa os funcionários ao estabelecimento
        DB::table('employee_establishment')->insert([
            'user_id' => $employee1->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_establishment')->insert([
            'user_id' => $employee2->id,
            'establishment_id' => $establishment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verifica se NÃO pode adicionar o terceiro funcionário
        $check = $this->planLimitService->canAddEmployee($user);
        $this->assertFalse($check['allowed'], 'NÃO deveria permitir adicionar o terceiro funcionário');
        $this->assertEquals(2, $check['current'], 'Deve ter 2 funcionários');
        $this->assertEquals(2, $check['limit'], 'O limite deve ser 2');

        // Limpa
        DB::table('employee_establishment')->where('establishment_id', $establishment->id)->delete();
        User::where('id', $employee1->id)->delete();
        User::where('id', $employee2->id)->delete();
        Establishment::where('id', $establishment->id)->delete();
        UserPlan::where('user_id', $user->id)->delete();
        Plan::where('id', $plan->id)->delete();
        User::where('id', $user->id)->delete();
    }

    /**
     * Testa plano ilimitado (null)
     */
    public function test_unlimited_plan()
    {
        // Cria um plano premium com limites ilimitados
        $plan = Plan::create([
            'name' => 'Plano Premium Teste',
            'description' => 'Plano para teste',
            'price' => 99.90,
            'interval' => 'monthly',
            'max_establishments' => null, // Ilimitado
            'max_services' => null, // Ilimitado
            'max_employees' => null, // Ilimitado
            'is_active' => true,
        ]);

        // Cria um usuário owner
        $user = User::create([
            'name' => 'Owner Premium',
            'email' => 'owner-premium@teste.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        // Ativa o plano
        UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Verifica se pode criar estabelecimentos ilimitados
        $check = $this->planLimitService->canCreateEstablishment($user);
        $this->assertTrue($check['allowed'], 'Plano ilimitado deve permitir criar estabelecimentos');

        // Verifica se pode criar serviços ilimitados
        $check = $this->planLimitService->canCreateService($user);
        $this->assertTrue($check['allowed'], 'Plano ilimitado deve permitir criar serviços');

        // Verifica se pode adicionar funcionários ilimitados
        $check = $this->planLimitService->canAddEmployee($user);
        $this->assertTrue($check['allowed'], 'Plano ilimitado deve permitir adicionar funcionários');

        // Limpa
        UserPlan::where('user_id', $user->id)->delete();
        Plan::where('id', $plan->id)->delete();
        User::where('id', $user->id)->delete();
    }
}


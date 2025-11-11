<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Plan;
use App\Models\UserPlan;
use App\Models\Establishment;
use App\Models\Service;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Controller para testar os limites de planos
 * ATENÇÃO: Este controller é apenas para testes e deve ser removido em produção
 */
class PlanLimitTestController extends Controller
{
    protected $planLimitService;

    public function __construct(PlanLimitService $planLimitService)
    {
        $this->planLimitService = $planLimitService;
    }

    /**
     * Testa os limites de um usuário
     * GET /api/test/plan-limits/{userId}
     */
    public function testLimits(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        
        $limits = $this->planLimitService->getPlanLimits($user);
        
        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'limits' => $limits,
            'can_create_establishment' => $this->planLimitService->canCreateEstablishment($user),
            'can_create_service' => $this->planLimitService->canCreateService($user),
            'can_add_employee' => $this->planLimitService->canAddEmployee($user),
        ]);
    }

    /**
     * Cria um usuário de teste com plano
     * POST /api/test/create-test-user
     */
    public function createTestUser(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        // Cria usuário de teste
        $user = User::create([
            'name' => $data['name'] ?? 'Usuário Teste ' . time(),
            'email' => $data['email'] ?? 'teste_' . time() . '@teste.com',
            'password' => Hash::make('password'),
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

        // Retorna informações do usuário e limites
        $limits = $this->planLimitService->getPlanLimits($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'password', // Para testes
            ],
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'max_establishments' => $plan->max_establishments,
                'max_services' => $plan->max_services,
                'max_employees' => $plan->max_employees,
            ],
            'limits' => $limits,
            'can_create_establishment' => $this->planLimitService->canCreateEstablishment($user),
            'can_create_service' => $this->planLimitService->canCreateService($user),
            'can_add_employee' => $this->planLimitService->canAddEmployee($user),
        ], Response::HTTP_CREATED);
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\UserPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlanController extends Controller
{
    /**
     * Lista todos os planos ativos
     */
    public function index(Request $request)
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json($plans);
    }

    /**
     * Exibe um plano específico
     */
    public function show(Plan $plan)
    {
        return response()->json($plan);
    }

    /**
     * Cria um novo plano (para desenvolvimento/admin)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'interval' => ['required', 'in:monthly,yearly'],
            'features' => ['nullable', 'array'],
            'max_establishments' => ['nullable', 'integer'],
            'max_services' => ['nullable', 'integer'],
            'max_employees' => ['nullable', 'integer'],
            'is_popular' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan = Plan::create($data);

        return response()->json($plan, Response::HTTP_CREATED);
    }

    /**
     * Retorna o plano atual do usuário
     */
    public function current(Request $request)
    {
        $user = $request->user();
        $userPlan = $user->currentPlan;

        if (!$userPlan) {
            // Retorna null em vez de 404 para facilitar o tratamento no frontend
            return response()->json(null);
        }

        $userPlan->load('plan');

        return response()->json($userPlan);
    }

    /**
     * Assina um plano (cria o user_plan)
     */
    public function subscribe(Request $request)
    {
        $user = $request->user();

        // Verifica se já tem um plano ativo
        $currentPlan = $user->currentPlan;
        if ($currentPlan) {
            return response()->json([
                'message' => 'Você já possui um plano ativo. Cancele o plano atual antes de assinar um novo.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        if (!$plan->is_active) {
            return response()->json([
                'message' => 'Este plano não está disponível.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Calcula a data de término baseado no intervalo
        $startsAt = now();
        $endsAt = match ($plan->interval) {
            'monthly' => $startsAt->copy()->addMonth(),
            'yearly' => $startsAt->copy()->addYear(),
            default => null,
        };

        $userPlan = UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $userPlan->load('plan');

        return response()->json($userPlan, Response::HTTP_CREATED);
    }

    /**
     * Cancela o plano atual do usuário
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        $userPlan = $user->currentPlan;

        if (!$userPlan) {
            return response()->json([
                'message' => 'Nenhum plano ativo encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        $userPlan->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Plano cancelado com sucesso.',
            'user_plan' => $userPlan->load('plan'),
        ]);
    }
}


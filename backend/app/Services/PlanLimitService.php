<?php

namespace App\Services;

use App\Models\User;
use App\Models\Establishment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class PlanLimitService
{
    /**
     * Verifica se o usuário pode criar mais estabelecimentos
     */
    public function canCreateEstablishment(User $user): array
    {
        // Admin não tem limites
        if ($user->role === 'admin') {
            return ['allowed' => true];
        }

        // Busca o plano atual do usuário
        $currentPlan = $user->currentPlan;

        if (!$currentPlan) {
            return [
                'allowed' => false,
                'message' => 'Você precisa de um plano ativo para criar estabelecimentos.',
            ];
        }

        $plan = $currentPlan->plan;

        // Se não tem limite (null), permite ilimitado
        if ($plan->max_establishments === null) {
            return ['allowed' => true];
        }

        // Conta estabelecimentos atuais do usuário
        $currentCount = Establishment::where('owner_id', $user->id)->count();

        if ($currentCount >= $plan->max_establishments) {
            return [
                'allowed' => false,
                'message' => "Você atingiu o limite de estabelecimentos do seu plano ({$plan->max_establishments}). Faça upgrade para criar mais estabelecimentos.",
                'current' => $currentCount,
                'limit' => $plan->max_establishments,
            ];
        }

        return [
            'allowed' => true,
            'current' => $currentCount,
            'limit' => $plan->max_establishments,
            'remaining' => $plan->max_establishments - $currentCount,
        ];
    }

    /**
     * Verifica se o usuário pode criar mais serviços
     */
    public function canCreateService(User $user, ?int $establishmentId = null): array
    {
        // Admin não tem limites
        if ($user->role === 'admin') {
            return ['allowed' => true];
        }

        // Busca o plano atual do usuário
        $currentPlan = $user->currentPlan;

        if (!$currentPlan) {
            return [
                'allowed' => false,
                'message' => 'Você precisa de um plano ativo para criar serviços.',
            ];
        }

        $plan = $currentPlan->plan;

        // Se não tem limite (null), permite ilimitado
        if ($plan->max_services === null) {
            return ['allowed' => true];
        }

        // Conta serviços atuais do usuário (em estabelecimentos dele)
        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
        $currentCount = Service::whereIn('establishment_id', $establishmentIds)->count();

        if ($currentCount >= $plan->max_services) {
            return [
                'allowed' => false,
                'message' => "Você atingiu o limite de serviços do seu plano ({$plan->max_services}). Faça upgrade para criar mais serviços.",
                'current' => $currentCount,
                'limit' => $plan->max_services,
            ];
        }

        return [
            'allowed' => true,
            'current' => $currentCount,
            'limit' => $plan->max_services,
            'remaining' => $plan->max_services - $currentCount,
        ];
    }

    /**
     * Verifica se o usuário pode adicionar mais funcionários
     */
    public function canAddEmployee(User $user, ?int $establishmentId = null): array
    {
        // Admin não tem limites
        if ($user->role === 'admin') {
            return ['allowed' => true];
        }

        // Busca o plano atual do usuário
        $currentPlan = $user->currentPlan;

        if (!$currentPlan) {
            return [
                'allowed' => false,
                'message' => 'Você precisa de um plano ativo para adicionar funcionários.',
            ];
        }

        $plan = $currentPlan->plan;

        // Se não tem limite (null), permite ilimitado
        if ($plan->max_employees === null) {
            return ['allowed' => true];
        }

        // Conta funcionários atuais do usuário (em estabelecimentos dele)
        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');

        if ($establishmentIds->isEmpty()) {
            $currentCount = 0;
        } else {
            // Conta funcionários únicos (pode ter funcionário em múltiplos estabelecimentos)
            $currentCount = DB::table('employee_establishment')
                ->whereIn('establishment_id', $establishmentIds)
                ->select('user_id')
                ->distinct()
                ->count();
        }

        if ($currentCount >= $plan->max_employees) {
            return [
                'allowed' => false,
                'message' => "Você atingiu o limite de funcionários do seu plano ({$plan->max_employees}). Faça upgrade para adicionar mais funcionários.",
                'current' => $currentCount,
                'limit' => $plan->max_employees,
            ];
        }

        return [
            'allowed' => true,
            'current' => $currentCount,
            'limit' => $plan->max_employees,
            'remaining' => $plan->max_employees - $currentCount,
        ];
    }

    /**
     * Retorna informações sobre os limites do plano atual do usuário
     */
    public function getPlanLimits(User $user): array
    {
        // Admin não tem limites
        if ($user->role === 'admin') {
            return [
                'has_plan' => true,
                'max_establishments' => null,
                'max_services' => null,
                'max_employees' => null,
                'current_establishments' => Establishment::where('owner_id', $user->id)->count(),
                'current_services' => Service::whereIn('establishment_id', Establishment::where('owner_id', $user->id)->pluck('id'))->count(),
            'current_employees' => DB::table('employee_establishment')
                ->whereIn('establishment_id', Establishment::where('owner_id', $user->id)->pluck('id'))
                ->select('user_id')
                ->distinct()
                ->count(),
            ];
        }

        $currentPlan = $user->currentPlan;

        if (!$currentPlan) {
            return [
                'has_plan' => false,
                'message' => 'Você não possui um plano ativo.',
            ];
        }

        $plan = $currentPlan->plan;
        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');

        $currentServices = 0;
        $currentEmployees = 0;

        if ($establishmentIds->isNotEmpty()) {
            $currentServices = Service::whereIn('establishment_id', $establishmentIds)->count();
            $currentEmployees = DB::table('employee_establishment')
                ->whereIn('establishment_id', $establishmentIds)
                ->select('user_id')
                ->distinct()
                ->count();
        }

        return [
            'has_plan' => true,
            'plan_name' => $plan->name,
            'max_establishments' => $plan->max_establishments,
            'max_services' => $plan->max_services,
            'max_employees' => $plan->max_employees,
            'current_establishments' => Establishment::where('owner_id', $user->id)->count(),
            'current_services' => $currentServices,
            'current_employees' => $currentEmployees,
        ];
    }
}


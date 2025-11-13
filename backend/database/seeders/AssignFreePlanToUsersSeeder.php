<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Database\Seeder;

class AssignFreePlanToUsersSeeder extends Seeder
{
    /**
     * Atribui o plano gratuito a todos os usuários owner que não têm plano ativo
     */
    public function run(): void
    {
        // Buscar o plano gratuito
        $freePlan = Plan::where('name', 'Gratuito')
            ->where('price', 0.00)
            ->where('interval', 'monthly')
            ->first();

        if (!$freePlan) {
            $this->command->error('Plano gratuito não encontrado! Execute primeiro o PlanSeeder.');
            return;
        }

        // Buscar todos os usuários owner que não têm plano ativo
        $usersWithoutPlan = User::where('role', 'owner')
            ->whereDoesntHave('userPlans', function ($query) {
                $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });
            })
            ->get();

        if ($usersWithoutPlan->isEmpty()) {
            $this->command->info('Todos os usuários owner já têm um plano ativo.');
            return;
        }

        $this->command->info("Encontrados {$usersWithoutPlan->count()} usuários sem plano ativo.");

        $assigned = 0;
        foreach ($usersWithoutPlan as $user) {
            // Verificar se já existe um UserPlan inativo para este usuário e plano
            $existingUserPlan = UserPlan::where('user_id', $user->id)
                ->where('plan_id', $freePlan->id)
                ->first();

            if ($existingUserPlan) {
                // Se existe mas está inativo, reativar
                if ($existingUserPlan->status !== 'active') {
                    $existingUserPlan->update([
                        'status' => 'active',
                        'starts_at' => now(),
                        'ends_at' => null,
                        'cancelled_at' => null,
                    ]);
                    $assigned++;
                    $this->command->info("Plano gratuito reativado para: {$user->name} ({$user->email})");
                } else {
                    $this->command->warn("Usuário {$user->name} já tem plano gratuito ativo.");
                }
            } else {
                // Criar novo UserPlan
                UserPlan::create([
                    'user_id' => $user->id,
                    'plan_id' => $freePlan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => null, // Plano gratuito não expira
                ]);
                $assigned++;
                $this->command->info("Plano gratuito atribuído para: {$user->name} ({$user->email})");
            }
        }

        $this->command->info("✅ Plano gratuito atribuído/reativado para {$assigned} usuário(s).");
    }
}

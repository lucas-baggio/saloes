<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            // Plano Gratuito
            [
                'name' => 'Gratuito',
                'description' => 'Plano gratuito para começar',
                'price' => 0.00,
                'interval' => 'monthly',
                'features' => [
                    'Até 1 estabelecimento',
                    'Até 5 serviços',
                    'Até 1 funcionário',
                    'Suporte básico',
                ],
                'max_establishments' => 1,
                'max_services' => 5,
                'max_employees' => 1,
                'is_popular' => false,
                'is_active' => true,
            ],
            // Planos Mensais
            [
                'name' => 'Básico',
                'description' => 'Ideal para começar',
                'price' => 29.90,
                'interval' => 'monthly',
                'features' => [
                    'Até 1 estabelecimento',
                    'Até 10 serviços',
                    'Até 3 funcionários',
                    'Suporte por email',
                ],
                'max_establishments' => 1,
                'max_services' => 10,
                'max_employees' => 3,
                'is_popular' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Profissional',
                'description' => 'Para negócios em crescimento',
                'price' => 79.90,
                'interval' => 'monthly',
                'features' => [
                    'Até 3 estabelecimentos',
                    'Até 50 serviços',
                    'Até 10 funcionários',
                    'Suporte prioritário',
                    'Relatórios avançados',
                ],
                'max_establishments' => 3,
                'max_services' => 50,
                'max_employees' => 10,
                'is_popular' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Empresarial',
                'description' => 'Para grandes operações',
                'price' => 199.90,
                'interval' => 'monthly',
                'features' => [
                    'Estabelecimentos ilimitados',
                    'Serviços ilimitados',
                    'Funcionários ilimitados',
                    'Suporte 24/7',
                    'Relatórios completos',
                    'API personalizada',
                ],
                'max_establishments' => null,
                'max_services' => null,
                'max_employees' => null,
                'is_popular' => false,
                'is_active' => true,
            ],
            // Planos Anuais
            [
                'name' => 'Básico',
                'description' => 'Ideal para começar',
                'price' => 299.00,
                'interval' => 'yearly',
                'features' => [
                    'Até 1 estabelecimento',
                    'Até 10 serviços',
                    'Até 3 funcionários',
                    'Suporte por email',
                    'Economia de 17%',
                ],
                'max_establishments' => 1,
                'max_services' => 10,
                'max_employees' => 3,
                'is_popular' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Profissional',
                'description' => 'Para negócios em crescimento',
                'price' => 799.00,
                'interval' => 'yearly',
                'features' => [
                    'Até 3 estabelecimentos',
                    'Até 50 serviços',
                    'Até 10 funcionários',
                    'Suporte prioritário',
                    'Relatórios avançados',
                    'Economia de 17%',
                ],
                'max_establishments' => 3,
                'max_services' => 50,
                'max_employees' => 10,
                'is_popular' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Empresarial',
                'description' => 'Para grandes operações',
                'price' => 1999.00,
                'interval' => 'yearly',
                'features' => [
                    'Estabelecimentos ilimitados',
                    'Serviços ilimitados',
                    'Funcionários ilimitados',
                    'Suporte 24/7',
                    'Relatórios completos',
                    'API personalizada',
                    'Economia de 17%',
                ],
                'max_establishments' => null,
                'max_services' => null,
                'max_employees' => null,
                'is_popular' => false,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }
    }
}


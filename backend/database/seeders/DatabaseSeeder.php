<?php

namespace Database\Seeders;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        Establishment::factory()
            ->count(3)
            ->has(
                \App\Models\Service::factory()
                    ->count(3)
                    ->has(\App\Models\SubService::factory()->count(2))
                    ->has(\App\Models\Scheduling::factory()->count(2))
            )
            ->create();
    }
}

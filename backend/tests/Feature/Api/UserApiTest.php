<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Novo Usuário',
            'email' => 'novo@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ];

        $createResponse = $this->postJson('/api/users', $payload);

        $createResponse
            ->assertCreated()
            ->assertJsonFragment([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'role' => $payload['role'],
            ])
            ->assertJsonMissing(['password' => $payload['password']]);

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'role' => $payload['role'],
        ]);

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user);
        $this->assertNotEquals($payload['password'], $user->getAttributes()['password']);

        $listResponse = $this->getJson('/api/users');

        $listResponse
            ->assertOk()
            ->assertJsonFragment([
                'email' => $payload['email'],
                'role' => $payload['role'],
            ]);
    }

    public function test_email_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/users', [
            'name' => 'Outro Usuário',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}


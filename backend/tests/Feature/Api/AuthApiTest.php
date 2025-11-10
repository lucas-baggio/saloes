<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Novo Dono',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_at',
                'user' => ['id', 'name', 'email', 'role'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'role' => 'owner',
        ]);

        $user = User::whereEmail('owner@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password123',
            'role' => 'employee',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);

        $token = $login->json('token');

        $logout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $logout->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }
}


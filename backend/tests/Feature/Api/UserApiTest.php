<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_create_user_validates_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_create_user_validates_password_min_length(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => 'short',
            'role' => 'owner',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_create_user_validates_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => 'password123',
            'role' => 'invalid_role',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_can_list_users_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(20)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
    }

    public function test_can_filter_users_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(5)->create(['role' => 'owner']);
        User::factory()->count(3)->create(['role' => 'employee']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users?role=owner');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_view_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }

    public function test_can_update_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Nome Atualizado',
            'email' => 'novoemail@example.com',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Nome Atualizado',
                'email' => 'novoemail@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nome Atualizado',
            'email' => 'novoemail@example.com',
        ]);
    }

    public function test_can_update_user_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$user->id}", [
            'password' => 'newpassword123',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_can_update_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'owner']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$user->id}", [
            'role' => 'employee',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['role' => 'employee']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'employee',
        ]);
    }

    public function test_update_user_validates_email_uniqueness(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$user1->id}", [
            'email' => 'user2@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_user_allows_same_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['email' => 'user@example.com']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Nome Atualizado',
            'email' => 'user@example.com',
        ]);

        $response->assertOk();
    }

    public function test_can_delete_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_user_list_is_ordered_by_latest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $oldUser = User::factory()->create(['created_at' => now()->subDays(2)]);
        $newUser = User::factory()->create(['created_at' => now()]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($newUser->id, $data[0]['id']);
    }
}



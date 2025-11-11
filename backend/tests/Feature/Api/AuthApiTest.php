<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\WelcomeNotification;
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
                'user' => ['id', 'name', 'email', 'role', 'email_verified_at'],
                'message',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'role' => 'owner',
        ]);

        $user = User::whereEmail('owner@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('email_verifications', [
            'email' => 'owner@example.com',
        ]);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_register_validates_email_format(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_unique_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_password_min_length(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'role' => 'owner',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_validates_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'invalid_role',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make('password123'),
            'role' => 'employee',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_at',
                'user' => ['id', 'name', 'email', 'role'],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_user_can_get_own_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'role',
                'email_verified_at',
                'created_at',
                'updated_at',
                'establishments',
                'services',
            ]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_user_can_request_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.']);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_reveal_if_email_exists(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.']);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $token = 'reset-token-123';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Senha redefinida com sucesso. Você já pode fazer login com a nova senha.']);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertBadRequest()
            ->assertJson(['message' => 'Token inválido ou expirado.']);
    }

    public function test_reset_password_fails_with_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $token = 'reset-token-123';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2), // Expirou (limite é 60 minutos)
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertBadRequest()
            ->assertJson(['message' => 'Token expirado. Solicite um novo link de recuperação.']);
    }

    public function test_reset_password_validates_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'some-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_verify_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'user@example.com']);

        $token = 'verification-token-123';
        DB::table('email_verifications')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'user@example.com',
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Email verificado com sucesso! Sua conta está ativa.']);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('email_verifications', [
            'email' => 'user@example.com',
        ]);

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_verify_email_fails_with_invalid_token(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'user@example.com',
            'token' => 'invalid-token',
        ]);

        $response->assertBadRequest()
            ->assertJson(['message' => 'Token de verificação inválido ou expirado.']);
    }

    public function test_verify_email_fails_with_expired_token(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'user@example.com']);

        $token = 'verification-token-123';
        DB::table('email_verifications')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subHours(25), // Expirou (limite é 24 horas)
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'user@example.com',
            'token' => $token,
        ]);

        $response->assertBadRequest()
            ->assertJson(['message' => 'Token expirado. Solicite um novo email de verificação.']);
    }

    public function test_verify_email_handles_already_verified_email(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => now(),
        ]);

        $token = 'verification-token-123';
        DB::table('email_verifications')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'user@example.com',
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Email já foi verificado anteriormente.']);
    }

    public function test_user_can_resend_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/auth/resend-verification', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Se o email estiver cadastrado e não verificado, você receberá um novo link de verificação.']);

        $this->assertDatabaseHas('email_verifications', [
            'email' => 'user@example.com',
        ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_verification_does_not_send_if_already_verified(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/resend-verification', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Este email já foi verificado.']);

        Notification::assertNothingSent();
    }

    public function test_resend_verification_does_not_reveal_if_email_exists(): void
    {
        $response = $this->postJson('/api/auth/resend-verification', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Se o email estiver cadastrado e não verificado, você receberá um novo link de verificação.']);
    }
}


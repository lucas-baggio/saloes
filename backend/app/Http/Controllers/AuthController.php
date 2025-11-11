<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'owner', 'employee'])],
        ]);

        // Criar usuário sem verificar email inicialmente
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'email_verified_at' => null, // Email não verificado
        ]);

        // Gerar token de verificação
        $verificationToken = Str::random(64);
        DB::table('email_verifications')->insert([
            'email' => $user->email,
            'token' => Hash::make($verificationToken),
            'created_at' => now(),
        ]);

        // Enviar email de verificação
        $verificationUrl = env('FRONTEND_URL', 'http://localhost:4200') . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);
        $user->notify(new VerifyEmailNotification($verificationUrl));

        // Criar token de autenticação (usuário pode fazer login, mas com limitações se necessário)
        $token = $user->createToken('auth_token', abilities: ['*'], expiresAt: now()->addHours(2));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ],
            'message' => 'Conta criada com sucesso! Verifique seu email para ativar sua conta.',
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $user->createToken('auth_token', abilities: ['*'], expiresAt: now()->addHours(2));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'establishments:id,name,owner_id',
            'services:id,name,user_id',
        ]);

        // Garantir que email_verified_at seja retornado
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'establishments' => $user->establishments,
            'services' => $user->services,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // Por segurança, não revelamos se o email existe ou não
            return response()->json([
                'message' => 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.',
            ], Response::HTTP_OK);
        }

        // Gerar token
        $token = Str::random(64);

        // Salvar token no banco (expira em 60 minutos)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Enviar email com o token
        $resetUrl = env('FRONTEND_URL', 'http://localhost:4200') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        $user->notify(new ResetPasswordNotification($resetUrl));

        return response()->json([
            'message' => 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.',
        ], Response::HTTP_OK);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $passwordReset) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verificar se o token expirou (60 minutos)
        if (now()->diffInMinutes($passwordReset->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Token expirado. Solicite um novo link de recuperação.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verificar se o token está correto
        if (! Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'message' => 'Token inválido.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Atualizar senha do usuário
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Usuário não encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Remover token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Senha redefinida com sucesso. Você já pode fazer login com a nova senha.',
        ], Response::HTTP_OK);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        \Log::info('verifyEmail called', [
            'token_length' => strlen($request->token ?? ''),
            'email' => $request->email,
            'all_data' => $request->all(),
        ]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $verification = DB::table('email_verifications')
            ->where('email', $request->email)
            ->first();

        if (! $verification) {
            return response()->json([
                'message' => 'Token de verificação inválido ou expirado.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verificar se o token expirou (24 horas)
        if (now()->diffInHours($verification->created_at) > 24) {
            DB::table('email_verifications')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Token expirado. Solicite um novo email de verificação.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verificar se o token está correto
        if (! Hash::check($request->token, $verification->token)) {
            return response()->json([
                'message' => 'Token inválido.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verificar email do usuário
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Usuário não encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email já foi verificado anteriormente.',
            ], Response::HTTP_OK);
        }

        // Atualizar email_verified_at
        $user->email_verified_at = now();
        $saved = $user->save();

        // Verificar se foi salvo corretamente
        $user->refresh();

        if (!$user->email_verified_at) {
            \Log::error('Falha ao atualizar email_verified_at', [
                'user_id' => $user->id,
                'email' => $user->email,
                'saved' => $saved,
            ]);

            // Tentar atualizar diretamente via DB
            DB::table('users')
                ->where('id', $user->id)
                ->update(['email_verified_at' => now()]);

            $user->refresh();
        }

                // Remover token usado
                DB::table('email_verifications')->where('email', $request->email)->delete();

                // Enviar email de boas-vindas após verificação
                $user->notify(new WelcomeNotification());

                return response()->json([
                    'message' => 'Email verificado com sucesso! Sua conta está ativa.',
                    'verified_at' => $user->email_verified_at?->toISOString(),
                ], Response::HTTP_OK);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // Por segurança, não revelamos se o email existe ou não
            return response()->json([
                'message' => 'Se o email estiver cadastrado e não verificado, você receberá um novo link de verificação.',
            ], Response::HTTP_OK);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Este email já foi verificado.',
            ], Response::HTTP_OK);
        }

        // Gerar novo token de verificação
        $verificationToken = Str::random(64);

        // Remover tokens antigos e criar novo
        DB::table('email_verifications')->where('email', $user->email)->delete();
        DB::table('email_verifications')->insert([
            'email' => $user->email,
            'token' => Hash::make($verificationToken),
            'created_at' => now(),
        ]);

        // Enviar email de verificação
        $verificationUrl = env('FRONTEND_URL', 'http://localhost:4200') . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);
        $user->notify(new VerifyEmailNotification($verificationUrl));

        return response()->json([
            'message' => 'Se o email estiver cadastrado e não verificado, você receberá um novo link de verificação.',
        ], Response::HTTP_OK);
    }
}


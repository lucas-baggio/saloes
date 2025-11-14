<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    /**
     * Envia uma mensagem de suporte por email
     */
    public function sendSupportMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $userEmail = $user ? $user->email : $request->email;
            $userName = $user ? $user->name : $request->name;

            // Email de destino (configurar no .env)
            $supportEmail = env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'suporte@exemplo.com'));

            // Envia o email
            Mail::raw(
                "Nova mensagem de suporte\n\n" .
                "Nome: {$userName}\n" .
                "Email: {$userEmail}\n" .
                "Assunto: {$request->subject}\n\n" .
                "Mensagem:\n{$request->message}\n\n" .
                "---\n" .
                "Enviado em: " . now()->format('d/m/Y H:i:s') . "\n" .
                "ID do usuário: " . ($user ? $user->id : 'Não autenticado'),
                function ($message) use ($supportEmail, $request, $userEmail) {
                    $message->to($supportEmail)
                        ->subject('[Suporte] ' . $request->subject)
                        ->replyTo($userEmail);
                }
            );

            return response()->json([
                'message' => 'Mensagem enviada com sucesso! Entraremos em contato em breve.',
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Erro ao enviar mensagem de suporte: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erro ao enviar mensagem. Tente novamente mais tarde.',
            ], 500);
        }
    }
}


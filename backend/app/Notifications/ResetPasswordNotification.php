<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $resetUrl
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        Log::info('ResetPasswordNotification::toMail chamado', [
            'email' => $notifiable->email,
            'reset_url' => $this->resetUrl,
        ]);

        try {
            $mail = (new MailMessage)
                ->subject('Redefinição de Senha - Salões')
                ->view('emails.reset-password', [
                    'resetUrl' => $this->resetUrl,
                ]);

            Log::info('MailMessage criado com sucesso');
            return $mail;
        } catch (\Exception $e) {
            Log::error('Erro ao criar MailMessage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}


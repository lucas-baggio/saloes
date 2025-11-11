<?php

namespace App\Notifications;

use App\Models\Scheduling;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatusChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Scheduling $scheduling,
        public string $oldStatus,
        public string $newStatus
    ) {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $schedulingDate = \Carbon\Carbon::parse($this->scheduling->scheduled_date)
            ->locale('pt_BR')
            ->isoFormat('dddd, DD [de] MMMM [de] YYYY');

        $schedulingTime = $this->scheduling->scheduled_time;

        $statusLabels = [
            'pending' => 'Pendente',
            'confirmed' => 'Confirmado',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
        ];

        return (new MailMessage)
            ->subject('Status do Agendamento Atualizado - Salões')
            ->view('emails.status-change', [
                'scheduling' => $this->scheduling,
                'schedulingDate' => $schedulingDate,
                'schedulingTime' => $schedulingTime,
                'clientName' => $this->scheduling->client_name,
                'oldStatus' => $statusLabels[$this->oldStatus] ?? $this->oldStatus,
                'newStatus' => $statusLabels[$this->newStatus] ?? $this->newStatus,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}


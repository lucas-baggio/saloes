<?php

namespace App\Notifications;

use App\Models\Scheduling;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchedulingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Scheduling $scheduling,
        public string $reminderType = '24h' // '24h' ou '1h'
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

        $reminderText = $this->reminderType === '24h'
            ? '24 horas'
            : '1 hora';

        return (new MailMessage)
            ->subject("Lembrete: Seu agendamento é em {$reminderText} - Salões")
            ->view('emails.scheduling-reminder', [
                'scheduling' => $this->scheduling,
                'schedulingDate' => $schedulingDate,
                'schedulingTime' => $schedulingTime,
                'clientName' => $this->scheduling->client_name,
                'reminderType' => $this->reminderType,
                'reminderText' => $reminderText,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}


<?php

namespace App\Notifications;

use App\Models\Scheduling;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchedulingConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Scheduling $scheduling)
    {
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

        return (new MailMessage)
            ->subject('Agendamento Confirmado - Salões')
            ->view('emails.scheduling-confirmation', [
                'scheduling' => $this->scheduling,
                'schedulingDate' => $schedulingDate,
                'schedulingTime' => $schedulingTime,
                'clientName' => $this->scheduling->client_name,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}


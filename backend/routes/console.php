<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agendar envio de lembretes de agendamento
Schedule::command('schedulings:send-reminders --type=24h')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo')
    ->description('Enviar lembretes de agendamentos 24h antes');

Schedule::command('schedulings:send-reminders --type=1h')
    ->hourly()
    ->timezone('America/Sao_Paulo')
    ->description('Enviar lembretes de agendamentos 1h antes');

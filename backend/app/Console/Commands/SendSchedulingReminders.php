<?php

namespace App\Console\Commands;

use App\Models\Scheduling;
use App\Models\User;
use App\Notifications\SchedulingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SendSchedulingReminders extends Command
{
    protected $signature = 'schedulings:send-reminders {--type=24h : Tipo de lembrete (24h ou 1h)}';
    protected $description = 'Envia lembretes de agendamentos (24h ou 1h antes)';

    public function handle()
    {
        $type = $this->option('type'); // '24h' ou '1h'

        if (!in_array($type, ['24h', '1h'])) {
            $this->error('Tipo inválido. Use 24h ou 1h.');
            return 1;
        }

        // Calcular a data/hora alvo
        $targetDateTime = Carbon::now('America/Sao_Paulo');
        if ($type === '24h') {
            $targetDateTime->addHours(24);
        } else {
            $targetDateTime->addHour();
        }

        $targetDate = $targetDateTime->format('Y-m-d');
        $targetTime = $targetDateTime->format('H:i');

        // Buscar agendamentos confirmados que estão próximos do horário
        // Buscar agendamentos do dia alvo com horário próximo (margem de 1 hora)
        $schedulings = Scheduling::where('scheduled_date', $targetDate)
            ->where('status', 'confirmed') // Apenas agendamentos confirmados
            ->whereTime('scheduled_time', '>=', Carbon::parse($targetTime)->subHour()->format('H:i'))
            ->whereTime('scheduled_time', '<=', Carbon::parse($targetTime)->addHour()->format('H:i'))
            ->with([
                'service:id,name,establishment_id',
                'establishment:id,name,owner_id',
            ])
            ->get();

        $sentCount = 0;

        foreach ($schedulings as $scheduling) {
            // Enviar para o proprietário do estabelecimento
            // Em produção, você pode adicionar um campo 'client_email' e enviar para o cliente
            $establishment = $scheduling->establishment;
            if ($establishment && $establishment->owner_id) {
                $owner = User::find($establishment->owner_id);
                if ($owner) {
                    $owner->notify(new SchedulingReminderNotification($scheduling, $type));
                    $sentCount++;
                }
            }
        }

        $this->info("Enviados {$sentCount} lembretes de {$type}.");
        return 0;
    }
}


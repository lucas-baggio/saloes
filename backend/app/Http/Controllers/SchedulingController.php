<?php

namespace App\Http\Controllers;

use App\Models\Scheduling;
use App\Models\Service;
use App\Models\Establishment;
use App\Models\User;
use App\Models\Sale;
use App\Notifications\SchedulingConfirmationNotification;
use App\Notifications\StatusChangeNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SchedulingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $schedulings = Scheduling::query()
            ->with([
                'service:id,name,establishment_id',
                'establishment:id,name,owner_id',
                'client:id,name,phone,email',
            ])
            ->when(
                $user->role === 'admin',
                // Admin vê tudo, mas pode filtrar
                function ($query) use ($request) {
                    if ($request->query('establishment_id')) {
                        $query->where('establishment_id', $request->query('establishment_id'));
                    }
                    if ($request->query('service_id')) {
                        $query->where('service_id', $request->query('service_id'));
                    }
                    if ($request->query('from')) {
                        $query->whereDate('scheduled_date', '>=', $request->query('from'));
                    }
                    if ($request->query('to')) {
                        $query->whereDate('scheduled_date', '<=', $request->query('to'));
                    }
                },
                // Owner e Employee veem apenas agendamentos dos seus estabelecimentos
                function ($query) use ($user, $request) {
                    if ($user->role === 'owner') {
                        // Owner vê agendamentos dos seus estabelecimentos
                        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
                        $query->whereIn('establishment_id', $establishmentIds);
                    } elseif ($user->role === 'employee') {
                        // Employee vê agendamentos dos estabelecimentos onde trabalha
                        $establishmentIds = DB::table('employee_establishment')
                            ->where('user_id', $user->id)
                            ->pluck('establishment_id');
                        $query->whereIn('establishment_id', $establishmentIds);
                    }

                    // Aplicar filtros opcionais
                    if ($request->query('establishment_id')) {
                        $query->where('establishment_id', $request->query('establishment_id'));
                    }
                    if ($request->query('service_id')) {
                        $query->where('service_id', $request->query('service_id'));
                    }
                    if ($request->query('from')) {
                        $query->whereDate('scheduled_date', '>=', $request->query('from'));
                    }
                    if ($request->query('to')) {
                        $query->whereDate('scheduled_date', '<=', $request->query('to'));
                    }
                }
            )
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->paginate($request->query('per_page', 15));

        return response()->json($schedulings);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['required_without:client_id', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,completed,cancelled'],
        ]);

        // Se não fornecido, definir como 'pending'
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        // Garantir que a data seja tratada como data local (sem timezone)
        // O formato YYYY-MM-DD deve ser interpretado como data local, não UTC
        if (isset($data['scheduled_date'])) {
            // Se já for string no formato YYYY-MM-DD, usar diretamente
            // Não usar Carbon para evitar problemas de timezone
            if (is_string($data['scheduled_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['scheduled_date'])) {
                // Já está no formato correto, usar diretamente
                // Não fazer nada, apenas garantir que está no formato correto
            } else {
                // Se vier como objeto Carbon ou DateTime, converter para string YYYY-MM-DD
                $date = Carbon::parse($data['scheduled_date'])->format('Y-m-d');
                $data['scheduled_date'] = $date;
            }
        }

        // Se client_id foi fornecido, buscar o nome do cliente
        if (isset($data['client_id'])) {
            $client = \App\Models\Client::find($data['client_id']);
            if ($client && $client->owner_id === $user->id) {
                $data['client_name'] = $client->name;
            } else {
                // Se o cliente não pertence ao usuário, remover client_id
                unset($data['client_id']);
            }
        }

        // Verificar se o estabelecimento pertence ao usuário (se não for admin)
        if ($user->role !== 'admin') {
            $establishment = Establishment::findOrFail($data['establishment_id']);

            if ($user->role === 'owner') {
                // Owner só pode criar agendamentos para seus estabelecimentos
                if ($establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado. Estabelecimento não pertence a você.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                // Employee só pode criar agendamentos para estabelecimentos onde trabalha
                $employeeExists = DB::table('employee_establishment')
                    ->where('user_id', $user->id)
                    ->where('establishment_id', $data['establishment_id'])
                    ->exists();

                if (!$employeeExists) {
                    return response()->json(['message' => 'Não autorizado. Você não trabalha neste estabelecimento.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        // Verificar se o serviço pertence ao estabelecimento informado
        $service = Service::findOrFail($data['service_id']);
        // Converter ambos para integer para garantir comparação correta
        if ((int) $service->establishment_id !== (int) $data['establishment_id']) {
            return response()->json([
                'message' => 'O serviço selecionado não pertence ao estabelecimento informado.',
                'debug' => [
                    'service_establishment_id' => $service->establishment_id,
                    'provided_establishment_id' => $data['establishment_id'],
                    'service_id' => $service->id,
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar horário único e conflitos
        $this->validateUniqueSlot($data);

        $scheduling = Scheduling::create($data)->load([
            'service:id,name,establishment_id,price,description',
            'service.user:id,name,email',
            'establishment:id,name',
        ]);

        $establishment = Establishment::find($data['establishment_id']);
        if ($establishment && $establishment->owner_id) {
            $owner = User::find($establishment->owner_id);
            if ($owner) {
                $owner->notify(new SchedulingConfirmationNotification($scheduling));
            }
        }

        return response()->json($scheduling, Response::HTTP_CREATED);
    }

    public function show(Request $request, Scheduling $scheduling)
    {
        $user = $request->user();

        // Carregar estabelecimento para verificar ownership
        $scheduling->load('establishment:id,name,owner_id', 'client:id,name,phone,email');

        // Verificar permissão
        if ($user->role !== 'admin') {
            if ($user->role === 'owner') {
                // Owner só vê agendamentos dos seus estabelecimentos
                if ($scheduling->establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                // Employee só vê agendamentos dos estabelecimentos onde trabalha
                $employeeExists = DB::table('employee_establishment')
                    ->where('user_id', $user->id)
                    ->where('establishment_id', $scheduling->establishment_id)
                    ->exists();

                if (!$employeeExists) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $scheduling->load([
            'service:id,name,establishment_id',
            'establishment:id,name',
            'client:id,name,phone,email',
        ]);

        return response()->json($scheduling);
    }

    public function update(Request $request, Scheduling $scheduling)
    {
        $user = $request->user();

        // Carregar estabelecimento para verificar ownership
        $scheduling->load('establishment:id,name,owner_id', 'client:id,name,phone,email');

        // Verificar permissão
        if ($user->role !== 'admin') {
            if ($user->role === 'owner') {
                // Owner só edita agendamentos dos seus estabelecimentos
                if ($scheduling->establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                // Employee só edita agendamentos dos estabelecimentos onde trabalha
                $employeeExists = DB::table('employee_establishment')
                    ->where('user_id', $user->id)
                    ->where('establishment_id', $scheduling->establishment_id)
                    ->exists();

                if (!$employeeExists) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $data = $request->validate([
            'scheduled_date' => ['sometimes', 'date_format:Y-m-d'],
            'scheduled_time' => ['sometimes', 'date_format:H:i'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'establishment_id' => ['sometimes', 'integer', 'exists:establishments,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,completed,cancelled'],
        ]);

        // Garantir que a data seja tratada como data local (sem timezone)
        if (isset($data['scheduled_date'])) {
            // Se já for string no formato YYYY-MM-DD, usar diretamente
            if (is_string($data['scheduled_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['scheduled_date'])) {
                // Já está no formato correto, usar diretamente
            } else {
                // Se vier como objeto Carbon ou DateTime, converter para string YYYY-MM-DD
                $date = Carbon::parse($data['scheduled_date'])->format('Y-m-d');
                $data['scheduled_date'] = $date;
            }
        }

        // Se não fornecido, definir como 'pending'
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        // Se client_id foi fornecido, buscar o nome do cliente
        if (isset($data['client_id'])) {
            $client = \App\Models\Client::find($data['client_id']);
            if ($client && $client->owner_id === $user->id) {
                $data['client_name'] = $client->name;
            } else {
                // Se o cliente não pertence ao usuário, remover client_id
                unset($data['client_id']);
            }
        }

        // Se estiver mudando o establishment_id, verificar permissão
        if (isset($data['establishment_id']) && $user->role !== 'admin') {
            $establishment = Establishment::findOrFail($data['establishment_id']);

            if ($user->role === 'owner') {
                if ($establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado. Estabelecimento não pertence a você.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                $employeeExists = DB::table('employee_establishment')
                    ->where('user_id', $user->id)
                    ->where('establishment_id', $data['establishment_id'])
                    ->exists();

                if (!$employeeExists) {
                    return response()->json(['message' => 'Não autorizado. Você não trabalha neste estabelecimento.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        // Se estiver mudando o service_id, verificar se pertence ao establishment_id
        if (isset($data['service_id'])) {
            $service = Service::findOrFail($data['service_id']);
            $establishmentId = $data['establishment_id'] ?? $scheduling->establishment_id;

            if ($service->establishment_id !== $establishmentId) {
                return response()->json(['message' => 'O serviço selecionado não pertence ao estabelecimento informado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $mergedData = array_merge($scheduling->only(['scheduled_date', 'scheduled_time', 'service_id', 'establishment_id']), $data);

        $this->validateUniqueSlot($mergedData, $scheduling->id);

        // Capturar status antigo antes de atualizar
        $oldStatus = $scheduling->status;

        $scheduling->update($data);
        $scheduling->refresh()->load([
            'service:id,name,establishment_id,price,description',
            'service.user:id,name,email',
            'establishment:id,name,owner_id',
            'client:id,name,phone,email',
        ]);

        // Enviar notificação de mudança de status (se mudou)
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            // Enviar para o proprietário do estabelecimento
            $establishment = $scheduling->establishment;
            if ($establishment && $establishment->owner_id) {
                $owner = User::find($establishment->owner_id);
                if ($owner) {
                    $owner->notify(new StatusChangeNotification($scheduling, $oldStatus, $data['status']));
                }
            }

            // Criar venda automaticamente quando o agendamento for concluído
            if ($data['status'] === 'completed' && $oldStatus !== 'completed') {
                $this->createSaleFromScheduling($scheduling, $user);
            }
        }

        return response()->json($scheduling);
    }

    public function destroy(Request $request, Scheduling $scheduling)
    {
        $user = $request->user();

        // Carregar estabelecimento para verificar ownership
        $scheduling->load('establishment:id,name,owner_id', 'client:id,name,phone,email');

        // Verificar permissão
        if ($user->role !== 'admin') {
            if ($user->role === 'owner') {
                // Owner só deleta agendamentos dos seus estabelecimentos
                if ($scheduling->establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                // Employee só deleta agendamentos dos estabelecimentos onde trabalha
                $employeeExists = DB::table('employee_establishment')
                    ->where('user_id', $user->id)
                    ->where('establishment_id', $scheduling->establishment_id)
                    ->exists();

                if (!$employeeExists) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $scheduling->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function validateUniqueSlot(array $data, ?int $ignoreId = null): void
    {
        // Verificar conflito exato (mesmo serviço, data e horário)
        $exactConflict = Scheduling::where('scheduled_date', $data['scheduled_date'])
            ->where('scheduled_time', $data['scheduled_time'])
            ->where('service_id', $data['service_id'])
            ->where('status', '!=', 'cancelled') // Ignorar agendamentos cancelados
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exactConflict) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'scheduled_time' => ['Já existe um agendamento para este serviço neste horário.'],
            ]);
        }

        // Verificar conflitos de sobreposição no mesmo estabelecimento/funcionário
        // Assumindo duração padrão de 1 hora para serviços
        $service = Service::find($data['service_id']);

        // Extrair apenas a data (sem hora) antes de concatenar
        $scheduledDateStr = $data['scheduled_date'];
        if ($scheduledDateStr instanceof \Carbon\Carbon) {
            $scheduledDateStr = $scheduledDateStr->format('Y-m-d');
        } elseif (is_string($scheduledDateStr) && strpos($scheduledDateStr, ' ') !== false) {
            // Se vier com hora, pegar apenas a data
            $scheduledDateStr = explode(' ', $scheduledDateStr)[0];
        }

        // Garantir que scheduled_time seja apenas H:i (sem segundos)
        $scheduledTimeStr = $data['scheduled_time'];
        if (is_string($scheduledTimeStr) && substr_count($scheduledTimeStr, ':') > 1) {
            $parts = explode(':', $scheduledTimeStr);
            $scheduledTimeStr = $parts[0] . ':' . $parts[1];
        }

        $scheduledDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $scheduledDateStr . ' ' . $scheduledTimeStr);
        $endDateTime = $scheduledDateTime->copy()->addHour(); // Duração padrão de 1 hora

        // Buscar agendamentos que podem conflitar
        $conflictingSchedulings = Scheduling::where('scheduled_date', $scheduledDateStr)
            ->where('establishment_id', $data['establishment_id'])
            ->where('status', '!=', 'cancelled')
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->get();

        foreach ($conflictingSchedulings as $existing) {
            // Extrair apenas a data do agendamento existente
            $existingDateStr = $existing->scheduled_date;
            if ($existingDateStr instanceof \Carbon\Carbon) {
                $existingDateStr = $existingDateStr->format('Y-m-d');
            } elseif (is_string($existingDateStr) && strpos($existingDateStr, ' ') !== false) {
                $existingDateStr = explode(' ', $existingDateStr)[0];
            }

            // Garantir que scheduled_time seja apenas H:i
            $existingTimeStr = $existing->scheduled_time;
            if (is_string($existingTimeStr) && substr_count($existingTimeStr, ':') > 1) {
                $parts = explode(':', $existingTimeStr);
                $existingTimeStr = $parts[0] . ':' . $parts[1];
            }

            $existingStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $existingDateStr . ' ' . $existingTimeStr);
            $existingEnd = $existingStart->copy()->addHour(); // Duração padrão de 1 hora

            // Verificar se há sobreposição de horários
            if ($scheduledDateTime->lt($existingEnd) && $endDateTime->gt($existingStart)) {
                // Se o serviço tem um funcionário atribuído, verificar conflito apenas para o mesmo funcionário
                if ($service->user_id && $existing->service) {
                    $existingService = Service::find($existing->service_id);
                    if ($existingService && $existingService->user_id === $service->user_id) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'scheduled_time' => ['Este horário conflita com outro agendamento do mesmo funcionário.'],
                        ]);
                    }
                } else {
                    // Se não há funcionário específico, verificar conflito no estabelecimento
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'scheduled_time' => ['Este horário conflita com outro agendamento no estabelecimento.'],
                    ]);
                }
            }
        }
    }

    /**
     * Cria uma venda automaticamente quando um agendamento é concluído
     */
    private function createSaleFromScheduling(Scheduling $scheduling, User $user): void
    {
        // Verificar se já existe uma venda para este agendamento
        $existingSale = Sale::where('scheduling_id', $scheduling->id)->first();
        if ($existingSale) {
            // Já existe uma venda, não criar duplicata
            return;
        }

        // Verificar se o agendamento tem os dados necessários
        if (!$scheduling->service_id || !$scheduling->establishment_id) {
            // Não tem dados suficientes para criar a venda
            return;
        }

        // Carregar o serviço para obter o preço
        $service = Service::find($scheduling->service_id);
        if (!$service) {
            return;
        }

        // Determinar o user_id (funcionário que realizou)
        // Se o serviço tem um funcionário atribuído, usar ele
        // Caso contrário, usar o usuário autenticado
        $saleUserId = $service->user_id ?? $user->id;

        // Criar a venda
        Sale::create([
            'client_id' => $scheduling->client_id,
            'service_id' => $scheduling->service_id,
            'scheduling_id' => $scheduling->id,
            'establishment_id' => $scheduling->establishment_id,
            'user_id' => $saleUserId,
            'amount' => $service->price,
            'payment_method' => 'pix', // Padrão, pode ser alterado depois
            'sale_date' => $scheduling->scheduled_date,
            'status' => 'pending', // Padrão como pendente, pode ser alterado depois
            'notes' => 'Venda criada automaticamente ao concluir agendamento.',
        ]);
    }
}



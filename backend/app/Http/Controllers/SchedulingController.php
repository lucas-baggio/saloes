<?php

namespace App\Http\Controllers;

use App\Models\Scheduling;
use App\Models\Service;
use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SchedulingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $schedulings = Scheduling::query()
            ->with([
                'service:id,name,establishment_id',
                'establishment:id,name,owner_id',
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
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,completed,cancelled'],
        ]);

        // Se não fornecido, definir como 'pending'
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
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

        $this->validateUniqueSlot($data);

        $scheduling = Scheduling::create($data)->load([
            'service:id,name,establishment_id',
            'establishment:id,name',
        ]);

        return response()->json($scheduling, Response::HTTP_CREATED);
    }

    public function show(Request $request, Scheduling $scheduling)
    {
        $user = $request->user();

        // Carregar estabelecimento para verificar ownership
        $scheduling->load('establishment:id,name,owner_id');

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
        ]);

        return response()->json($scheduling);
    }

    public function update(Request $request, Scheduling $scheduling)
    {
        $user = $request->user();

        // Carregar estabelecimento para verificar ownership
        $scheduling->load('establishment:id,name,owner_id');

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
            'scheduled_date' => ['sometimes', 'date'],
            'scheduled_time' => ['sometimes', 'date_format:H:i'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'establishment_id' => ['sometimes', 'integer', 'exists:establishments,id'],
            'client_name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,completed,cancelled'],
        ]);

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

        $scheduling->update($data);

        return response()->json($scheduling->refresh()->load([
            'service:id,name,establishment_id',
            'establishment:id,name',
        ]));
    }

    public function destroy(Request $request, Scheduling $scheduling)
    {
        $user = $request->user();

        // Carregar estabelecimento para verificar ownership
        $scheduling->load('establishment:id,name,owner_id');

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
        Validator::make(
            $data,
            [
                'scheduled_date' => [
                    Rule::unique('schedulings')->where(fn ($query) => $query
                        ->where('service_id', $data['service_id'])
                        ->where('scheduled_time', $data['scheduled_time'])
                    )->ignore($ignoreId),
                ],
            ],
            [
                'scheduled_date.unique' => 'Já existe um agendamento para este serviço neste horário.',
            ]
        )->validate();
    }
}



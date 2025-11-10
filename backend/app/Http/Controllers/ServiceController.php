<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\SubService;
use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $services = Service::query()
            ->with([
                'establishment:id,name,owner_id',
                'user:id,name,email,role',
                'subServices:id,name,description,price,service_id',
            ])
            ->when(
                $user->role === 'admin',
                // Admin vê tudo, mas pode filtrar
                function ($query) use ($request) {
                    if ($request->query('establishment_id')) {
                        $query->where('establishment_id', $request->query('establishment_id'));
                    }
                    if ($request->query('user_id')) {
                        $query->where('user_id', $request->query('user_id'));
                    }
                },
                // Owner e Employee veem apenas serviços dos seus estabelecimentos
                function ($query) use ($user) {
                    if ($user->role === 'owner') {
                        // Owner vê serviços dos seus estabelecimentos
                        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
                        $query->whereIn('establishment_id', $establishmentIds);
                    } elseif ($user->role === 'employee') {
                        // Employee vê apenas serviços atribuídos a ele
                        $query->where('user_id', $user->id);
                    }
                }
            )
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json($services);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Funcionários não podem criar serviços
        if ($user->role === 'employee') {
            return response()->json(['message' => 'Não autorizado. Funcionários não podem criar serviços.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'between:0,999999.99'],
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sub_services' => ['nullable', 'array'],
            'sub_services.*.name' => ['required_with:sub_services', 'string', 'max:255'],
            'sub_services.*.description' => ['nullable', 'string'],
            'sub_services.*.price' => ['required_with:sub_services', 'numeric', 'between:0,999999.99'],
        ]);

        // Verificar se o estabelecimento pertence ao usuário (se não for admin)
        if ($user->role !== 'admin') {
            $establishment = Establishment::findOrFail($data['establishment_id']);
            if ($establishment->owner_id !== $user->id) {
                return response()->json(['message' => 'Não autorizado. Estabelecimento não pertence a você.'], Response::HTTP_FORBIDDEN);
            }
        }

        // Validar e processar subserviços
        $subServices = $data['sub_services'] ?? [];
        unset($data['sub_services']);

        // Calcular preço total dos subserviços
        $totalPrice = 0;
        if (!empty($subServices)) {
            foreach ($subServices as $subService) {
                $totalPrice += (float) $subService['price'];
            }
        }

        // Se não forneceu preço, usar a soma dos subserviços
        // Se forneceu preço, usar o fornecido (mas validar se há subserviços)
        if (empty($data['price']) && !empty($subServices)) {
            $data['price'] = $totalPrice;
        } elseif (empty($data['price']) && empty($subServices)) {
            return response()->json(['message' => 'É necessário fornecer um preço ou criar subserviços.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar user_id (funcionário) se fornecido
        if (isset($data['user_id'])) {
            // Verificar se o funcionário trabalha no estabelecimento
            $establishment = Establishment::findOrFail($data['establishment_id']);
            $employeeExists = DB::table('employee_establishment')
                ->where('user_id', $data['user_id'])
                ->where('establishment_id', $data['establishment_id'])
                ->exists();

            if (!$employeeExists && $user->role !== 'admin') {
                return response()->json(['message' => 'O funcionário selecionado não trabalha neste estabelecimento.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } elseif ($user->role !== 'admin') {
            // Se não forneceu user_id e não for admin, não atribuir funcionário (pode ser null)
        }

        // Criar serviço
        $service = Service::create($data);

        // Criar subserviços se fornecidos
        if (!empty($subServices)) {
            foreach ($subServices as $subServiceData) {
                SubService::create([
                    'name' => $subServiceData['name'],
                    'description' => $subServiceData['description'] ?? null,
                    'price' => $subServiceData['price'],
                    'service_id' => $service->id,
                ]);
            }
        }

        $service->load([
            'establishment:id,name',
            'user:id,name,email,role',
            'subServices:id,name,description,price,service_id',
        ]);

        return response()->json($service, Response::HTTP_CREATED);
    }

    public function show(Request $request, Service $service)
    {
        $user = $request->user();

        // Carregar o estabelecimento para verificar ownership
        $service->load('establishment:id,name,owner_id');

        // Verificar permissão
        if ($user->role !== 'admin') {
            if ($user->role === 'owner') {
                // Owner só vê serviços dos seus estabelecimentos
                if ($service->establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                // Employee só vê seus próprios serviços
                if ($service->user_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $service->load([
            'establishment:id,name',
            'user:id,name,email,role',
            'subServices:id,name,description,price,service_id',
            'schedulings:id,scheduled_date,scheduled_time,service_id',
        ]);

        return response()->json($service);
    }

    public function update(Request $request, Service $service)
    {
        $user = $request->user();

        // Carregar o estabelecimento para verificar ownership
        $service->load('establishment:id,name,owner_id');

        // Verificar permissão
        if ($user->role !== 'admin') {
            if ($user->role === 'owner') {
                // Owner só edita serviços dos seus estabelecimentos
                if ($service->establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                // Employee só edita seus próprios serviços
                if ($service->user_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'between:0,999999.99'],
            'establishment_id' => ['sometimes', 'integer', 'exists:establishments,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        // Se estiver mudando o establishment_id, verificar permissão
        if (isset($data['establishment_id']) && $user->role !== 'admin') {
            $establishment = Establishment::findOrFail($data['establishment_id']);
            if ($establishment->owner_id !== $user->id) {
                return response()->json(['message' => 'Não autorizado. Estabelecimento não pertence a você.'], Response::HTTP_FORBIDDEN);
            }
        }

        $service->update($data);

        return response()->json($service->refresh()->load([
            'establishment:id,name',
            'user:id,name,email,role',
            'subServices:id,name,service_id',
        ]));
    }

    public function destroy(Request $request, Service $service)
    {
        $user = $request->user();

        // Carregar o estabelecimento para verificar ownership
        $service->load('establishment:id,name,owner_id');

        // Verificar permissão
        if ($user->role !== 'admin') {
            if ($user->role === 'owner') {
                // Owner só deleta serviços dos seus estabelecimentos
                if ($service->establishment->owner_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            } elseif ($user->role === 'employee') {
                // Employee só deleta seus próprios serviços
                if ($service->user_id !== $user->id) {
                    return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $service->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}


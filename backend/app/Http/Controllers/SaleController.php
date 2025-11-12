<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Commission;
use App\Models\Establishment;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $sales = Sale::query()
            ->with([
                'client:id,name,phone,email',
                'service:id,name,price',
                'scheduling:id,scheduled_date,scheduled_time',
                'establishment:id,name',
                'user:id,name,email',
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
                    if ($request->query('client_id')) {
                        $query->where('client_id', $request->query('client_id'));
                    }
                    if ($request->query('from')) {
                        $query->whereDate('sale_date', '>=', $request->query('from'));
                    }
                    if ($request->query('to')) {
                        $query->whereDate('sale_date', '<=', $request->query('to'));
                    }
                    if ($request->query('status')) {
                        $query->where('status', $request->query('status'));
                    }
                    if ($request->query('payment_method')) {
                        $query->where('payment_method', $request->query('payment_method'));
                    }
                },
                // Owner e Employee veem apenas vendas dos seus estabelecimentos
                function ($query) use ($user, $request) {
                    if ($user->role === 'owner') {
                        // Owner vê vendas dos seus estabelecimentos
                        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
                        $query->whereIn('establishment_id', $establishmentIds);
                    } elseif ($user->role === 'employee') {
                        // Employee vê apenas vendas que ele realizou
                        $query->where('user_id', $user->id);
                    }

                    // Aplicar filtros opcionais
                    if ($request->query('establishment_id')) {
                        $query->where('establishment_id', $request->query('establishment_id'));
                    }
                    if ($request->query('client_id')) {
                        $query->where('client_id', $request->query('client_id'));
                    }
                    if ($request->query('from')) {
                        $query->whereDate('sale_date', '>=', $request->query('from'));
                    }
                    if ($request->query('to')) {
                        $query->whereDate('sale_date', '<=', $request->query('to'));
                    }
                    if ($request->query('status')) {
                        $query->where('status', $request->query('status'));
                    }
                    if ($request->query('payment_method')) {
                        $query->where('payment_method', $request->query('payment_method'));
                    }
                }
            )
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                        ->orWhereHas('service', function ($serviceQuery) use ($search) {
                            $serviceQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('sale_date')
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json($sales);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'scheduling_id' => ['nullable', 'integer', 'exists:schedulings,id'],
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'payment_method' => ['required', 'string', 'in:pix,cartao_credito,cartao_debito,dinheiro,outro'],
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'string', 'in:pending,paid,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        // Se não fornecido, definir como 'pending'
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        // Verificar permissões
        if ($user->role === 'owner') {
            // Owner só pode criar vendas nos seus estabelecimentos
            $establishment = Establishment::findOrFail($data['establishment_id']);
            if ($establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Você não tem permissão para criar vendas neste estabelecimento.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            // Employee só pode criar vendas nos estabelecimentos onde trabalha
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');

            if (!in_array($data['establishment_id'], $establishmentIds->toArray())) {
                return response()->json(
                    ['message' => 'Você não tem permissão para criar vendas neste estabelecimento.'],
                    Response::HTTP_FORBIDDEN
                );
            }
            // Employee sempre cria vendas com seu próprio user_id
            $data['user_id'] = $user->id;
        }

        // Se service_id fornecido, usar o preço e funcionário do serviço
        if (isset($data['service_id'])) {
            $service = Service::findOrFail($data['service_id']);

            // Usar o preço do serviço se amount não foi especificado
            if (!isset($data['amount'])) {
                $data['amount'] = $service->price;
            }

            // Se o serviço tem um funcionário atribuído e user_id não foi fornecido, usar o funcionário do serviço
            if (!isset($data['user_id']) && $service->user_id) {
                $data['user_id'] = $service->user_id;
            }
        }

        // Se user_id ainda não foi definido, usar o usuário autenticado
        if (!isset($data['user_id'])) {
            $data['user_id'] = $user->id;
        }

        $sale = Sale::create($data);
        $sale->load([
            'client:id,name,phone,email',
            'service:id,name,price',
            'scheduling:id,scheduled_date,scheduled_time',
            'establishment:id,name',
            'user:id,name,email',
        ]);

        // Criar comissão automaticamente se a venda tem um funcionário
        if ($sale->user_id && $sale->status !== 'cancelled') {
            $this->createCommissionForSale($sale);
        }

        return response()->json($sale, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale)
    {
        $user = request()->user();

        // Verificar permissões
        if ($user->role === 'owner') {
            $establishment = $sale->establishment;
            if ($establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            if ($sale->user_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $sale->load([
            'client:id,name,phone,email,cpf,birth_date,address',
            'service:id,name,price,description',
            'scheduling:id,scheduled_date,scheduled_time,status',
            'establishment:id,name',
            'user:id,name,email',
        ]);

        return response()->json($sale);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sale $sale)
    {
        $user = $request->user();

        // Verificar permissões
        if ($user->role === 'owner') {
            $establishment = $sale->establishment;
            if ($establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            if ($sale->user_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $data = $request->validate([
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'scheduling_id' => ['sometimes', 'nullable', 'integer', 'exists:schedulings,id'],
            'establishment_id' => ['sometimes', 'integer', 'exists:establishments,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'payment_method' => ['sometimes', 'string', 'in:pix,cartao_credito,cartao_debito,dinheiro,outro'],
            'sale_date' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'string', 'in:pending,paid,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        // Verificar permissões para establishment_id se foi alterado
        if (isset($data['establishment_id']) && $data['establishment_id'] !== $sale->establishment_id) {
            if ($user->role === 'owner') {
                $establishment = Establishment::findOrFail($data['establishment_id']);
                if ($establishment->owner_id !== $user->id) {
                    return response()->json(
                        ['message' => 'Você não tem permissão para mover vendas para este estabelecimento.'],
                        Response::HTTP_FORBIDDEN
                    );
                }
            } elseif ($user->role === 'employee') {
                $establishmentIds = DB::table('employee_establishment')
                    ->where('user_id', $user->id)
                    ->pluck('establishment_id');

                if (!in_array($data['establishment_id'], $establishmentIds->toArray())) {
                    return response()->json(
                        ['message' => 'Você não tem permissão para mover vendas para este estabelecimento.'],
                        Response::HTTP_FORBIDDEN
                    );
                }
            }
        }

        // Capturar valores antigos antes de atualizar
        $oldStatus = $sale->status;
        $oldUserId = $sale->user_id;
        $oldAmount = $sale->amount;
        $oldServiceId = $sale->service_id;

        // Se service_id foi alterado, atualizar user_id e amount
        if (isset($data['service_id']) && $data['service_id'] !== $oldServiceId) {
            $service = Service::findOrFail($data['service_id']);

            // Se o serviço tem um funcionário atribuído, usar ele
            if ($service->user_id) {
                $data['user_id'] = $service->user_id;
            }

            // Se amount não foi especificado, usar o preço do serviço
            if (!isset($data['amount'])) {
                $data['amount'] = $service->price;
            }
        }

        $sale->update($data);
        $sale->refresh()->load([
            'client:id,name,phone,email',
            'service:id,name,price,user_id',
            'scheduling:id,scheduled_date,scheduled_time',
            'establishment:id,name',
            'user:id,name,email',
        ]);

        // Gerenciar comissões quando a venda é atualizada
        $this->manageCommissionsForSale($sale, $oldStatus, $oldUserId, $oldAmount, $oldServiceId);

        return response()->json($sale);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        $user = request()->user();

        // Verificar permissões
        if ($user->role === 'owner') {
            $establishment = $sale->establishment;
            if ($establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            // Employee não pode deletar vendas
            return response()->json(
                ['message' => 'Funcionários não podem deletar vendas.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $sale->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cria uma comissão automaticamente para uma venda
     */
    private function createCommissionForSale(Sale $sale): void
    {
        // Carregar o serviço para obter o funcionário responsável
        $sale->load('service:id,user_id');

        // Usar o funcionário do serviço, não o user_id da venda
        // Se o serviço não tem funcionário, usar o user_id da venda
        $commissionUserId = $sale->service->user_id ?? $sale->user_id;

        if (!$commissionUserId) {
            // Não tem funcionário para receber comissão
            return;
        }

        // Verificar se já existe uma comissão para esta venda e funcionário
        $existingCommission = Commission::where('sale_id', $sale->id)
            ->where('user_id', $commissionUserId)
            ->first();

        if ($existingCommission) {
            // Já existe uma comissão, não criar duplicata
            return;
        }

        // Percentual padrão de comissão (10%)
        // TODO: Permitir configurar percentual por serviço ou funcionário
        $defaultPercentage = 10.0;

        // Calcular valor da comissão
        $commissionAmount = ($sale->amount * $defaultPercentage) / 100;

        // Criar a comissão
        Commission::create([
            'sale_id' => $sale->id,
            'user_id' => $commissionUserId,
            'percentage' => $defaultPercentage,
            'amount' => $commissionAmount,
            'status' => 'pending',
            'notes' => 'Comissão criada automaticamente.',
        ]);
    }

    /**
     * Gerencia comissões quando uma venda é atualizada
     */
    private function manageCommissionsForSale(Sale $sale, string $oldStatus, ?int $oldUserId, float $oldAmount, ?int $oldServiceId = null): void
    {
        // Se a venda foi cancelada, cancelar comissões pendentes
        if ($sale->status === 'cancelled') {
            Commission::where('sale_id', $sale->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
            return;
        }

        // Se a venda foi cancelada antes e agora não está mais, recriar comissões
        if ($oldStatus === 'cancelled' && $sale->status !== 'cancelled') {
            Commission::where('sale_id', $sale->id)
                ->where('status', 'cancelled')
                ->update(['status' => 'pending']);
        }

        // Obter o funcionário correto do serviço
        $currentServiceUserId = $sale->service->user_id ?? $sale->user_id;
        $oldServiceUserId = null;

        // Se o serviço mudou, obter o funcionário do serviço antigo
        if ($oldServiceId && $oldServiceId !== $sale->service_id) {
            $oldService = Service::find($oldServiceId);
            $oldServiceUserId = $oldService->user_id ?? $oldUserId;
        } else {
            $oldServiceUserId = $oldUserId;
        }

        // Se o funcionário responsável mudou (por mudança de serviço ou user_id), atualizar comissões
        if ($oldServiceUserId !== $currentServiceUserId && $currentServiceUserId) {
            // Cancelar comissões do funcionário antigo (se ainda pendentes)
            if ($oldServiceUserId) {
                Commission::where('sale_id', $sale->id)
                    ->where('user_id', $oldServiceUserId)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            }

            // Criar comissão para o novo funcionário
            $this->createCommissionForSale($sale);
        }

        // Se o valor mudou, atualizar comissões pendentes
        if ($oldAmount !== $sale->amount) {
            $commissions = Commission::where('sale_id', $sale->id)
                ->where('status', 'pending')
                ->get();

            foreach ($commissions as $commission) {
                $newAmount = ($sale->amount * $commission->percentage) / 100;
                $commission->update(['amount' => $newAmount]);
            }
        }

        // Se não existe comissão e a venda tem funcionário, criar
        if ($currentServiceUserId && $sale->status !== 'cancelled') {
            $existingCommission = Commission::where('sale_id', $sale->id)
                ->where('user_id', $currentServiceUserId)
                ->first();

            if (!$existingCommission) {
                $this->createCommissionForSale($sale);
            }
        }
    }
}


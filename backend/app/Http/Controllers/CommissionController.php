<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Sale;
use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $commissions = Commission::query()
            ->with([
                'sale:id,amount,sale_date,status,establishment_id',
                'sale.establishment:id,name',
                'sale.service:id,name',
                'sale.client:id,name',
                'user:id,name,email',
            ])
            ->when(
                $user->role === 'admin',
                // Admin vê tudo, mas pode filtrar
                function ($query) use ($request) {
                    if ($request->query('user_id')) {
                        $query->where('user_id', $request->query('user_id'));
                    }
                    if ($request->query('sale_id')) {
                        $query->where('sale_id', $request->query('sale_id'));
                    }
                    if ($request->query('from')) {
                        $query->whereHas('sale', function ($q) use ($request) {
                            $q->whereDate('sale_date', '>=', $request->query('from'));
                        });
                    }
                    if ($request->query('to')) {
                        $query->whereHas('sale', function ($q) use ($request) {
                            $q->whereDate('sale_date', '<=', $request->query('to'));
                        });
                    }
                    if ($request->query('status')) {
                        $query->where('status', $request->query('status'));
                    }
                },
                // Owner e Employee veem apenas comissões dos seus estabelecimentos
                function ($query) use ($user, $request) {
                    if ($user->role === 'owner') {
                        // Owner vê comissões dos seus estabelecimentos
                        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
                        $query->whereHas('sale', function ($q) use ($establishmentIds) {
                            $q->whereIn('establishment_id', $establishmentIds);
                        });
                    } elseif ($user->role === 'employee') {
                        // Employee vê apenas suas próprias comissões
                        $query->where('user_id', $user->id);
                    }

                    // Aplicar filtros opcionais
                    if ($request->query('sale_id')) {
                        $query->where('sale_id', $request->query('sale_id'));
                    }
                    if ($request->query('from')) {
                        $query->whereHas('sale', function ($q) use ($request) {
                            $q->whereDate('sale_date', '>=', $request->query('from'));
                        });
                    }
                    if ($request->query('to')) {
                        $query->whereHas('sale', function ($q) use ($request) {
                            $q->whereDate('sale_date', '<=', $request->query('to'));
                        });
                    }
                    if ($request->query('status')) {
                        $query->where('status', $request->query('status'));
                    }
                }
            )
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    })
                        ->orWhereHas('sale', function ($saleQuery) use ($search) {
                            $saleQuery->whereHas('service', function ($serviceQuery) use ($search) {
                                $serviceQuery->where('name', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json($commissions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'string', 'in:pending,paid,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        // Buscar a venda para validar permissões e calcular o valor
        $sale = Sale::with('establishment')->findOrFail($data['sale_id']);

        // Verificar permissões
        if ($user->role === 'owner') {
            if ($sale->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Você não tem permissão para criar comissões para esta venda.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            // Employee não pode criar comissões
            return response()->json(
                ['message' => 'Funcionários não podem criar comissões.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Calcular o valor da comissão
        $data['amount'] = ($sale->amount * $data['percentage']) / 100;

        // Se não fornecido, definir como 'pending'
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        $commission = Commission::create($data);
        $commission->load([
            'sale:id,amount,sale_date,status,establishment_id',
            'sale.establishment:id,name',
            'sale.service:id,name',
            'sale.client:id,name',
            'user:id,name,email',
        ]);

        return response()->json($commission, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Commission $commission)
    {
        $user = request()->user();

        // Verificar permissões
        $commission->load('sale.establishment');
        if ($user->role === 'owner') {
            if ($commission->sale->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            if ($commission->user_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $commission->load([
            'sale:id,amount,sale_date,status,establishment_id,client_id,service_id',
            'sale.establishment:id,name',
            'sale.service:id,name,price',
            'sale.client:id,name',
            'user:id,name,email',
        ]);

        return response()->json($commission);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Commission $commission)
    {
        $user = $request->user();

        // Verificar permissões
        $commission->load('sale.establishment');
        if ($user->role === 'owner') {
            if ($commission->sale->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            // Employee não pode editar comissões
            return response()->json(
                ['message' => 'Funcionários não podem editar comissões.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $request->validate([
            'percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'payment_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'string', 'in:pending,paid,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        // Se o percentual foi alterado, recalcular o valor
        if (isset($data['percentage'])) {
            $sale = Sale::findOrFail($commission->sale_id);
            $data['amount'] = ($sale->amount * $data['percentage']) / 100;
        }

        $commission->update($data);
        $commission->load([
            'sale:id,amount,sale_date,status,establishment_id',
            'sale.establishment:id,name',
            'sale.service:id,name',
            'sale.client:id,name',
            'user:id,name,email',
        ]);

        return response()->json($commission);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Commission $commission)
    {
        $user = request()->user();

        // Verificar permissões
        $commission->load('sale.establishment');
        if ($user->role === 'owner') {
            if ($commission->sale->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            // Employee não pode deletar comissões
            return response()->json(
                ['message' => 'Funcionários não podem deletar comissões.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $commission->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Marcar comissão como paga
     */
    public function markAsPaid(Request $request, Commission $commission)
    {
        $user = $request->user();

        // Verificar permissões
        $commission->load('sale.establishment');
        if ($user->role === 'owner') {
            if ($commission->sale->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem marcar comissões como pagas.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $request->validate([
            'payment_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $commission->update([
            'status' => 'paid',
            'payment_date' => $data['payment_date'] ?? now()->format('Y-m-d'),
        ]);

        $commission->load([
            'sale:id,amount,sale_date,status,establishment_id',
            'sale.establishment:id,name',
            'sale.service:id,name',
            'sale.client:id,name',
            'user:id,name,email',
        ]);

        return response()->json($commission);
    }
}


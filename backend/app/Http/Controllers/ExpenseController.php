<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $expenses = Expense::query()
            ->with(['establishment:id,name'])
            ->when(
                $user->role === 'admin',
                // Admin vê tudo, mas pode filtrar
                function ($query) use ($request) {
                    if ($request->query('establishment_id')) {
                        $query->where('establishment_id', $request->query('establishment_id'));
                    }
                    if ($request->query('category')) {
                        $query->where('category', $request->query('category'));
                    }
                    if ($request->query('from')) {
                        $query->whereDate('due_date', '>=', $request->query('from'));
                    }
                    if ($request->query('to')) {
                        $query->whereDate('due_date', '<=', $request->query('to'));
                    }
                    if ($request->query('status')) {
                        $query->where('status', $request->query('status'));
                    }
                },
                // Owner e Employee veem apenas despesas dos seus estabelecimentos
                function ($query) use ($user, $request) {
                    if ($user->role === 'owner') {
                        // Owner vê despesas dos seus estabelecimentos
                        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
                        $query->whereIn('establishment_id', $establishmentIds);
                    } elseif ($user->role === 'employee') {
                        // Employee não pode ver despesas
                        $query->whereRaw('1 = 0');
                    }

                    // Aplicar filtros opcionais
                    if ($request->query('establishment_id')) {
                        $query->where('establishment_id', $request->query('establishment_id'));
                    }
                    if ($request->query('category')) {
                        $query->where('category', $request->query('category'));
                    }
                    if ($request->query('from')) {
                        $query->whereDate('due_date', '>=', $request->query('from'));
                    }
                    if ($request->query('to')) {
                        $query->whereDate('due_date', '<=', $request->query('to'));
                    }
                    if ($request->query('status')) {
                        $query->where('status', $request->query('status'));
                    }
                }
            )
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('due_date')
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 15));

        // Atualizar status de despesas vencidas
        foreach ($expenses->items() as $expense) {
            if ($expense->isOverdue() && $expense->status === 'pending') {
                $expense->update(['status' => 'overdue']);
            }
        }

        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem criar despesas.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $request->validate([
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'payment_date' => ['nullable', 'date_format:Y-m-d'],
            'payment_method' => ['required', 'string', 'in:pix,cartao_credito,cartao_debito,dinheiro,transferencia,boleto,outro'],
            'status' => ['sometimes', 'string', 'in:pending,paid,overdue'],
            'notes' => ['nullable', 'string'],
        ]);

        // Verificar permissões
        if ($user->role === 'owner') {
            $establishment = Establishment::findOrFail($data['establishment_id']);
            if ($establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Você não tem permissão para criar despesas neste estabelecimento.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        // Se não fornecido, definir como 'pending'
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        // Se payment_date foi fornecido e status não foi definido, marcar como paga
        if (isset($data['payment_date']) && !isset($data['status'])) {
            $data['status'] = 'paid';
        }

        // Verificar se está vencida
        $dueDate = Carbon::parse($data['due_date']);
        if ($data['status'] === 'pending' && $dueDate->isPast()) {
            $data['status'] = 'overdue';
        }

        $expense = Expense::create($data);
        $expense->load('establishment:id,name');

        return response()->json($expense, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        $user = request()->user();

        // Verificar permissões
        $expense->load('establishment');
        if ($user->role === 'owner') {
            if ($expense->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        } elseif ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem visualizar despesas.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return response()->json($expense);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem editar despesas.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Verificar permissões
        $expense->load('establishment');
        if ($user->role === 'owner') {
            if ($expense->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $data = $request->validate([
            'establishment_id' => ['sometimes', 'integer', 'exists:establishments,id'],
            'description' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'due_date' => ['sometimes', 'date_format:Y-m-d'],
            'payment_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'payment_method' => ['sometimes', 'string', 'in:pix,cartao_credito,cartao_debito,dinheiro,transferencia,boleto,outro'],
            'status' => ['sometimes', 'string', 'in:pending,paid,overdue'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        // Verificar permissões para establishment_id se foi alterado
        if (isset($data['establishment_id']) && $data['establishment_id'] !== $expense->establishment_id) {
            if ($user->role === 'owner') {
                $establishment = Establishment::findOrFail($data['establishment_id']);
                if ($establishment->owner_id !== $user->id) {
                    return response()->json(
                        ['message' => 'Você não tem permissão para mover despesas para este estabelecimento.'],
                        Response::HTTP_FORBIDDEN
                    );
                }
            }
        }

        // Se payment_date foi fornecido, marcar como paga
        if (isset($data['payment_date']) && !isset($data['status'])) {
            $data['status'] = 'paid';
        }

        // Verificar se está vencida
        $dueDate = isset($data['due_date'])
            ? Carbon::parse($data['due_date'])
            : Carbon::parse($expense->due_date);

        if ((!isset($data['status']) || $data['status'] === 'pending') && $dueDate->isPast()) {
            $data['status'] = 'overdue';
        }

        $expense->update($data);
        $expense->load('establishment:id,name');

        return response()->json($expense);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $user = request()->user();

        if ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem deletar despesas.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Verificar permissões
        $expense->load('establishment');
        if ($user->role === 'owner') {
            if ($expense->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $expense->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Marcar despesa como paga
     */
    public function markAsPaid(Request $request, Expense $expense)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem marcar despesas como pagas.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Verificar permissões
        $expense->load('establishment');
        if ($user->role === 'owner') {
            if ($expense->establishment->owner_id !== $user->id) {
                return response()->json(
                    ['message' => 'Não autorizado.'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $data = $request->validate([
            'payment_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $expense->update([
            'status' => 'paid',
            'payment_date' => $data['payment_date'] ?? now()->format('Y-m-d'),
        ]);

        $expense->load('establishment:id,name');

        return response()->json($expense);
    }
}


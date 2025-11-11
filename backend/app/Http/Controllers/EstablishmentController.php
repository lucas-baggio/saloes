<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EstablishmentController extends Controller
{
    protected $planLimitService;

    public function __construct(PlanLimitService $planLimitService)
    {
        $this->planLimitService = $planLimitService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Funcionários não podem ver estabelecimentos
        if ($user->role === 'employee') {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $establishments = Establishment::query()
            ->with('owner:id,name,email,role')
            ->withCount('services')
            ->when(
                $user->role !== 'admin',
                fn ($query) => $query->where('owner_id', $user->id)
            )
            ->when(
                $user->role === 'admin' && $request->query('owner_id'),
                fn ($query, $ownerId) => $query->where('owner_id', $ownerId)
            )
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json($establishments);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Funcionários não podem criar estabelecimentos
        if ($user->role === 'employee') {
            return response()->json(['message' => 'Não autorizado. Funcionários não podem criar estabelecimentos.'], Response::HTTP_FORBIDDEN);
        }

        // Verifica limite de estabelecimentos
        $limitCheck = $this->planLimitService->canCreateEstablishment($user);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'message' => $limitCheck['message'],
                'current' => $limitCheck['current'] ?? null,
                'limit' => $limitCheck['limit'] ?? null,
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        // Se for admin, pode definir owner_id manualmente, senão usa o usuário autenticado
        if ($user->role === 'admin' && $request->has('owner_id')) {
            $data['owner_id'] = $request->validate(['owner_id' => ['integer', 'exists:users,id']])['owner_id'];
        } else {
            $data['owner_id'] = $user->id;
        }

        $establishment = Establishment::create($data)->load('owner:id,name,email,role');

        return response()->json($establishment, Response::HTTP_CREATED);
    }

    public function show(Request $request, Establishment $establishment)
    {
        $user = $request->user();

        // Verificar se o usuário tem permissão para ver este estabelecimento
        if ($user->role !== 'admin' && $establishment->owner_id !== $user->id) {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $establishment->load([
            'owner:id,name,email,role',
            'services:id,name,description,price,establishment_id,user_id',
            'services.user:id,name,email,role',
        ]);

        return response()->json($establishment);
    }

    public function update(Request $request, Establishment $establishment)
    {
        $user = $request->user();

        // Verificar se o usuário tem permissão para editar este estabelecimento
        if ($user->role !== 'admin' && $establishment->owner_id !== $user->id) {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        // Apenas admin pode alterar o owner_id
        if ($user->role === 'admin' && $request->has('owner_id')) {
            $data['owner_id'] = $request->validate(['owner_id' => ['integer', 'exists:users,id']])['owner_id'];
        }

        $establishment->update($data);

        return response()->json($establishment->refresh()->load('owner:id,name,email,role'));
    }

    public function destroy(Request $request, Establishment $establishment)
    {
        $user = $request->user();

        // Verificar se o usuário tem permissão para deletar este estabelecimento
        if ($user->role !== 'admin' && $establishment->owner_id !== $user->id) {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $establishment->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}


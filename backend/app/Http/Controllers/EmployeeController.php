<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Service;
use App\Models\Establishment;
use App\Models\Scheduling;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    protected $planLimitService;

    public function __construct(PlanLimitService $planLimitService)
    {
        $this->planLimitService = $planLimitService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Apenas owners podem ver employees
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        // Pegar IDs dos estabelecimentos do owner
        $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');

        // Se não houver estabelecimentos, retornar array vazio
        if ($establishmentIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
        }

        // Pegar IDs dos employees associados aos estabelecimentos do owner (via tabela pivot)
        $employeeIdsFromEstablishments = DB::table('employee_establishment')
            ->whereIn('establishment_id', $establishmentIds)
            ->distinct()
            ->pluck('user_id');

        // Se não houver employees associados, retornar array vazio
        if ($employeeIdsFromEstablishments->isEmpty()) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
        }

        // Buscar employees com estatísticas
        $employees = User::whereIn('id', $employeeIdsFromEstablishments)
            ->where('role', 'employee')
            ->withCount([
                'services' => function ($query) use ($establishmentIds) {
                    $query->whereIn('establishment_id', $establishmentIds);
                }
            ])
            ->get()
            ->map(function ($employee) use ($establishmentIds) {
                // Calcular receita total gerada pelo employee
                $revenue = Scheduling::query()
                    ->join('services', 'schedulings.service_id', '=', 'services.id')
                    ->where('services.user_id', $employee->id)
                    ->whereIn('services.establishment_id', $establishmentIds)
                    ->select(DB::raw('COALESCE(SUM(services.price), 0) as total'))
                    ->first()
                    ->total ?? 0;

                // Contar agendamentos
                $schedulingsCount = Scheduling::query()
                    ->join('services', 'schedulings.service_id', '=', 'services.id')
                    ->where('services.user_id', $employee->id)
                    ->whereIn('services.establishment_id', $establishmentIds)
                    ->count();

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'created_at' => $employee->created_at,
                    'services_count' => $employee->services_count,
                    'revenue' => (float) $revenue,
                    'schedulings_count' => $schedulingsCount,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        return response()->json([
            'data' => $employees,
            'total' => $employees->count(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Apenas owners podem criar employees
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
        ]);

        // Verificar se o estabelecimento pertence ao owner
        $establishment = Establishment::findOrFail($data['establishment_id']);
        if ($user->role === 'owner' && $establishment->owner_id !== $user->id) {
            return response()->json(['message' => 'Estabelecimento não pertence a você.'], Response::HTTP_FORBIDDEN);
        }

        // Verifica limite de funcionários
        $limitCheck = $this->planLimitService->canAddEmployee($user);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'message' => $limitCheck['message'],
                'current' => $limitCheck['current'] ?? null,
                'limit' => $limitCheck['limit'] ?? null,
            ], Response::HTTP_FORBIDDEN);
        }

        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'employee';

        $employee = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        // Associar funcionário ao estabelecimento
        $employee->establishments()->attach($data['establishment_id']);

        // Retornar com estrutura igual ao index
        return response()->json([
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'created_at' => $employee->created_at,
            'services_count' => 0,
            'revenue' => 0.0,
            'schedulings_count' => 0,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        // Apenas owners podem ver employees
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $employee = User::findOrFail($id);

        // Verificar se o employee trabalha nos estabelecimentos do owner
        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $hasServices = Service::where('user_id', $employee->id)
                ->whereIn('establishment_id', $establishmentIds)
                ->exists();

            if (!$hasServices && $employee->role !== 'employee') {
                return response()->json(['message' => 'Funcionário não encontrado.'], Response::HTTP_NOT_FOUND);
            }
        }

        // Estatísticas detalhadas
        $establishmentIds = $user->role === 'admin'
            ? Establishment::pluck('id')
            : Establishment::where('owner_id', $user->id)->pluck('id');

        $servicesCount = Service::where('user_id', $employee->id)
            ->whereIn('establishment_id', $establishmentIds)
            ->count();

        $revenue = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->where('services.user_id', $employee->id)
            ->whereIn('services.establishment_id', $establishmentIds)
            ->select(DB::raw('COALESCE(SUM(services.price), 0) as total'))
            ->first()
            ->total ?? 0;

        $schedulingsCount = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->where('services.user_id', $employee->id)
            ->whereIn('services.establishment_id', $establishmentIds)
            ->count();

        return response()->json([
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'created_at' => $employee->created_at,
            'services_count' => $servicesCount,
            'revenue' => (float) $revenue,
            'schedulings_count' => $schedulingsCount,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();

        // Apenas owners podem atualizar employees
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $employee = User::findOrFail($id);

        // Verificar se o employee trabalha nos estabelecimentos do owner
        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $hasServices = Service::where('user_id', $employee->id)
                ->whereIn('establishment_id', $establishmentIds)
                ->exists();

            if (!$hasServices && $employee->role !== 'employee') {
                return response()->json(['message' => 'Funcionário não encontrado.'], Response::HTTP_NOT_FOUND);
            }
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($employee->id)],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
        }

        $employee->update($data);

        // Retornar com estatísticas atualizadas
        $establishmentIds = $user->role === 'admin'
            ? Establishment::pluck('id')
            : Establishment::where('owner_id', $user->id)->pluck('id');

        $servicesCount = Service::where('user_id', $employee->id)
            ->whereIn('establishment_id', $establishmentIds)
            ->count();

        $revenue = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->where('services.user_id', $employee->id)
            ->whereIn('services.establishment_id', $establishmentIds)
            ->select(DB::raw('COALESCE(SUM(services.price), 0) as total'))
            ->first()
            ->total ?? 0;

        $schedulingsCount = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->where('services.user_id', $employee->id)
            ->whereIn('services.establishment_id', $establishmentIds)
            ->count();

        return response()->json([
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'created_at' => $employee->created_at,
            'services_count' => $servicesCount,
            'revenue' => (float) $revenue,
            'schedulings_count' => $schedulingsCount,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        // Apenas owners podem deletar employees
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            return response()->json(['message' => 'Não autorizado.'], Response::HTTP_FORBIDDEN);
        }

        $employee = User::findOrFail($id);

        // Verificar se o employee trabalha nos estabelecimentos do owner
        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $hasServices = Service::where('user_id', $employee->id)
                ->whereIn('establishment_id', $establishmentIds)
                ->exists();

            if (!$hasServices && $employee->role !== 'employee') {
                return response()->json(['message' => 'Funcionário não encontrado.'], Response::HTTP_NOT_FOUND);
            }
        }

        $employee->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}


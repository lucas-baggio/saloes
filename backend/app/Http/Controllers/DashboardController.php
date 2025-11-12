<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\Service;
use App\Models\Scheduling;
use App\Models\Sale;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->query('period', 'month'); // day, week, month, year
        $startDate = $this->getStartDate($period);
        $endDate = $this->getEndDate($period);

        // Estabelecimentos
        $establishmentsQuery = Establishment::query();
        if ($user->role !== 'admin') {
            $establishmentsQuery->where('owner_id', $user->id);
        }
        $totalEstablishments = $establishmentsQuery->count();

        // Serviços
        $servicesQuery = Service::query();
        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $servicesQuery->whereIn('establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $servicesQuery->where('user_id', $user->id);
        }
        $totalServices = $servicesQuery->count();

        // Agendamentos (vendas)
        // Comparar strings de data YYYY-MM-DD diretamente
        // Usar a mesma lógica do SchedulingController: filtrar por establishment_id
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $schedulingsQuery = Scheduling::query()
            ->where('scheduled_date', '>=', $startDateStr)
            ->where('scheduled_date', '<=', $endDateStr);

        if ($user->role === 'owner') {
            // Owner vê agendamentos dos seus estabelecimentos
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $schedulingsQuery->whereIn('establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            // Employee vê agendamentos dos estabelecimentos onde trabalha
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');
            $schedulingsQuery->whereIn('establishment_id', $establishmentIds);
        }

        // Debug: Log da query e contagem
        $allSchedulings = $schedulingsQuery->get();
        $totalSchedulings = $allSchedulings->count();

        \Log::info('Dashboard Stats Query', [
            'user_id' => $user->id,
            'role' => $user->role,
            'period' => $period,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'total_schedulings' => $totalSchedulings,
            'schedulings_ids' => $allSchedulings->pluck('id')->toArray(),
            'schedulings_dates' => $allSchedulings->pluck('scheduled_date')->toArray(),
        ]);

        // Agendamentos do período anterior para comparação
        $previousStartDate = $this->getPreviousStartDate($period);
        $previousEndDate = $startDate->copy()->subDay();

        $previousStartDateStr = $previousStartDate->format('Y-m-d');
        $previousEndDateStr = $previousEndDate->format('Y-m-d');
        $previousSchedulingsQuery = Scheduling::query()
            ->where('scheduled_date', '>=', $previousStartDateStr)
            ->where('scheduled_date', '<=', $previousEndDateStr);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $previousSchedulingsQuery->whereIn('establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');
            $previousSchedulingsQuery->whereIn('establishment_id', $establishmentIds);
        }

        $previousSchedulings = $previousSchedulingsQuery->count();
        $schedulingsGrowth = $previousSchedulings > 0
            ? round((($totalSchedulings - $previousSchedulings) / $previousSchedulings) * 100, 2)
            : ($totalSchedulings > 0 ? 100 : 0);

        // Receita total (valor gerado)
        // Filtrar por establishment_id diretamente nos schedulings
        $revenueQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->where('schedulings.scheduled_date', '>=', $startDateStr)
            ->where('schedulings.scheduled_date', '<=', $endDateStr)
            ->select(DB::raw('COALESCE(SUM(services.price), 0) as total'));

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $revenueQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');
            $revenueQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        }

        $totalRevenue = $revenueQuery->first()->total ?? 0;

        // Verificar receita manualmente para debug (carregar relacionamento service)
        $allSchedulings->load('service');
        $manualRevenue = $allSchedulings->sum(function($scheduling) {
            return $scheduling->service->price ?? 0;
        });

        \Log::info('Dashboard Revenue Result', [
            'total_revenue_query' => $totalRevenue,
            'manual_revenue' => $manualRevenue,
            'schedulings_with_services' => $allSchedulings->filter(fn($s) => $s->service)->count(),
        ]);

        // Receita do período anterior
        $previousRevenueQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->where('schedulings.scheduled_date', '>=', $previousStartDateStr)
            ->where('schedulings.scheduled_date', '<=', $previousEndDateStr)
            ->select(DB::raw('COALESCE(SUM(services.price), 0) as total'));

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $previousRevenueQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');
            $previousRevenueQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        }

        $previousRevenue = $previousRevenueQuery->first()->total ?? 0;
        $revenueGrowth = $previousRevenue > 0
            ? round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 2)
            : ($totalRevenue > 0 ? 100 : 0);

        // Ticket médio
        $averageTicket = $totalSchedulings > 0 ? round($totalRevenue / $totalSchedulings, 2) : 0;

        return response()->json([
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'establishments' => $totalEstablishments,
            'services' => $totalServices,
            'schedulings' => [
                'total' => $totalSchedulings,
                'growth' => $schedulingsGrowth,
                'previous' => $previousSchedulings,
            ],
            'revenue' => [
                'total' => (float) $totalRevenue,
                'growth' => $revenueGrowth,
                'previous' => (float) $previousRevenue,
            ],
            'average_ticket' => $averageTicket,
        ]);
    }

    public function revenueChart(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->query('period', 'month'); // day, week, month, year
        $startDate = $this->getStartDate($period);
        $endDate = $this->getEndDate($period);

        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $revenueQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->where('schedulings.scheduled_date', '>=', $startDateStr)
            ->where('schedulings.scheduled_date', '<=', $endDateStr);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $revenueQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');
            $revenueQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        }

        // Group by based on period
        // Como scheduled_date é string YYYY-MM-DD, podemos usar SUBSTRING para agrupar
        if ($period === 'day') {
            // Para dia, agrupar por hora (assumindo que temos hora em scheduled_time)
            $revenueQuery->select(
                DB::raw("CONCAT(schedulings.scheduled_date, ' ', SUBSTRING(schedulings.scheduled_time, 1, 2), ':00') as period"),
                DB::raw('COALESCE(SUM(services.price), 0) as revenue'),
                DB::raw('COUNT(*) as count')
            )->groupBy('schedulings.scheduled_date', DB::raw("SUBSTRING(schedulings.scheduled_time, 1, 2)"));
        } elseif ($period === 'week' || $period === 'month') {
            // Para semana/mês, agrupar por dia (scheduled_date já é YYYY-MM-DD)
            $revenueQuery->select(
                DB::raw('schedulings.scheduled_date as period'),
                DB::raw('COALESCE(SUM(services.price), 0) as revenue'),
                DB::raw('COUNT(*) as count')
            )->groupBy('schedulings.scheduled_date');
        } else { // year
            // Para ano, agrupar por mês (usar SUBSTRING para pegar YYYY-MM)
            $revenueQuery->select(
                DB::raw("SUBSTRING(schedulings.scheduled_date, 1, 7) as period"),
                DB::raw('COALESCE(SUM(services.price), 0) as revenue'),
                DB::raw('COUNT(*) as count')
            )->groupBy(DB::raw("SUBSTRING(schedulings.scheduled_date, 1, 7)"));
        }

        $data = $revenueQuery->orderBy('period')->get();

        \Log::info('Dashboard Revenue Chart Query', [
            'user_id' => $user->id,
            'role' => $user->role,
            'period' => $period,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'data_count' => $data->count(),
            'data' => $data->toArray(),
            'labels' => $data->pluck('period')->toArray(),
            'revenue' => $data->pluck('revenue')->toArray(),
            'count' => $data->pluck('count')->toArray(),
        ]);

        return response()->json([
            'labels' => $data->pluck('period')->toArray(),
            'revenue' => $data->pluck('revenue')->map(fn($v) => (float) $v)->toArray(),
            'count' => $data->pluck('count')->toArray(),
        ]);
    }

    public function topServices(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->query('limit', 5);
        $period = $request->query('period', 'month');
        $startDate = $this->getStartDate($period);
        $endDate = $this->getEndDate($period);

        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $topServicesQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->join('establishments', 'services.establishment_id', '=', 'establishments.id')
            ->where('schedulings.scheduled_date', '>=', $startDateStr)
            ->where('schedulings.scheduled_date', '<=', $endDateStr)
            ->select(
                'services.id',
                'services.name',
                'establishments.name as establishment_name',
                DB::raw('COUNT(*) as schedulings_count'),
                DB::raw('COALESCE(SUM(services.price), 0) as total_revenue'),
                DB::raw('COALESCE(AVG(services.price), 0) as average_price')
            )
            ->groupBy('services.id', 'services.name', 'establishments.name')
            ->orderByDesc('schedulings_count')
            ->limit($limit);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $topServicesQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');
            $topServicesQuery->whereIn('schedulings.establishment_id', $establishmentIds);
        }

        $topServices = $topServicesQuery->get()->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'establishment' => $service->establishment_name,
                'schedulings' => $service->schedulings_count,
                'revenue' => (float) $service->total_revenue,
                'average_price' => (float) $service->average_price,
            ];
        });

        return response()->json($topServices);
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    private function getEndDate(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->endOfDay(),
            'week' => now()->endOfWeek(),
            'month' => now()->endOfMonth(),
            'year' => now()->endOfYear(),
            default => now()->endOfMonth(),
        };
    }

    private function getPreviousStartDate(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->subDay()->startOfDay(),
            'week' => now()->subWeek()->startOfWeek(),
            'month' => now()->subMonth()->startOfMonth(),
            'year' => now()->subYear()->startOfYear(),
            default => now()->subMonth()->startOfMonth(),
        };
    }

    /**
     * Retorna dados financeiros (entradas, saídas, saldo)
     */
    public function financial(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->query('period', 'month');
        $startDate = $this->getStartDate($period);
        $endDate = $this->getEndDate($period);

        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');

        // Entradas: Vendas pagas
        $salesQuery = Sale::query()
            ->where('status', 'paid')
            ->whereDate('sale_date', '>=', $startDateStr)
            ->whereDate('sale_date', '<=', $endDateStr);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $salesQuery->whereIn('establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $establishmentIds = DB::table('employee_establishment')
                ->where('user_id', $user->id)
                ->pluck('establishment_id');
            $salesQuery->whereIn('establishment_id', $establishmentIds);
        }

        $totalIncome = $salesQuery->sum('amount');

        // Saídas: Despesas pagas
        $expensesQuery = Expense::query()
            ->where('status', 'paid')
            ->whereDate('payment_date', '>=', $startDateStr)
            ->whereDate('payment_date', '<=', $endDateStr);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $expensesQuery->whereIn('establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            // Employees não podem ver despesas
            $expensesQuery->whereRaw('1 = 0');
        }

        $totalExpenses = $expensesQuery->sum('amount');

        // Saldo
        $balance = $totalIncome - $totalExpenses;

        // Despesas pendentes (para alertas)
        $pendingExpensesQuery = Expense::query()
            ->whereIn('status', ['pending', 'overdue'])
            ->whereDate('due_date', '<=', $endDateStr);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $pendingExpensesQuery->whereIn('establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $pendingExpensesQuery->whereRaw('1 = 0');
        }

        $pendingExpensesCount = $pendingExpensesQuery->count();
        $pendingExpensesAmount = $pendingExpensesQuery->sum('amount');

        return response()->json([
            'income' => (float) $totalIncome,
            'expenses' => (float) $totalExpenses,
            'balance' => (float) $balance,
            'pending_expenses_count' => $pendingExpensesCount,
            'pending_expenses_amount' => (float) $pendingExpensesAmount,
            'period' => $period,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
        ]);
    }

}


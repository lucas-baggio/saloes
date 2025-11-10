<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\Service;
use App\Models\Scheduling;
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
        $endDate = now();

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
        $schedulingsQuery = Scheduling::query()
            ->whereBetween('scheduled_date', [$startDate, $endDate]);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $serviceIds = Service::whereIn('establishment_id', $establishmentIds)->pluck('id');
            $schedulingsQuery->whereIn('service_id', $serviceIds);
        } elseif ($user->role === 'employee') {
            $serviceIds = Service::where('user_id', $user->id)->pluck('id');
            $schedulingsQuery->whereIn('service_id', $serviceIds);
        }

        $totalSchedulings = $schedulingsQuery->count();

        // Agendamentos do período anterior para comparação
        $previousStartDate = $this->getPreviousStartDate($period);
        $previousEndDate = $startDate->copy()->subDay();

        $previousSchedulingsQuery = Scheduling::query()
            ->whereBetween('scheduled_date', [$previousStartDate, $previousEndDate]);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $serviceIds = Service::whereIn('establishment_id', $establishmentIds)->pluck('id');
            $previousSchedulingsQuery->whereIn('service_id', $serviceIds);
        } elseif ($user->role === 'employee') {
            $serviceIds = Service::where('user_id', $user->id)->pluck('id');
            $previousSchedulingsQuery->whereIn('service_id', $serviceIds);
        }

        $previousSchedulings = $previousSchedulingsQuery->count();
        $schedulingsGrowth = $previousSchedulings > 0
            ? round((($totalSchedulings - $previousSchedulings) / $previousSchedulings) * 100, 2)
            : ($totalSchedulings > 0 ? 100 : 0);

        // Receita total (valor gerado)
        $revenueQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->whereBetween('schedulings.scheduled_date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(SUM(services.price), 0) as total'));

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $revenueQuery->whereIn('services.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $revenueQuery->where('services.user_id', $user->id);
        }

        $totalRevenue = $revenueQuery->first()->total ?? 0;

        // Receita do período anterior
        $previousRevenueQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->whereBetween('schedulings.scheduled_date', [$previousStartDate, $previousEndDate])
            ->select(DB::raw('COALESCE(SUM(services.price), 0) as total'));

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $previousRevenueQuery->whereIn('services.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $previousRevenueQuery->where('services.user_id', $user->id);
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
        $endDate = now();

        $revenueQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->whereBetween('schedulings.scheduled_date', [$startDate, $endDate]);

        if ($user->role === 'owner') {
            $establishmentIds = Establishment::where('owner_id', $user->id)->pluck('id');
            $revenueQuery->whereIn('services.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $revenueQuery->where('services.user_id', $user->id);
        }

        // Group by based on period
        if ($period === 'day') {
            $revenueQuery->select(
                DB::raw("DATE_FORMAT(schedulings.scheduled_date, '%Y-%m-%d %H:00') as period"),
                DB::raw('COALESCE(SUM(services.price), 0) as revenue'),
                DB::raw('COUNT(*) as count')
            )->groupBy(DB::raw("DATE_FORMAT(schedulings.scheduled_date, '%Y-%m-%d %H:00')"));
        } elseif ($period === 'week' || $period === 'month') {
            $revenueQuery->select(
                DB::raw("DATE_FORMAT(schedulings.scheduled_date, '%Y-%m-%d') as period"),
                DB::raw('COALESCE(SUM(services.price), 0) as revenue'),
                DB::raw('COUNT(*) as count')
            )->groupBy(DB::raw("DATE_FORMAT(schedulings.scheduled_date, '%Y-%m-%d')"));
        } else { // year
            $revenueQuery->select(
                DB::raw("DATE_FORMAT(schedulings.scheduled_date, '%Y-%m') as period"),
                DB::raw('COALESCE(SUM(services.price), 0) as revenue'),
                DB::raw('COUNT(*) as count')
            )->groupBy(DB::raw("DATE_FORMAT(schedulings.scheduled_date, '%Y-%m')"));
        }

        $data = $revenueQuery->orderBy('period')->get();

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
        $endDate = now();

        $topServicesQuery = Scheduling::query()
            ->join('services', 'schedulings.service_id', '=', 'services.id')
            ->join('establishments', 'services.establishment_id', '=', 'establishments.id')
            ->whereBetween('schedulings.scheduled_date', [$startDate, $endDate])
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
            $topServicesQuery->whereIn('services.establishment_id', $establishmentIds);
        } elseif ($user->role === 'employee') {
            $topServicesQuery->where('services.user_id', $user->id);
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

}


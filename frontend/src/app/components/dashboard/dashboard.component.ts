import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import {
  DashboardService,
  DashboardStats,
  RevenueChart,
  TopService,
} from '../../services/dashboard.service';
import { AuthService } from '../../services/auth.service';
import { SchedulingService } from '../../services/scheduling.service';
import { Scheduling } from '../../models/service.model';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss',
})
export class DashboardComponent implements OnInit {
  stats: DashboardStats | null = null;
  revenueChart: RevenueChart | null = null;
  topServices: TopService[] = [];
  schedulings: Scheduling[] = [];
  pastSchedulings: Scheduling[] = [];
  todaySchedulings: Scheduling[] = [];
  futureSchedulings: Scheduling[] = [];
  totalSchedulings = 0;
  employeeTopServices: Array<{ name: string; count: number }> = [];
  loading = true;
  selectedPeriod = 'month';
  user: any = null;
  isEmployee = false;

  constructor(
    private dashboardService: DashboardService,
    public authService: AuthService,
    private schedulingService: SchedulingService
  ) {}

  ngOnInit() {
    this.user = this.authService.getCurrentUser();
    this.isEmployee = this.user?.role === 'employee';

    if (this.isEmployee) {
      this.loadEmployeeDashboard();
    } else {
      this.loadDashboardData();
    }
  }

  loadEmployeeDashboard() {
    this.loading = true;
    this.schedulingService.getAll().subscribe({
      next: (data) => {
        const schedulings = data.data || data || [];
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Separar agendamentos por data (sem considerar hora para a classificação)
        this.pastSchedulings = schedulings
          .filter((s: Scheduling) => {
            const scheduleDate = new Date(s.scheduled_date);
            scheduleDate.setHours(0, 0, 0, 0);
            return scheduleDate < today;
          })
          .sort((a: Scheduling, b: Scheduling) => {
            const dateA = new Date(`${a.scheduled_date}T${a.scheduled_time}`);
            const dateB = new Date(`${b.scheduled_date}T${b.scheduled_time}`);
            return dateB.getTime() - dateA.getTime();
          });

        this.todaySchedulings = schedulings
          .filter((s: Scheduling) => {
            const scheduleDate = new Date(s.scheduled_date);
            scheduleDate.setHours(0, 0, 0, 0);
            return scheduleDate.getTime() === today.getTime();
          })
          .sort((a: Scheduling, b: Scheduling) => {
            const dateA = new Date(`${a.scheduled_date}T${a.scheduled_time}`);
            const dateB = new Date(`${b.scheduled_date}T${b.scheduled_time}`);
            return dateA.getTime() - dateB.getTime();
          });

        this.futureSchedulings = schedulings
          .filter((s: Scheduling) => {
            const scheduleDate = new Date(s.scheduled_date);
            scheduleDate.setHours(0, 0, 0, 0);
            return scheduleDate > today;
          })
          .sort((a: Scheduling, b: Scheduling) => {
            const dateA = new Date(`${a.scheduled_date}T${a.scheduled_time}`);
            const dateB = new Date(`${b.scheduled_date}T${b.scheduled_time}`);
            return dateA.getTime() - dateB.getTime();
          });

        // Todos os agendamentos ordenados
        this.schedulings = [
          ...this.todaySchedulings,
          ...this.futureSchedulings,
          ...this.pastSchedulings,
        ];

        // Total de agendamentos
        this.totalSchedulings = schedulings.length;

        // Calcular serviços mais vendidos
        this.calculateTopServices(schedulings);

        this.loading = false;
      },
      error: () => {
        this.loading = false;
      },
    });
  }

  calculateTopServices(schedulings: Scheduling[]) {
    const serviceCount: { [key: string]: { name: string; count: number } } = {};

    schedulings.forEach((scheduling: Scheduling) => {
      const serviceName =
        scheduling.service?.name || `Serviço #${scheduling.service_id}`;
      if (serviceCount[serviceName]) {
        serviceCount[serviceName].count++;
      } else {
        serviceCount[serviceName] = { name: serviceName, count: 1 };
      }
    });

    // Converter para array e ordenar por quantidade
    this.employeeTopServices = Object.values(serviceCount)
      .sort((a, b) => b.count - a.count)
      .slice(0, 5); // Top 5
  }

  loadDashboardData() {
    this.loading = true;

    this.dashboardService.getStats(this.selectedPeriod).subscribe({
      next: (data) => {
        this.stats = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      },
    });

    this.dashboardService.getRevenueChart(this.selectedPeriod).subscribe({
      next: (data) => {
        this.revenueChart = data;
        this.renderChart();
      },
    });

    this.dashboardService.getTopServices(this.selectedPeriod, 5).subscribe({
      next: (data) => {
        this.topServices = data;
      },
    });
  }

  changePeriod(period: string) {
    this.selectedPeriod = period;
    this.loadDashboardData();
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value);
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('pt-BR');
  }

  formatTime(time: string): string {
    return time;
  }

  getServiceName(serviceId: number): string {
    // Para funcionários, podemos buscar o nome do serviço se necessário
    return `Serviço #${serviceId}`;
  }

  getGrowthColor(growth: number): string {
    if (growth > 0) return 'text-green-600';
    if (growth < 0) return 'text-red-600';
    return 'text-gray-600';
  }

  getGrowthIcon(growth: number): string {
    if (growth > 0) return '↑';
    if (growth < 0) return '↓';
    return '→';
  }

  renderChart() {
    if (!this.revenueChart) return;

    // Simple bar chart using CSS
    const maxRevenue = Math.max(...this.revenueChart.revenue, 1);
    this.chartData = this.revenueChart.labels.map((label, index) => ({
      label: this.formatChartLabel(label),
      value: this.revenueChart!.revenue[index],
      percentage: (this.revenueChart!.revenue[index] / maxRevenue) * 100,
      count: this.revenueChart!.count[index],
    }));
  }

  chartData: Array<{
    label: string;
    value: number;
    percentage: number;
    count: number;
  }> = [];

  formatChartLabel(label: string): string {
    // Format based on period - label comes as YYYY-MM-DD or YYYY-MM-DD HH:00 or YYYY-MM
    try {
      if (this.selectedPeriod === 'day') {
        // Format: YYYY-MM-DD HH:00
        const [datePart, timePart] = label.split(' ');
        if (timePart) {
          const [hours] = timePart.split(':');
          return `${hours}h`;
        }
        return new Date(label).toLocaleTimeString('pt-BR', {
          hour: '2-digit',
          minute: '2-digit',
        });
      }
      if (this.selectedPeriod === 'month' || this.selectedPeriod === 'week') {
        // Format: YYYY-MM-DD
        // Parse manualmente e formatar diretamente para evitar problemas de timezone
        const [year, month, day] = label.split('-').map(Number);
        const months = [
          'jan',
          'fev',
          'mar',
          'abr',
          'mai',
          'jun',
          'jul',
          'ago',
          'set',
          'out',
          'nov',
          'dez',
        ];
        return `${day.toString().padStart(2, '0')} de ${months[month - 1]}.`;
      }
      if (this.selectedPeriod === 'year') {
        // Format: YYYY-MM
        // Parse manualmente e formatar diretamente para evitar problemas de timezone
        const [year, month] = label.split('-').map(Number);
        const months = [
          'jan',
          'fev',
          'mar',
          'abr',
          'mai',
          'jun',
          'jul',
          'ago',
          'set',
          'out',
          'nov',
          'dez',
        ];
        return `${months[month - 1]} ${year}`;
      }
    } catch (e) {
      // Fallback to original label if parsing fails
    }
    return label;
  }

  // Expose Math to template
  Math = Math;
}

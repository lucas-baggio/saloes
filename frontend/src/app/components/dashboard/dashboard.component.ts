import {
  Component,
  OnInit,
  AfterViewInit,
  ViewChild,
  ElementRef,
  OnDestroy,
} from '@angular/core';
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
import { Chart, registerables } from 'chart.js';
import { BreadcrumbsComponent, BreadcrumbItem } from '../breadcrumbs/breadcrumbs.component';

Chart.register(...registerables);

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink, BreadcrumbsComponent],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss',
})
export class DashboardComponent implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('revenueChartCanvas')
  revenueChartCanvas!: ElementRef<HTMLCanvasElement>;
  @ViewChild('servicesChartCanvas')
  servicesChartCanvas!: ElementRef<HTMLCanvasElement>;
  @ViewChild('statusChartCanvas')
  statusChartCanvas!: ElementRef<HTMLCanvasElement>;

  stats: DashboardStats | null = null;
  revenueChart: RevenueChart | null = null;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard' },
  ];
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

  private revenueChartInstance: Chart | null = null;
  private servicesChartInstance: Chart | null = null;
  private statusChartInstance: Chart | null = null;

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

  ngAfterViewInit() {
    // Charts will be created after data loads
  }

  ngOnDestroy() {
    // Destroy charts to prevent memory leaks
    if (this.revenueChartInstance) {
      this.revenueChartInstance.destroy();
    }
    if (this.servicesChartInstance) {
      this.servicesChartInstance.destroy();
    }
    if (this.statusChartInstance) {
      this.statusChartInstance.destroy();
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
        // Wait for view to be ready
        setTimeout(() => this.createRevenueChart(), 100);
      },
    });

    this.dashboardService.getTopServices(this.selectedPeriod, 5).subscribe({
      next: (data) => {
        this.topServices = data;
        // Wait for view to be ready
        setTimeout(() => this.createServicesChart(), 100);
      },
    });

    // Load schedulings for status chart
    this.schedulingService.getAll().subscribe({
      next: (data) => {
        const schedulings = data.data || data || [];
        // Wait for view to be ready
        setTimeout(() => this.createStatusChart(schedulings), 100);
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

  createRevenueChart() {
    if (!this.revenueChart || !this.revenueChartCanvas) return;

    // Destroy existing chart
    if (this.revenueChartInstance) {
      this.revenueChartInstance.destroy();
    }

    const labels = this.revenueChart.labels.map((label) =>
      this.formatChartLabel(label)
    );

    this.revenueChartInstance = new Chart(
      this.revenueChartCanvas.nativeElement,
      {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Receita (R$)',
              data: this.revenueChart.revenue,
              borderColor: 'rgb(59, 130, 246)',
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              tension: 0.4,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              position: 'top',
            },
            tooltip: {
              callbacks: {
                label: (context) => {
                  const value = context.parsed.y ?? 0;
                  return `Receita: ${this.formatCurrency(value)}`;
                },
              },
            },
          },
          scales: {
            x: {
              ticks: {
                maxRotation: 45,
                minRotation: 45,
                font: {
                  size: 10,
                },
              },
            },
            y: {
              beginAtZero: true,
              ticks: {
                callback: (value) => {
                  return this.formatCurrency(value as number);
                },
                font: {
                  size: 10,
                },
              },
            },
          },
        },
      }
    );
  }

  createServicesChart() {
    if (
      !this.topServices ||
      this.topServices.length === 0 ||
      !this.servicesChartCanvas
    )
      return;

    // Destroy existing chart
    if (this.servicesChartInstance) {
      this.servicesChartInstance.destroy();
    }

    const labels = this.topServices.map((s) => s.name);
    const revenues = this.topServices.map((s) => s.revenue);

    this.servicesChartInstance = new Chart(
      this.servicesChartCanvas.nativeElement,
      {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Receita por Serviço (R$)',
              data: revenues,
              backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)',
              ],
              borderColor: [
                'rgb(59, 130, 246)',
                'rgb(16, 185, 129)',
                'rgb(245, 158, 11)',
                'rgb(239, 68, 68)',
                'rgb(139, 92, 246)',
              ],
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
            tooltip: {
              callbacks: {
                label: (context) => {
                  const value = context.parsed.y ?? 0;
                  return `Receita: ${this.formatCurrency(value)}`;
                },
              },
            },
          },
          scales: {
            x: {
              ticks: {
                maxRotation: 45,
                minRotation: 45,
                font: {
                  size: 10,
                },
              },
            },
            y: {
              beginAtZero: true,
              ticks: {
                callback: (value) => {
                  return this.formatCurrency(value as number);
                },
                font: {
                  size: 10,
                },
              },
            },
          },
        },
      }
    );
  }

  createStatusChart(schedulings: Scheduling[]) {
    if (!this.statusChartCanvas) return;

    // Destroy existing chart
    if (this.statusChartInstance) {
      this.statusChartInstance.destroy();
    }

    const statusCount: { [key: string]: number } = {
      pending: 0,
      confirmed: 0,
      completed: 0,
      cancelled: 0,
    };

    schedulings.forEach((s: Scheduling) => {
      const status = s.status || 'pending';
      if (statusCount[status] !== undefined) {
        statusCount[status]++;
      }
    });

    const labels = ['Pendente', 'Confirmado', 'Concluído', 'Cancelado'];
    const data = [
      statusCount['pending'],
      statusCount['confirmed'],
      statusCount['completed'],
      statusCount['cancelled'],
    ];

    this.statusChartInstance = new Chart(this.statusChartCanvas.nativeElement, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [
          {
            data: data,
            backgroundColor: [
              'rgba(245, 158, 11, 0.8)',
              'rgba(59, 130, 246, 0.8)',
              'rgba(16, 185, 129, 0.8)',
              'rgba(239, 68, 68, 0.8)',
            ],
            borderColor: [
              'rgb(245, 158, 11)',
              'rgb(59, 130, 246)',
              'rgb(16, 185, 129)',
              'rgb(239, 68, 68)',
            ],
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              padding: 8,
              font: {
                size: 11,
              },
            },
          },
          tooltip: {
            callbacks: {
              label: (context) => {
                const total = data.reduce((a, b) => a + b, 0);
                const percentage =
                  total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                return `${context.label}: ${context.parsed} (${percentage}%)`;
              },
            },
          },
        },
      },
    });
  }

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

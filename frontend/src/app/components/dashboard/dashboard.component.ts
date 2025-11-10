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
  loading = true;
  selectedPeriod = 'month';

  constructor(
    private dashboardService: DashboardService,
    public authService: AuthService
  ) {}

  ngOnInit() {
    this.loadDashboardData();
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
        const date = new Date(label);
        return date.toLocaleDateString('pt-BR', {
          day: '2-digit',
          month: 'short',
        });
      }
      if (this.selectedPeriod === 'year') {
        // Format: YYYY-MM
        const date = new Date(label + '-01');
        return date.toLocaleDateString('pt-BR', {
          month: 'short',
          year: 'numeric',
        });
      }
    } catch (e) {
      // Fallback to original label if parsing fails
    }
    return label;
  }

  // Expose Math to template
  Math = Math;
}

import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface DashboardStats {
  period: string;
  start_date: string;
  end_date: string;
  establishments: number;
  services: number;
  schedulings: {
    total: number;
    growth: number;
    previous: number;
  };
  revenue: {
    total: number;
    growth: number;
    previous: number;
  };
  average_ticket: number;
}

export interface RevenueChart {
  labels: string[];
  revenue: number[];
  count: number[];
}

export interface TopService {
  id: number;
  name: string;
  establishment: string;
  schedulings: number;
  revenue: number;
  average_price: number;
}

export interface FinancialData {
  income: number;
  expenses: number;
  balance: number;
  pending_expenses_count: number;
  pending_expenses_amount: number;
  period: string;
  start_date: string;
  end_date: string;
}

@Injectable({
  providedIn: 'root',
})
export class DashboardService {
  constructor(private api: ApiService) {}

  getStats(period: string = 'month'): Observable<DashboardStats> {
    return this.api.get<DashboardStats>(`dashboard/stats?period=${period}`);
  }

  getRevenueChart(period: string = 'month'): Observable<RevenueChart> {
    return this.api.get<RevenueChart>(
      `dashboard/revenue-chart?period=${period}`
    );
  }

  getTopServices(
    period: string = 'month',
    limit: number = 5
  ): Observable<TopService[]> {
    return this.api.get<TopService[]>(
      `dashboard/top-services?period=${period}&limit=${limit}`
    );
  }

  getFinancial(period: string = 'month'): Observable<FinancialData> {
    return this.api.get<FinancialData>(`dashboard/financial?period=${period}`);
  }
}

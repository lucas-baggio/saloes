import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Expense } from '../models/expense.model';

@Injectable({
  providedIn: 'root',
})
export class ExpenseService {
  constructor(private api: ApiService) {}

  getAll(params?: {
    search?: string;
    establishment_id?: number;
    category?: string;
    from?: string;
    to?: string;
    status?: string;
    per_page?: number;
  }): Observable<any> {
    let query = 'expenses';
    if (params) {
      const queryParams = new URLSearchParams();
      if (params.search) queryParams.append('search', params.search);
      if (params.establishment_id)
        queryParams.append(
          'establishment_id',
          params.establishment_id.toString()
        );
      if (params.category) queryParams.append('category', params.category);
      if (params.from) queryParams.append('from', params.from);
      if (params.to) queryParams.append('to', params.to);
      if (params.status) queryParams.append('status', params.status);
      if (params.per_page)
        queryParams.append('per_page', params.per_page.toString());
      if (queryParams.toString()) query += '?' + queryParams.toString();
    }
    return this.api.get<any>(query);
  }

  getById(id: number): Observable<Expense> {
    return this.api.get<Expense>(`expenses/${id}`);
  }

  create(expense: Partial<Expense>): Observable<Expense> {
    return this.api.post<Expense>('expenses', expense);
  }

  update(id: number, expense: Partial<Expense>): Observable<Expense> {
    return this.api.put<Expense>(`expenses/${id}`, expense);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`expenses/${id}`);
  }

  markAsPaid(id: number, paymentDate?: string): Observable<Expense> {
    const body: any = {};
    if (paymentDate) {
      body.payment_date = paymentDate;
    }
    return this.api.post<Expense>(`expenses/${id}/mark-as-paid`, body);
  }
}

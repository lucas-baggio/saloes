import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Commission } from '../models/commission.model';

@Injectable({
  providedIn: 'root',
})
export class CommissionService {
  constructor(private api: ApiService) {}

  getAll(params?: {
    search?: string;
    user_id?: number;
    sale_id?: number;
    from?: string;
    to?: string;
    status?: string;
    per_page?: number;
  }): Observable<any> {
    let query = 'commissions';
    if (params) {
      const queryParams = new URLSearchParams();
      if (params.search) queryParams.append('search', params.search);
      if (params.user_id)
        queryParams.append('user_id', params.user_id.toString());
      if (params.sale_id)
        queryParams.append('sale_id', params.sale_id.toString());
      if (params.from) queryParams.append('from', params.from);
      if (params.to) queryParams.append('to', params.to);
      if (params.status) queryParams.append('status', params.status);
      if (params.per_page)
        queryParams.append('per_page', params.per_page.toString());
      if (queryParams.toString()) query += '?' + queryParams.toString();
    }
    return this.api.get<any>(query);
  }

  getById(id: number): Observable<Commission> {
    return this.api.get<Commission>(`commissions/${id}`);
  }

  create(commission: Partial<Commission>): Observable<Commission> {
    return this.api.post<Commission>('commissions', commission);
  }

  update(id: number, commission: Partial<Commission>): Observable<Commission> {
    return this.api.put<Commission>(`commissions/${id}`, commission);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`commissions/${id}`);
  }

  markAsPaid(id: number, paymentDate?: string): Observable<Commission> {
    const body: any = {};
    if (paymentDate) {
      body.payment_date = paymentDate;
    }
    return this.api.post<Commission>(`commissions/${id}/mark-as-paid`, body);
  }
}

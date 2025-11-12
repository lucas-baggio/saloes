import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Sale } from '../models/sale.model';

@Injectable({
  providedIn: 'root',
})
export class SaleService {
  constructor(private api: ApiService) {}

  getAll(params?: {
    search?: string;
    establishment_id?: number;
    user_id?: number;
    client_id?: number;
    from?: string;
    to?: string;
    status?: string;
    payment_method?: string;
    per_page?: number;
  }): Observable<any> {
    let query = 'sales';
    if (params) {
      const queryParams = new URLSearchParams();
      if (params.search) queryParams.append('search', params.search);
      if (params.establishment_id)
        queryParams.append(
          'establishment_id',
          params.establishment_id.toString()
        );
      if (params.user_id)
        queryParams.append('user_id', params.user_id.toString());
      if (params.client_id)
        queryParams.append('client_id', params.client_id.toString());
      if (params.from) queryParams.append('from', params.from);
      if (params.to) queryParams.append('to', params.to);
      if (params.status) queryParams.append('status', params.status);
      if (params.payment_method)
        queryParams.append('payment_method', params.payment_method);
      if (params.per_page)
        queryParams.append('per_page', params.per_page.toString());
      if (queryParams.toString()) query += '?' + queryParams.toString();
    }
    return this.api.get<any>(query);
  }

  getById(id: number): Observable<Sale> {
    return this.api.get<Sale>(`sales/${id}`);
  }

  create(sale: Partial<Sale>): Observable<Sale> {
    return this.api.post<Sale>('sales', sale);
  }

  update(id: number, sale: Partial<Sale>): Observable<Sale> {
    return this.api.put<Sale>(`sales/${id}`, sale);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`sales/${id}`);
  }
}

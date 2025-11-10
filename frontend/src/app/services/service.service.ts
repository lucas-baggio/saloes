import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Service } from '../models/service.model';

@Injectable({
  providedIn: 'root',
})
export class ServiceService {
  constructor(private api: ApiService) {}

  getAll(params?: {
    establishment_id?: number;
    user_id?: number;
    per_page?: number;
  }): Observable<any> {
    let query = 'services';
    if (params) {
      const queryParams = new URLSearchParams();
      if (params.establishment_id)
        queryParams.append(
          'establishment_id',
          params.establishment_id.toString()
        );
      if (params.user_id)
        queryParams.append('user_id', params.user_id.toString());
      if (params.per_page)
        queryParams.append('per_page', params.per_page.toString());
      if (queryParams.toString()) query += '?' + queryParams.toString();
    }
    return this.api.get<any>(query);
  }

  getById(id: number): Observable<Service> {
    return this.api.get<Service>(`services/${id}`);
  }

  create(data: Partial<Service>): Observable<Service> {
    return this.api.post<Service>('services', data);
  }

  update(id: number, data: Partial<Service>): Observable<Service> {
    return this.api.put<Service>(`services/${id}`, data);
  }

  delete(id: number): Observable<any> {
    return this.api.delete(`services/${id}`);
  }
}

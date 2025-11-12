import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Client } from '../models/client.model';

@Injectable({
  providedIn: 'root',
})
export class ClientService {
  constructor(private api: ApiService) {}

  getAll(params?: { search?: string; per_page?: number }): Observable<any> {
    let query = 'clients';
    if (params) {
      const queryParams = new URLSearchParams();
      if (params.search) queryParams.append('search', params.search);
      if (params.per_page)
        queryParams.append('per_page', params.per_page.toString());
      if (queryParams.toString()) query += '?' + queryParams.toString();
    }
    return this.api.get<any>(query);
  }

  getById(id: number): Observable<Client> {
    return this.api.get<Client>(`clients/${id}`);
  }

  create(client: Partial<Client>): Observable<Client> {
    return this.api.post<Client>('clients', client);
  }

  update(id: number, client: Partial<Client>): Observable<Client> {
    return this.api.put<Client>(`clients/${id}`, client);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`clients/${id}`);
  }
}

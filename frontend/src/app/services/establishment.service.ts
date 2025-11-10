import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Establishment } from '../models/establishment.model';

@Injectable({
  providedIn: 'root',
})
export class EstablishmentService {
  constructor(private api: ApiService) {}

  getAll(): Observable<any> {
    return this.api.get<any>('establishments');
  }

  getById(id: number): Observable<Establishment> {
    return this.api.get<Establishment>(`establishments/${id}`);
  }

  create(data: Partial<Establishment>): Observable<Establishment> {
    return this.api.post<Establishment>('establishments', data);
  }

  update(id: number, data: Partial<Establishment>): Observable<Establishment> {
    return this.api.put<Establishment>(`establishments/${id}`, data);
  }

  delete(id: number): Observable<any> {
    return this.api.delete(`establishments/${id}`);
  }
}

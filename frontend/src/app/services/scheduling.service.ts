import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Scheduling } from '../models/service.model';

@Injectable({
  providedIn: 'root',
})
export class SchedulingService {
  constructor(private api: ApiService) {}

  getAll(): Observable<any> {
    return this.api.get<any>('schedulings');
  }

  getById(id: number): Observable<Scheduling> {
    return this.api.get<Scheduling>(`schedulings/${id}`);
  }

  create(data: Partial<Scheduling>): Observable<Scheduling> {
    return this.api.post<Scheduling>('schedulings', data);
  }

  update(id: number, data: Partial<Scheduling>): Observable<Scheduling> {
    return this.api.put<Scheduling>(`schedulings/${id}`, data);
  }

  delete(id: number): Observable<any> {
    return this.api.delete(`schedulings/${id}`);
  }
}

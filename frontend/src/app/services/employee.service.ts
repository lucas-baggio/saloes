import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ApiService } from './api.service';

export interface Employee {
  id: number;
  name: string;
  email: string;
  role: string;
  created_at?: string;
  services_count: number;
  revenue: number;
  schedulings_count: number;
}

@Injectable({
  providedIn: 'root',
})
export class EmployeeService {
  constructor(private api: ApiService) {}

  getAll(): Observable<Employee[]> {
    return this.api
      .get<{ data: Employee[]; total: number } | Employee[]>('employees')
      .pipe(
        map((response) =>
          Array.isArray(response) ? response : response.data || []
        )
      );
  }

  getById(id: number): Observable<Employee> {
    return this.api.get<Employee>(`employees/${id}`);
  }

  create(data: {
    name: string;
    email: string;
    password: string;
    establishment_id: number;
  }): Observable<Employee> {
    return this.api.post<Employee>('employees', data);
  }

  update(id: number, data: Partial<Employee>): Observable<Employee> {
    return this.api.put<Employee>(`employees/${id}`, data);
  }

  delete(id: number): Observable<any> {
    return this.api.delete(`employees/${id}`);
  }
}

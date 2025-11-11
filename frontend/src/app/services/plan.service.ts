import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { Plan, UserPlan } from '../models/plan.model';

@Injectable({
  providedIn: 'root',
})
export class PlanService {
  constructor(private api: ApiService) {}

  getAll(): Observable<any> {
    return this.api.get<any>('plans');
  }

  getById(id: number): Observable<Plan> {
    return this.api.get<Plan>(`plans/${id}`);
  }

  getCurrentPlan(): Observable<UserPlan | null> {
    return this.api.get<UserPlan | null>('plans/current');
  }

  subscribe(planId: number): Observable<UserPlan> {
    return this.api.post<UserPlan>('plans/subscribe', { plan_id: planId });
  }

  cancel(): Observable<any> {
    return this.api.post<any>('plans/cancel', {});
  }
}

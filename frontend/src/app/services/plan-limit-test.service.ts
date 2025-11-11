import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface PlanLimits {
  has_plan: boolean;
  plan_name?: string;
  max_establishments: number | null;
  max_services: number | null;
  max_employees: number | null;
  current_establishments: number;
  current_services: number;
  current_employees: number;
  message?: string;
}

export interface LimitCheck {
  allowed: boolean;
  message?: string;
  current?: number;
  limit?: number;
  remaining?: number;
}

export interface TestUser {
  user: {
    id: number;
    name: string;
    email: string;
    password: string;
  };
  plan: {
    id: number;
    name: string;
    max_establishments: number | null;
    max_services: number | null;
    max_employees: number | null;
  };
  limits: PlanLimits;
  can_create_establishment: LimitCheck;
  can_create_service: LimitCheck;
  can_add_employee: LimitCheck;
}

@Injectable({
  providedIn: 'root',
})
export class PlanLimitTestService {
  constructor(private api: ApiService) {}

  /**
   * Obtém os limites de um usuário
   */
  getPlanLimits(userId: number): Observable<{
    user_id: number;
    user_name: string;
    user_email: string;
    limits: PlanLimits;
    can_create_establishment: LimitCheck;
    can_create_service: LimitCheck;
    can_add_employee: LimitCheck;
  }> {
    return this.api.get(`test/plan-limits/${userId}`);
  }

  /**
   * Cria um usuário de teste com plano
   */
  createTestUser(planId: number, name?: string, email?: string): Observable<TestUser> {
    return this.api.post('test/create-test-user', {
      plan_id: planId,
      name,
      email,
    });
  }
}


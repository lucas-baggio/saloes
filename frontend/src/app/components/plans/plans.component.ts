import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { PlanService } from '../../services/plan.service';
import { Plan, UserPlan } from '../../models/plan.model';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';

@Component({
  selector: 'app-plans',
  standalone: true,
  imports: [CommonModule, DatePipe, BreadcrumbsComponent],
  templateUrl: './plans.component.html',
  styleUrl: './plans.component.scss',
})
export class PlansComponent implements OnInit {
  plans: Plan[] = [];
  currentPlan: UserPlan | null = null;
  loading = false;
  subscribing = false;
  selectedInterval: 'monthly' | 'yearly' = 'monthly';
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Planos' },
  ];

  constructor(
    private planService: PlanService,
    private authService: AuthService,
    private alertService: AlertService,
    private router: Router
  ) {}

  ngOnInit() {
    this.loadPlans();
    this.loadCurrentPlan();
  }

  loadPlans() {
    this.loading = true;
    this.planService.getAll().subscribe({
      next: (response) => {
        this.plans = response.data || response || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar planos',
          'Não foi possível carregar os planos disponíveis.'
        );
      },
    });
  }

  loadCurrentPlan() {
    this.planService.getCurrentPlan().subscribe({
      next: (plan) => {
        this.currentPlan = plan;
      },
      error: () => {
        // Se não houver plano atual, não é um erro crítico
        this.currentPlan = null;
      },
    });
  }

  getFilteredPlans(): Plan[] {
    return this.plans.filter((plan) => plan.interval === this.selectedInterval);
  }

  subscribe(plan: Plan) {
    // Navegar para a tela de pagamento
    this.router.navigate(['/payment', plan.id]);
  }

  cancelPlan() {
    this.alertService
      .confirm(
        'Cancelar Plano',
        'Tem certeza que deseja cancelar seu plano atual?',
        'Cancelar Plano',
        'Manter Plano'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.subscribing = true;
          this.planService.cancel().subscribe({
            next: () => {
              this.alertService.success(
                'Plano cancelado',
                'Seu plano foi cancelado com sucesso.'
              );
              this.loadCurrentPlan();
              this.subscribing = false;
            },
            error: (err) => {
              this.subscribing = false;
              this.alertService.error(
                'Erro ao cancelar plano',
                err.error?.message || 'Não foi possível cancelar o plano.'
              );
            },
          });
        }
      });
  }

  isCurrentPlan(planId: number): boolean {
    return (
      this.currentPlan?.plan_id === planId &&
      this.currentPlan?.status === 'active'
    );
  }

  getMaxFeaturesLength(): number[] {
    const plans = this.getFilteredPlans();
    if (plans.length === 0) return [];
    const maxLength = Math.max(
      ...plans.map((plan) => plan.features?.length || 0),
      0
    );
    return Array.from({ length: Math.min(maxLength, 5) }, (_, i) => i);
  }

  getFeatureName(index: number): string {
    // Tenta pegar o nome da feature do primeiro plano que tiver essa feature
    for (const plan of this.getFilteredPlans()) {
      if (plan.features && plan.features[index]) {
        // Extrai o nome da feature (remove checkmarks, etc)
        return plan.features[index].replace(/^[✓✔✅]\s*/, '').trim();
      }
    }
    return `Recurso ${index + 1}`;
  }
}

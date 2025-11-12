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
        const allPlans: Plan[] = response.data || response || [];
        // Remover duplicatas baseado em id
        const uniquePlans = Array.from(
          new Map(allPlans.map((plan: Plan) => [plan.id, plan])).values()
        ) as Plan[];
        this.plans = uniquePlans;
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
        console.log('Plano atual carregado:', plan);
      },
      error: (err) => {
        // Se não houver plano atual, não é um erro crítico
        console.log('Nenhum plano atual encontrado:', err);
        this.currentPlan = null;
      },
    });
  }

  getFilteredPlans(): Plan[] {
    // Sempre incluir o plano gratuito
    const freePlan = this.plans.find(
      (plan) => plan.name === 'Gratuito' && plan.price === 0
    );

    // Filtrar planos por intervalo, excluindo o gratuito (já será adicionado)
    const filteredPlans = this.plans.filter(
      (plan) =>
        plan.interval === this.selectedInterval &&
        !(plan.name === 'Gratuito' && plan.price === 0)
    );

    // Remover duplicatas por nome e intervalo
    const uniquePlans = Array.from(
      new Map(
        filteredPlans.map((plan) => [`${plan.name}-${plan.interval}`, plan])
      ).values()
    );

    // Adicionar o plano gratuito no início se existir
    if (freePlan) {
      return [freePlan, ...uniquePlans];
    }

    return uniquePlans;
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
    if (!this.currentPlan || this.currentPlan.status !== 'active') {
      return false;
    }

    // Verificar se o plan_id corresponde
    if (this.currentPlan.plan_id === planId) {
      return true;
    }

    // Verificar se o plano atual tem um objeto plan e o id corresponde
    if (this.currentPlan.plan && this.currentPlan.plan.id === planId) {
      return true;
    }

    return false;
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

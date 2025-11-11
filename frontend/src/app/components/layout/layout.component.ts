import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  Router,
  RouterLink,
  RouterLinkActive,
  RouterOutlet,
  NavigationEnd,
} from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { User } from '../../models/user.model';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';
import { PlanService } from '../../services/plan.service';
import { UserPlan } from '../../models/plan.model';
import { environment } from '../../../environments/environment';
import { filter, Subscription } from 'rxjs';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss',
})
export class LayoutComponent implements OnInit, OnDestroy {
  user: User | null = null;
  currentPlan: UserPlan | null = null;
  isMenuOpen = false;
  isDevelopment = !environment.production;
  loadingPlan = false;
  private routerSubscription?: Subscription;

  constructor(
    private authService: AuthService,
    private router: Router,
    private apiService: ApiService,
    private alertService: AlertService,
    private planService: PlanService
  ) {}

  ngOnInit() {
    this.loadUser();
    this.loadCurrentPlan();

    // Recarrega o plano quando navega (útil após pagamento)
    this.routerSubscription = this.router.events
      .pipe(
        filter(
          (event): event is NavigationEnd => event instanceof NavigationEnd
        )
      )
      .subscribe((event) => {
        // Recarrega o plano quando navega para o dashboard (após pagamento)
        // Com delay para garantir que o backend processou o webhook
        if (event.urlAfterRedirects.includes('/dashboard')) {
          // Primeira tentativa após 3 segundos (tempo para webhook processar)
          // Deve fazer retry se não encontrar plano (webhook pode não ter processado ainda)
          setTimeout(() => {
            this.loadCurrentPlan(0, true);
          }, 3000);
        } else {
          // Para outras navegações, recarrega sem retry
          setTimeout(() => {
            this.loadCurrentPlan(0, false);
          }, 500);
        }
      });
  }

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  loadUser() {
    // Tentar carregar do localStorage primeiro
    this.user = this.authService.getCurrentUser();

    // Se estiver autenticado, buscar dados atualizados do servidor
    if (this.authService.isAuthenticated()) {
      this.authService.me().subscribe({
        next: (user) => {
          this.user = user;
          localStorage.setItem('user', JSON.stringify(user));
          // Carrega o plano após carregar o usuário
          this.loadCurrentPlan();
        },
        error: () => {
          // Se der erro, usar dados do localStorage
          this.user = this.authService.getCurrentUser();
          this.loadCurrentPlan();
        },
      });
    }
  }

  loadCurrentPlan(retryCount = 0, shouldRetryOnNull = false) {
    // Apenas owners e admins podem ter planos
    if (!this.authService.isAuthenticated()) {
      return;
    }

    const user = this.authService.getCurrentUser();
    if (!user || user.role === 'employee') {
      return;
    }

    // Evita múltiplas chamadas simultâneas (exceto em retry)
    if (this.loadingPlan && retryCount === 0) {
      return;
    }

    this.loadingPlan = true;
    this.planService.getCurrentPlan().subscribe({
      next: (userPlan) => {
        // Se retornar null, significa que não tem plano ativo
        this.currentPlan = userPlan || null;
        this.loadingPlan = false;

        // Log para debug
        console.log('Plano carregado:', {
          hasPlan: !!this.currentPlan,
          planName: this.currentPlan?.plan?.name || 'Gratuito',
          status: this.currentPlan?.status,
          retryCount,
        });

        // Se não encontrou plano e devemos fazer retry (após pagamento)
        // Pode ser que o webhook ainda não tenha processado
        if (!this.currentPlan && shouldRetryOnNull && retryCount < 3) {
          console.log(
            `Plano ainda não ativado, tentando novamente (${
              retryCount + 1
            }/3)...`
          );
          setTimeout(() => {
            this.loadCurrentPlan(retryCount + 1, shouldRetryOnNull);
          }, 3000 * (retryCount + 1)); // Backoff: 3s, 6s, 9s
        }
      },
      error: (err) => {
        // Erro de rede ou servidor
        this.currentPlan = null;
        this.loadingPlan = false;
        console.error('Erro ao carregar plano:', err);

        // Se for erro do servidor e devemos fazer retry
        if (shouldRetryOnNull && retryCount < 3 && err.status >= 500) {
          console.log(
            `Erro ao carregar plano, tentando novamente (${
              retryCount + 1
            }/3)...`
          );
          setTimeout(() => {
            this.loadCurrentPlan(retryCount + 1, shouldRetryOnNull);
          }, 3000 * (retryCount + 1));
        }
      },
    });
  }

  getPlanName(): string {
    if (!this.user || this.user.role === 'employee') {
      return '';
    }

    if (this.loadingPlan) {
      return 'Carregando...';
    }

    if (!this.currentPlan || this.currentPlan.status !== 'active') {
      return 'Gratuito';
    }

    return this.currentPlan.plan?.name || 'Gratuito';
  }

  getPlanBadgeClass(): string {
    if (!this.currentPlan || this.currentPlan.status !== 'active') {
      return 'bg-gray-100 text-gray-800';
    }

    const planName = this.currentPlan.plan?.name?.toLowerCase() || '';

    if (planName.includes('premium')) {
      return 'bg-purple-100 text-purple-800';
    } else if (
      planName.includes('profissional') ||
      planName.includes('professional')
    ) {
      return 'bg-blue-100 text-blue-800';
    } else if (planName.includes('básico') || planName.includes('basico')) {
      return 'bg-green-100 text-green-800';
    }

    return 'bg-primary-100 text-primary-800';
  }

  shouldShowPlanBadge(): boolean {
    // Não mostra para funcionários
    if (!this.user || this.user.role === 'employee') {
      return false;
    }
    return true;
  }

  logout() {
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/login']);
      },
    });
  }

  toggleMenu() {
    this.isMenuOpen = !this.isMenuOpen;
  }

  resendVerification() {
    if (!this.user?.email) {
      return;
    }

    this.apiService
      .post('auth/resend-verification', { email: this.user.email })
      .subscribe({
        next: () => {
          this.alertService.success(
            'Email reenviado!',
            'Verifique sua caixa de entrada para o novo link de verificação.'
          );
        },
        error: (err) => {
          this.alertService.error(
            'Erro',
            'Não foi possível reenviar o email. Tente novamente mais tarde.'
          );
        },
      });
  }
}

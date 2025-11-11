import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';
import { AuthService } from '../../services/auth.service';
import { Subscription } from 'rxjs';
import { take } from 'rxjs/operators';

@Component({
  selector: 'app-verify-email',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './verify-email.component.html',
  styleUrl: './verify-email.component.scss',
})
export class VerifyEmailComponent implements OnInit, OnDestroy {
  loading = false;
  success = false;
  error = '';
  token = '';
  email = '';
  private hasVerified = false; // Flag para evitar verificação duplicada
  private queryParamsSubscription?: Subscription;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    public router: Router,
    private route: ActivatedRoute,
    private authService: AuthService
  ) {}

  ngOnInit() {
    console.log('VerifyEmailComponent initialized');
    // Usar take(1) para garantir que só execute uma vez
    this.queryParamsSubscription = this.route.queryParams
      .pipe(take(1))
      .subscribe((params) => {
        console.log('Query params:', params);
        this.token = params['token'] || '';
        this.email = params['email'] || '';

        console.log('Token:', this.token);
        console.log('Email:', this.email);

        // Só verifica se ainda não foi verificado e tem token/email
        if (this.token && this.email && !this.hasVerified && !this.success) {
          console.log('Calling verifyEmail()...');
          this.verifyEmail();
        } else if (!this.token || !this.email) {
          console.error('Missing token or email');
          this.error = 'Link de verificação inválido ou incompleto.';
        }
      });
  }

  ngOnDestroy() {
    // Limpar subscription ao destruir o componente
    if (this.queryParamsSubscription) {
      this.queryParamsSubscription.unsubscribe();
    }
  }

  verifyEmail() {
    // Proteção contra verificação duplicada
    if (this.hasVerified || this.loading) {
      console.log('Verification already in progress or completed');
      return;
    }

    if (!this.token || !this.email) {
      console.error('verifyEmail called without token or email');
      this.error = 'Link de verificação inválido.';
      return;
    }

    console.log('Sending verification request:', {
      token: this.token.substring(0, 10) + '...',
      email: this.email,
    });

    this.hasVerified = true; // Marca como verificado antes de fazer a requisição
    this.loading = true;
    this.apiService
      .post('auth/verify-email', {
        token: this.token,
        email: this.email,
      })
      .subscribe({
        next: (response: any) => {
          // Se já foi marcado como sucesso, não fazer nada
          if (this.success) {
            return;
          }

          this.loading = false;
          this.success = true;

          // Atualizar usuário no localStorage
          const currentUser = this.authService.getCurrentUser();
          if (currentUser) {
            currentUser.email_verified_at = new Date().toISOString();
            localStorage.setItem('user', JSON.stringify(currentUser));
          }

          // Se estiver logado, recarregar dados do usuário primeiro
          if (this.authService.isAuthenticated()) {
            this.authService.me().subscribe({
              next: (user) => {
                localStorage.setItem('user', JSON.stringify(user));

                // Mostrar alerta de sucesso e redirecionar ao clicar em OK
                this.alertService
                  .success(
                    'Email verificado!',
                    'Sua conta foi ativada com sucesso. Você já pode usar todos os recursos da plataforma.'
                  )
                  .then(() => {
                    // Redirecionar para dashboard após clicar em OK
                    this.router.navigate(['/dashboard']);
                  });
              },
              error: () => {
                // Se der erro ao buscar dados, ainda assim mostra sucesso e redireciona
                this.alertService
                  .success(
                    'Email verificado!',
                    'Sua conta foi ativada com sucesso. Você já pode usar todos os recursos da plataforma.'
                  )
                  .then(() => {
                    this.router.navigate(['/dashboard']);
                  });
              },
            });
          } else {
            this.alertService
              .success(
                'Email verificado!',
                'Sua conta foi ativada com sucesso. Você já pode fazer login.'
              )
              .then(() => {
                this.router.navigate(['/login']);
              });
          }
        },
        error: (err) => {
          // Se já foi marcado como sucesso, ignorar qualquer erro
          if (this.success) {
            console.log('Error after success, ignoring');
            return;
          }

          this.loading = false;

          // Se o erro for "já verificado", tratar como sucesso
          if (
            err.error?.message?.includes('já foi verificado') ||
            err.error?.message?.includes('já foi verificado anteriormente')
          ) {
            this.success = true;
            this.hasVerified = true;

            // Atualizar usuário no localStorage
            const currentUser = this.authService.getCurrentUser();
            if (currentUser) {
              currentUser.email_verified_at = new Date().toISOString();
              localStorage.setItem('user', JSON.stringify(currentUser));
            }

            // Se estiver logado, recarregar dados
            if (this.authService.isAuthenticated()) {
              this.authService.me().subscribe({
                next: (user) => {
                  localStorage.setItem('user', JSON.stringify(user));
                  this.alertService
                    .success(
                      'Email verificado!',
                      'Sua conta já estava verificada. Redirecionando...'
                    )
                    .then(() => {
                      this.router.navigate(['/dashboard']);
                    });
                },
                error: () => {
                  this.alertService
                    .success(
                      'Email verificado!',
                      'Sua conta já estava verificada. Redirecionando...'
                    )
                    .then(() => {
                      this.router.navigate(['/dashboard']);
                    });
                },
              });
            }
            return;
          }

          // Só mostrar erro se realmente não foi verificado
          this.hasVerified = false; // Permite tentar novamente em caso de erro real
          this.error =
            err.error?.message ||
            'Erro ao verificar email. O link pode ter expirado.';
          this.alertService.error('Erro na verificação', this.error);
        },
      });
  }

  resendVerification() {
    if (!this.email) {
      this.alertService.warning(
        'Email necessário',
        'Por favor, informe seu email para reenviar a verificação.'
      );
      return;
    }

    this.loading = true;
    this.apiService
      .post('auth/resend-verification', { email: this.email })
      .subscribe({
        next: (response: any) => {
          this.loading = false;
          this.alertService.success(
            'Email reenviado!',
            'Verifique sua caixa de entrada para o novo link de verificação.'
          );
        },
        error: (err) => {
          this.loading = false;
          this.alertService.error(
            'Erro',
            'Não foi possível reenviar o email. Tente novamente mais tarde.'
          );
        },
      });
  }
}

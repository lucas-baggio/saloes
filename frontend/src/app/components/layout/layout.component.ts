import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  Router,
  RouterLink,
  RouterLinkActive,
  RouterOutlet,
} from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { User } from '../../models/user.model';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss',
})
export class LayoutComponent implements OnInit {
  user: User | null = null;
  isMenuOpen = false;

  constructor(
    private authService: AuthService,
    private router: Router,
    private apiService: ApiService,
    private alertService: AlertService
  ) {}

  ngOnInit() {
    this.loadUser();
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
          // Debug: verificar se email_verified_at está presente
          console.log('User loaded:', {
            email: user.email,
            email_verified_at: user.email_verified_at,
            isVerified: !!user.email_verified_at
          });
        },
        error: () => {
          // Se der erro, usar dados do localStorage
          this.user = this.authService.getCurrentUser();
        },
      });
    }
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

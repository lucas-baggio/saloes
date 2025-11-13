import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { AlertService } from '../services/alert.service';

export const emailVerifiedGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  const alertService = inject(AlertService);

  const user = authService.getCurrentUser();

  // Se não estiver autenticado, redireciona para login
  if (!authService.isAuthenticated() || !user) {
    router.navigate(['/login']);
    return false;
  }

  // Se o email não estiver verificado, bloqueia acesso
  if (!user.email_verified_at) {
    alertService.warning(
      'Email não verificado',
      'Você precisa verificar seu email para acessar esta funcionalidade. Verifique sua caixa de entrada.'
    );
    router.navigate(['/calendar']);
    return false;
  }

  return true;
};

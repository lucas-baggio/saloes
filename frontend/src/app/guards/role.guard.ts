import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const ownerGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const user = authService.getCurrentUser();
  
  if (user && (user.role === 'owner' || user.role === 'admin')) {
    return true;
  }

  router.navigate(['/dashboard']);
  return false;
};


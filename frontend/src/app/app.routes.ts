import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { guestGuard } from './guards/guest.guard';
import { ownerGuard } from './guards/role.guard';
import { emailVerifiedGuard } from './guards/email-verified.guard';
import { environment } from '../environments/environment';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () =>
      import('./components/login/login.component').then(
        (m) => m.LoginComponent
      ),
    canActivate: [guestGuard],
  },
  {
    path: 'register',
    loadComponent: () =>
      import('./components/register/register.component').then(
        (m) => m.RegisterComponent
      ),
    canActivate: [guestGuard],
  },
  {
    path: 'forgot-password',
    loadComponent: () =>
      import('./components/forgot-password/forgot-password.component').then(
        (m) => m.ForgotPasswordComponent
      ),
    canActivate: [guestGuard],
  },
  {
    path: 'reset-password',
    loadComponent: () =>
      import('./components/reset-password/reset-password.component').then(
        (m) => m.ResetPasswordComponent
      ),
    canActivate: [guestGuard],
  },
  {
    path: 'verify-email',
    loadComponent: () =>
      import('./components/verify-email/verify-email.component').then(
        (m) => m.VerifyEmailComponent
      ),
    // Sem guard - permite acesso mesmo autenticado
  },
  {
    path: '',
    loadComponent: () =>
      import('./components/layout/layout.component').then(
        (m) => m.LayoutComponent
      ),
    canActivate: [authGuard],
    children: [
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full',
      },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./components/dashboard/dashboard.component').then(
            (m) => m.DashboardComponent
          ),
      },
      {
        path: 'establishments',
        loadComponent: () =>
          import('./components/establishments/establishments.component').then(
            (m) => m.EstablishmentsComponent
          ),
        canActivate: [ownerGuard, emailVerifiedGuard],
      },
      {
        path: 'services',
        loadComponent: () =>
          import('./components/services/services.component').then(
            (m) => m.ServicesComponent
          ),
        canActivate: [ownerGuard, emailVerifiedGuard],
      },
      {
        path: 'schedulings',
        loadComponent: () =>
          import('./components/schedulings/schedulings.component').then(
            (m) => m.SchedulingsComponent
          ),
        canActivate: [emailVerifiedGuard],
      },
      {
        path: 'calendar',
        loadComponent: () =>
          import('./components/calendar/calendar.component').then(
            (m) => m.CalendarComponent
          ),
        canActivate: [emailVerifiedGuard],
      },
      {
        path: 'employees',
        loadComponent: () =>
          import('./components/employees/employees.component').then(
            (m) => m.EmployeesComponent
          ),
        canActivate: [ownerGuard, emailVerifiedGuard],
      },
      {
        path: 'clients',
        loadComponent: () =>
          import('./components/clients/clients.component').then(
            (m) => m.ClientsComponent
          ),
        canActivate: [ownerGuard, emailVerifiedGuard],
      },
      {
        path: 'sales',
        loadComponent: () =>
          import('./components/sales/sales.component').then(
            (m) => m.SalesComponent
          ),
        canActivate: [emailVerifiedGuard],
      },
      {
        path: 'commissions',
        loadComponent: () =>
          import('./components/commissions/commissions.component').then(
            (m) => m.CommissionsComponent
          ),
        canActivate: [emailVerifiedGuard],
      },
      {
        path: 'expenses',
        loadComponent: () =>
          import('./components/expenses/expenses.component').then(
            (m) => m.ExpensesComponent
          ),
        canActivate: [emailVerifiedGuard],
      },
      {
        path: 'plans',
        loadComponent: () =>
          import('./components/plans/plans.component').then(
            (m) => m.PlansComponent
          ),
        canActivate: [ownerGuard, emailVerifiedGuard],
      },
      {
        path: 'payment/:planId',
        loadComponent: () =>
          import('./components/payment/payment.component').then(
            (m) => m.PaymentComponent
          ),
        canActivate: [ownerGuard, emailVerifiedGuard],
      },
      // Rota de teste de limites (apenas em desenvolvimento
    ],
  },
  {
    path: '**',
    redirectTo: 'dashboard',
  },
];

import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { guestGuard } from './guards/guest.guard';
import { ownerGuard } from './guards/role.guard';

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
      },
      {
        path: 'services',
        loadComponent: () =>
          import('./components/services/services.component').then(
            (m) => m.ServicesComponent
          ),
      },
      {
        path: 'schedulings',
        loadComponent: () =>
          import('./components/schedulings/schedulings.component').then(
            (m) => m.SchedulingsComponent
          ),
      },
      {
        path: 'employees',
        loadComponent: () =>
          import('./components/employees/employees.component').then(
            (m) => m.EmployeesComponent
          ),
        canActivate: [ownerGuard],
      },
    ],
  },
  {
    path: '**',
    redirectTo: 'dashboard',
  },
];

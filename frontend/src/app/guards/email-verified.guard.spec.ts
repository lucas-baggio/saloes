import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { RouterTestingModule } from '@angular/router/testing';
import { emailVerifiedGuard } from './email-verified.guard';
import { AuthService } from '../services/auth.service';
import { AlertService } from '../services/alert.service';
import { User } from '../models/user.model';

describe('emailVerifiedGuard', () => {
  let guard: typeof emailVerifiedGuard;
  let authService: jasmine.SpyObj<AuthService>;
  let alertService: jasmine.SpyObj<AlertService>;
  let router: Router;

  const mockVerifiedUser: User = {
    id: 1,
    name: 'Verified User',
    email: 'verified@example.com',
    role: 'owner',
    email_verified_at: '2024-01-01T00:00:00Z',
  };

  const mockUnverifiedUser: User = {
    id: 2,
    name: 'Unverified User',
    email: 'unverified@example.com',
    role: 'owner',
    email_verified_at: null,
  };

  beforeEach(() => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', [
      'isAuthenticated',
      'getCurrentUser',
    ]);
    const alertServiceSpy = jasmine.createSpyObj('AlertService', ['warning']);

    TestBed.configureTestingModule({
      imports: [RouterTestingModule],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: AlertService, useValue: alertServiceSpy },
      ],
    });

    guard = emailVerifiedGuard;
    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    alertService = TestBed.inject(AlertService) as jasmine.SpyObj<AlertService>;
    router = TestBed.inject(Router);
    spyOn(router, 'navigate');
  });

  it('should be created', () => {
    expect(guard).toBeTruthy();
  });

  it('should allow access when email is verified', () => {
    authService.isAuthenticated.and.returnValue(true);
    authService.getCurrentUser.and.returnValue(mockVerifiedUser);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(true);
    expect(router.navigate).not.toHaveBeenCalled();
    expect(alertService.warning).not.toHaveBeenCalled();
  });

  it('should redirect to login when user is not authenticated', () => {
    authService.isAuthenticated.and.returnValue(false);
    authService.getCurrentUser.and.returnValue(null);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
    expect(alertService.warning).not.toHaveBeenCalled();
  });

  it('should redirect to dashboard and show warning when email is not verified', () => {
    authService.isAuthenticated.and.returnValue(true);
    authService.getCurrentUser.and.returnValue(mockUnverifiedUser);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
    expect(alertService.warning).toHaveBeenCalledWith(
      'Email não verificado',
      'Você precisa verificar seu email para acessar esta funcionalidade. Verifique sua caixa de entrada.'
    );
  });

  it('should redirect to login when user is null but authenticated returns true', () => {
    authService.isAuthenticated.and.returnValue(true);
    authService.getCurrentUser.and.returnValue(null);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });
});

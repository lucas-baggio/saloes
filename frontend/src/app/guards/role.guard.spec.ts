import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { RouterTestingModule } from '@angular/router/testing';
import { ownerGuard } from './role.guard';
import { AuthService } from '../services/auth.service';
import { User } from '../models/user.model';

describe('ownerGuard', () => {
  let guard: typeof ownerGuard;
  let authService: jasmine.SpyObj<AuthService>;
  let router: Router;

  const mockOwnerUser: User = {
    id: 1,
    name: 'Owner',
    email: 'owner@example.com',
    role: 'owner',
  };

  const mockAdminUser: User = {
    id: 2,
    name: 'Admin',
    email: 'admin@example.com',
    role: 'admin',
  };

  const mockEmployeeUser: User = {
    id: 3,
    name: 'Employee',
    email: 'employee@example.com',
    role: 'employee',
  };

  beforeEach(() => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', [
      'getCurrentUser',
    ]);

    TestBed.configureTestingModule({
      imports: [RouterTestingModule],
      providers: [{ provide: AuthService, useValue: authServiceSpy }],
    });

    guard = ownerGuard;
    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router);
    spyOn(router, 'navigate');
  });

  it('should be created', () => {
    expect(guard).toBeTruthy();
  });

  it('should allow access for owner role', () => {
    authService.getCurrentUser.and.returnValue(mockOwnerUser);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(true);
    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('should allow access for admin role', () => {
    authService.getCurrentUser.and.returnValue(mockAdminUser);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(true);
    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('should redirect to dashboard for employee role', () => {
    authService.getCurrentUser.and.returnValue(mockEmployeeUser);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
  });

  it('should redirect to dashboard when user is null', () => {
    authService.getCurrentUser.and.returnValue(null);

    const result = TestBed.runInInjectionContext(() =>
      guard({} as any, {} as any)
    );

    expect(result).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
  });
});

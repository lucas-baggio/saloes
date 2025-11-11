import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { RouterTestingModule } from '@angular/router/testing';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { of, throwError } from 'rxjs';
import { LoginComponent } from './login.component';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import { AuthResponse } from '../../models/user.model';

describe('LoginComponent', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let router: Router;

  const mockAuthResponse: AuthResponse = {
    token: 'test-token',
    token_type: 'Bearer',
    user: {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'owner' as const,
    },
  };

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['login']);

    await TestBed.configureTestingModule({
      imports: [
        LoginComponent,
        ReactiveFormsModule,
        RouterTestingModule,
        HttpClientTestingModule,
      ],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        AlertService,
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router);
    spyOn(router, 'navigate');
    localStorage.clear();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize form with empty values', () => {
    expect(component.loginForm.get('email')?.value).toBe('');
    expect(component.loginForm.get('password')?.value).toBe('');
  });

  it('should have required validators on email and password', () => {
    expect(component.loginForm.get('email')?.hasError('required')).toBe(true);
    expect(component.loginForm.get('password')?.hasError('required')).toBe(
      true
    );
  });

  it('should validate email format', () => {
    component.loginForm.patchValue({ email: 'invalid-email' });
    expect(component.loginForm.get('email')?.hasError('email')).toBe(true);

    component.loginForm.patchValue({ email: 'valid@email.com' });
    expect(component.loginForm.get('email')?.hasError('email')).toBe(false);
  });

  it('should validate password minimum length', () => {
    component.loginForm.patchValue({ password: 'short' });
    expect(component.loginForm.get('password')?.hasError('minlength')).toBe(
      true
    );

    component.loginForm.patchValue({ password: 'longpassword' });
    expect(component.loginForm.get('password')?.hasError('minlength')).toBe(
      false
    );
  });

  it('should not submit when form is invalid', () => {
    component.onSubmit();
    expect(authService.login).not.toHaveBeenCalled();
  });

  it('should call authService.login when form is valid', () => {
    authService.login.and.returnValue(of(mockAuthResponse));
    component.loginForm.patchValue({
      email: 'test@example.com',
      password: 'password123',
    });

    component.onSubmit();

    expect(authService.login).toHaveBeenCalledWith({
      email: 'test@example.com',
      password: 'password123',
    });
  });

  it('should navigate to dashboard on successful login', () => {
    authService.login.and.returnValue(of(mockAuthResponse));
    component.loginForm.patchValue({
      email: 'test@example.com',
      password: 'password123',
    });

    component.onSubmit();

    expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
  });

  it('should set loading to true during login', () => {
    authService.login.and.returnValue(of(mockAuthResponse));
    component.loginForm.patchValue({
      email: 'test@example.com',
      password: 'password123',
    });

    component.onSubmit();
    expect(component.loading).toBe(true);
  });

  it('should handle login error', () => {
    const error = { error: { message: 'Invalid credentials' } };
    authService.login.and.returnValue(throwError(() => error));
    component.loginForm.patchValue({
      email: 'test@example.com',
      password: 'wrongpassword',
    });

    component.onSubmit();

    expect(component.error).toBe('Invalid credentials');
    expect(component.loading).toBe(false);
    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('should set default error message when error has no message', () => {
    const error = { error: {} };
    authService.login.and.returnValue(throwError(() => error));
    component.loginForm.patchValue({
      email: 'test@example.com',
      password: 'wrongpassword',
    });

    component.onSubmit();

    expect(component.error).toBe(
      'Erro ao fazer login. Verifique suas credenciais.'
    );
  });

  it('should clear error on new submit', () => {
    component.error = 'Previous error';
    authService.login.and.returnValue(of(mockAuthResponse));
    component.loginForm.patchValue({
      email: 'test@example.com',
      password: 'password123',
    });

    component.onSubmit();

    expect(component.error).toBe('');
  });
});

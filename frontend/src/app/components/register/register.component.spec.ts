import { ComponentFixture, TestBed, fakeAsync, tick } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { RouterTestingModule } from '@angular/router/testing';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { of, throwError } from 'rxjs';
import { RegisterComponent } from './register.component';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import { AuthResponse } from '../../models/user.model';

describe('RegisterComponent', () => {
  let component: RegisterComponent;
  let fixture: ComponentFixture<RegisterComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let alertService: jasmine.SpyObj<AlertService>;
  let router: Router;

  const mockAuthResponse: AuthResponse & { message?: string } = {
    token: 'test-token',
    token_type: 'Bearer',
    user: {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'owner' as const,
    },
    message: 'Conta criada com sucesso!',
  };

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['register']);
    const alertServiceSpy = jasmine.createSpyObj('AlertService', [
      'success',
      'validationError',
    ]);

    await TestBed.configureTestingModule({
      imports: [
        RegisterComponent,
        ReactiveFormsModule,
        RouterTestingModule,
        HttpClientTestingModule,
      ],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: AlertService, useValue: alertServiceSpy },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(RegisterComponent);
    component = fixture.componentInstance;
    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    alertService = TestBed.inject(AlertService) as jasmine.SpyObj<AlertService>;
    router = TestBed.inject(Router);
    spyOn(router, 'navigate');
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize form with default values', () => {
    expect(component.registerForm.get('name')?.value).toBe('');
    expect(component.registerForm.get('email')?.value).toBe('');
    expect(component.registerForm.get('password')?.value).toBe('');
    expect(component.registerForm.get('role')?.value).toBe('owner');
  });

  it('should have required validators on name, email and password', () => {
    expect(component.registerForm.get('name')?.hasError('required')).toBe(true);
    expect(component.registerForm.get('email')?.hasError('required')).toBe(
      true
    );
    expect(component.registerForm.get('password')?.hasError('required')).toBe(
      true
    );
  });

  it('should validate name minimum length', () => {
    component.registerForm.patchValue({ name: 'ab' });
    expect(component.registerForm.get('name')?.hasError('minlength')).toBe(
      true
    );

    component.registerForm.patchValue({ name: 'John Doe' });
    expect(component.registerForm.get('name')?.hasError('minlength')).toBe(
      false
    );
  });

  it('should validate email format', () => {
    component.registerForm.patchValue({ email: 'invalid-email' });
    expect(component.registerForm.get('email')?.hasError('email')).toBe(true);

    component.registerForm.patchValue({ email: 'valid@email.com' });
    expect(component.registerForm.get('email')?.hasError('email')).toBe(false);
  });

  it('should validate password minimum length', () => {
    component.registerForm.patchValue({ password: 'short' });
    expect(component.registerForm.get('password')?.hasError('minlength')).toBe(
      true
    );

    component.registerForm.patchValue({ password: 'longpassword' });
    expect(component.registerForm.get('password')?.hasError('minlength')).toBe(
      false
    );
  });

  it('should not submit when form is invalid', () => {
    component.onSubmit();
    expect(authService.register).not.toHaveBeenCalled();
  });

  it('should call authService.register when form is valid', () => {
    alertService.success.and.returnValue(Promise.resolve({} as any));
    authService.register.and.returnValue(of(mockAuthResponse));
    component.registerForm.patchValue({
      name: 'Test User',
      email: 'test@example.com',
      password: 'password123',
      role: 'owner',
    });

    component.onSubmit();

    expect(authService.register).toHaveBeenCalledWith({
      name: 'Test User',
      email: 'test@example.com',
      password: 'password123',
      role: 'owner',
    });
  });

  it('should show success message and navigate to login on successful registration', fakeAsync(() => {
    alertService.success.and.returnValue(Promise.resolve({} as any));
    authService.register.and.returnValue(of(mockAuthResponse));
    component.registerForm.patchValue({
      name: 'Test User',
      email: 'test@example.com',
      password: 'password123',
      role: 'owner',
    });

    component.onSubmit();
    tick(1); // Process the Observable
    expect(alertService.success).toHaveBeenCalledWith(
      'Conta criada!',
      'Conta criada com sucesso!'
    );
    tick(2000); // Advance setTimeout
    
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  }));

  it('should handle registration error', () => {
    const error = { error: { message: 'Email already taken' } };
    authService.register.and.returnValue(throwError(() => error));
    component.registerForm.patchValue({
      name: 'Test User',
      email: 'existing@example.com',
      password: 'password123',
      role: 'owner',
    });

    component.onSubmit();

    expect(component.error).toBe('Email already taken');
    expect(component.loading).toBe(false);
    expect(alertService.validationError).toHaveBeenCalledWith(error);
  });

  it('should set loading to true during registration', () => {
    authService.register.and.returnValue(of(mockAuthResponse));
    alertService.success.and.returnValue(Promise.resolve({} as any));
    component.registerForm.patchValue({
      name: 'Test User',
      email: 'test@example.com',
      password: 'password123',
      role: 'owner',
    });

    component.onSubmit();
    expect(component.loading).toBe(true);
  });

  it('should clear error on new submit', () => {
    component.error = 'Previous error';
    authService.register.and.returnValue(of(mockAuthResponse));
    alertService.success.and.returnValue(Promise.resolve({} as any));
    component.registerForm.patchValue({
      name: 'Test User',
      email: 'test@example.com',
      password: 'password123',
      role: 'owner',
    });

    component.onSubmit();

    expect(component.error).toBe('');
  });
});

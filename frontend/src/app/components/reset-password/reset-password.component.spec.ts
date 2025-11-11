import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { RouterTestingModule } from '@angular/router/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { of } from 'rxjs';
import { ResetPasswordComponent } from './reset-password.component';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';
import { environment } from '../../../environments/environment';

describe('ResetPasswordComponent', () => {
  let component: ResetPasswordComponent;
  let fixture: ComponentFixture<ResetPasswordComponent>;
  let httpMock: HttpTestingController;
  let alertService: jasmine.SpyObj<AlertService>;
  let router: Router;

  beforeEach(async () => {
    const alertServiceSpy = jasmine.createSpyObj('AlertService', [
      'success',
      'error',
      'validationError',
    ]);

    await TestBed.configureTestingModule({
      imports: [
        ResetPasswordComponent,
        ReactiveFormsModule,
        RouterTestingModule,
        HttpClientTestingModule,
      ],
      providers: [
        ApiService,
        { provide: AlertService, useValue: alertServiceSpy },
        {
          provide: ActivatedRoute,
          useValue: {
            queryParams: of({ token: 'test-token', email: 'test@example.com' }),
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ResetPasswordComponent);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController);
    alertService = TestBed.inject(AlertService) as jasmine.SpyObj<AlertService>;
    router = TestBed.inject(Router);
    spyOn(router, 'navigate');
    jasmine.clock().install();
  });

  afterEach(() => {
    httpMock.verify();
    jasmine.clock().uninstall();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize form with empty password fields', () => {
    expect(component.resetPasswordForm.get('password')?.value).toBe('');
    expect(
      component.resetPasswordForm.get('password_confirmation')?.value
    ).toBe('');
  });

  it('should validate password match', () => {
    component.resetPasswordForm.patchValue({
      password: 'password123',
      password_confirmation: 'different',
    });

    expect(component.resetPasswordForm.hasError('passwordMismatch')).toBe(true);
  });

  it('should allow matching passwords', () => {
    component.resetPasswordForm.patchValue({
      password: 'password123',
      password_confirmation: 'password123',
    });

    expect(
      component.resetPasswordForm.hasError('passwordMismatch')
    ).toBeFalsy();
  });

  it('should load token and email from query params', () => {
    fixture.detectChanges();
    expect(component.token).toBe('test-token');
    expect(component.email).toBe('test@example.com');
  });

  it('should redirect if token or email is missing', () => {
    const route = TestBed.inject(ActivatedRoute);
    (route as any).queryParams = of({});

    fixture = TestBed.createComponent(ResetPasswordComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(alertService.error).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/forgot-password']);
  });

  it('should submit form and reset password', () => {
    alertService.success.and.returnValue(Promise.resolve({} as any));
    fixture.detectChanges();
    component.resetPasswordForm.patchValue({
      password: 'newpassword123',
      password_confirmation: 'newpassword123',
    });

    component.onSubmit();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/reset-password`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({
      token: 'test-token',
      email: 'test@example.com',
      password: 'newpassword123',
      password_confirmation: 'newpassword123',
    });
    req.flush({ message: 'Password reset successfully' });

    expect(component.success).toBe(true);
    expect(alertService.success).toHaveBeenCalled();
  });

  it('should handle reset password error', () => {
    const error = { error: { message: 'Invalid token' } };
    fixture.detectChanges();
    component.resetPasswordForm.patchValue({
      password: 'newpassword123',
      password_confirmation: 'newpassword123',
    });

    component.onSubmit();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/reset-password`);
    req.flush(
      { error: 'Invalid token' },
      { status: 400, statusText: 'Bad Request' }
    );

    expect(alertService.validationError).toHaveBeenCalled();
  });
});

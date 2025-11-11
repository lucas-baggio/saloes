import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { RouterTestingModule } from '@angular/router/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { of, throwError } from 'rxjs';
import { ForgotPasswordComponent } from './forgot-password.component';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';
import { environment } from '../../../environments/environment';

describe('ForgotPasswordComponent', () => {
  let component: ForgotPasswordComponent;
  let fixture: ComponentFixture<ForgotPasswordComponent>;
  let httpMock: HttpTestingController;
  let alertService: jasmine.SpyObj<AlertService>;

  beforeEach(async () => {
    const alertServiceSpy = jasmine.createSpyObj('AlertService', [
      'success',
      'info',
    ]);

    await TestBed.configureTestingModule({
      imports: [
        ForgotPasswordComponent,
        ReactiveFormsModule,
        RouterTestingModule,
        HttpClientTestingModule,
      ],
      providers: [
        ApiService,
        { provide: AlertService, useValue: alertServiceSpy },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ForgotPasswordComponent);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController);
    alertService = TestBed.inject(AlertService) as jasmine.SpyObj<AlertService>;
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize form with empty email', () => {
    expect(component.forgotPasswordForm.get('email')?.value).toBe('');
  });

  it('should validate email is required', () => {
    expect(
      component.forgotPasswordForm.get('email')?.hasError('required')
    ).toBe(true);
  });

  it('should validate email format', () => {
    component.forgotPasswordForm.patchValue({ email: 'invalid' });
    expect(component.forgotPasswordForm.get('email')?.hasError('email')).toBe(
      true
    );
  });

  it('should submit form and show success message', () => {
    alertService.success.and.returnValue(Promise.resolve({} as any));
    component.forgotPasswordForm.patchValue({ email: 'test@example.com' });

    component.onSubmit();

    const req = httpMock.expectOne(
      `${environment.apiUrl}/auth/forgot-password`
    );
    expect(req.request.method).toBe('POST');
    req.flush({ message: 'Email sent' });

    expect(component.success).toBe(true);
    expect(component.loading).toBe(false);
    expect(alertService.success).toHaveBeenCalled();
  });

  it('should show info message even on error for security', () => {
    alertService.info.and.returnValue(Promise.resolve({} as any));
    component.forgotPasswordForm.patchValue({ email: 'test@example.com' });

    component.onSubmit();

    const req = httpMock.expectOne(
      `${environment.apiUrl}/auth/forgot-password`
    );
    req.flush({ error: 'Error' }, { status: 500, statusText: 'Server Error' });

    expect(component.success).toBe(true);
    expect(alertService.info).toHaveBeenCalled();
  });
});

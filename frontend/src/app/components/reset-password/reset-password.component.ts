import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './reset-password.component.html',
  styleUrl: './reset-password.component.scss',
})
export class ResetPasswordComponent implements OnInit {
  resetPasswordForm: FormGroup;
  loading = false;
  token = '';
  email = '';
  success = false;

  constructor(
    private fb: FormBuilder,
    private apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute
  ) {
    this.resetPasswordForm = this.fb.group(
      {
        password: ['', [Validators.required, Validators.minLength(8)]],
        password_confirmation: ['', [Validators.required]],
      },
      {
        validators: this.passwordMatchValidator.bind(this),
      }
    );
  }

  ngOnInit() {
    this.route.queryParams.subscribe((params) => {
      this.token = params['token'] || '';
      this.email = params['email'] || '';

      if (!this.token || !this.email) {
        this.alertService.error(
          'Link inválido',
          'O link de recuperação está incompleto ou inválido.'
        );
        this.router.navigate(['/forgot-password']);
      }
    });
  }

  private passwordMatchValidator(form: FormGroup) {
    const password = form.get('password');
    const passwordConfirmation = form.get('password_confirmation');

    if (!password || !passwordConfirmation) {
      return null;
    }

    if (password.value !== passwordConfirmation.value) {
      passwordConfirmation.setErrors({ passwordMismatch: true });
      return { passwordMismatch: true };
    } else {
      if (passwordConfirmation.hasError('passwordMismatch')) {
        passwordConfirmation.setErrors(null);
      }
      return null;
    }
  }

  onSubmit() {
    if (this.resetPasswordForm.valid && this.token && this.email) {
      this.loading = true;
      const data = {
        token: this.token,
        email: this.email,
        password: this.resetPasswordForm.value.password,
        password_confirmation:
          this.resetPasswordForm.value.password_confirmation,
      };

      this.apiService.post('auth/reset-password', data).subscribe({
        next: (response: any) => {
          this.loading = false;
          this.success = true;
          this.alertService.success(
            'Senha redefinida!',
            'Sua senha foi redefinida com sucesso. Você já pode fazer login.'
          );
          setTimeout(() => {
            this.router.navigate(['/login']);
          }, 2000);
        },
        error: (err) => {
          this.loading = false;
          this.alertService.validationError(err);
        },
      });
    }
  }
}

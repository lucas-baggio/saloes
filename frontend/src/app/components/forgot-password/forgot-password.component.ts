import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './forgot-password.component.html',
  styleUrl: './forgot-password.component.scss',
})
export class ForgotPasswordComponent {
  forgotPasswordForm: FormGroup;
  loading = false;
  success = false;

  constructor(
    private fb: FormBuilder,
    private apiService: ApiService,
    private alertService: AlertService,
    public router: Router
  ) {
    this.forgotPasswordForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
    });
  }

  onSubmit() {
    if (this.forgotPasswordForm.valid) {
      this.loading = true;
      this.apiService
        .post('auth/forgot-password', this.forgotPasswordForm.value)
        .subscribe({
          next: (response: any) => {
            this.loading = false;
            this.success = true;
            this.alertService.success(
              'Email enviado!',
              'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.'
            );
          },
          error: (err) => {
            this.loading = false;
            // Mesmo em caso de erro, mostramos mensagem de sucesso por segurança
            this.success = true;
            this.alertService.info(
              'Email enviado!',
              'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.'
            );
          },
        });
    }
  }
}

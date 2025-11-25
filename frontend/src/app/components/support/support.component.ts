import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { SupportService } from '../../services/support.service';
import { AlertService } from '../../services/alert.service';
import { AuthService } from '../../services/auth.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';

@Component({
  selector: 'app-support',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, BreadcrumbsComponent],
  templateUrl: './support.component.html',
  styleUrl: './support.component.scss',
})
export class SupportComponent implements OnInit {
  form: FormGroup;
  loading = false;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Suporte' },
  ];

  constructor(
    private fb: FormBuilder,
    private supportService: SupportService,
    private alertService: AlertService,
    public authService: AuthService
  ) {
    const user = this.authService.getCurrentUser();
    this.form = this.fb.group({
      subject: ['', [Validators.required, Validators.minLength(5)]],
      message: ['', [Validators.required, Validators.minLength(10)]],
      name: [user?.name || '', user ? [] : [Validators.required]],
      email: [
        user?.email || '',
        user ? [] : [Validators.required, Validators.email],
      ],
    });
  }

  ngOnInit() {
    // Preenche dados do usuário se estiver autenticado
    const user = this.authService.getCurrentUser();
    if (user) {
      this.form.patchValue({
        name: user.name,
        email: user.email,
      });
    }
  }

  onSubmit() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.alertService.warning(
        'Formulário inválido',
        'Por favor, preencha todos os campos obrigatórios corretamente.'
      );
      return;
    }

    this.loading = true;
    const data = this.form.value;

    this.supportService.sendMessage(data).subscribe({
      next: () => {
        this.alertService.success(
          'Mensagem enviada!',
          'Sua mensagem foi enviada com sucesso. Entraremos em contato em breve.'
        );
        this.clearForm();
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao enviar mensagem',
          err.error?.message ||
            'Ocorreu um erro ao enviar sua mensagem. Tente novamente.'
        );
      },
    });
  }

  clearForm() {
    const user = this.authService.getCurrentUser();

    if (user) {
      // Se o usuário está autenticado, limpa apenas assunto e mensagem
      // Mantém nome e email
      this.form.patchValue({
        subject: '',
        message: '',
        name: user.name,
        email: user.email,
      });
    } else {
      // Se não está autenticado, limpa tudo
      this.form.reset();
    }

    // Remove estados de validação
    this.form.markAsUntouched();
  }
}

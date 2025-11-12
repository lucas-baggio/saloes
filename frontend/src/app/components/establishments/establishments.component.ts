import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { EstablishmentService } from '../../services/establishment.service';
import { Establishment } from '../../models/establishment.model';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';
import { TooltipDirective } from '../../directives/tooltip.directive';

@Component({
  selector: 'app-establishments',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterLink,
    BreadcrumbsComponent,
    TooltipDirective,
  ],
  templateUrl: './establishments.component.html',
  styleUrl: './establishments.component.scss',
})
export class EstablishmentsComponent implements OnInit {
  establishments: Establishment[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;
  error: string = '';
  isEmailVerified = false;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Estabelecimentos' },
  ];

  constructor(
    private establishmentService: EstablishmentService,
    private authService: AuthService,
    private alertService: AlertService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(3)]],
      description: [''],
    });
  }

  ngOnInit() {
    const user = this.authService.getCurrentUser();
    this.isEmailVerified = !!user?.email_verified_at;
    this.loadEstablishments();
  }

  loadEstablishments() {
    this.loading = true;
    this.establishmentService.getAll().subscribe({
      next: (data) => {
        this.establishments = data.data || data || [];
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      },
    });
  }

  openForm(establishment?: Establishment) {
    if (!this.isEmailVerified) {
      this.alertService.warning(
        'Email não verificado',
        'Você precisa verificar seu email para criar ou editar estabelecimentos.'
      );
      return;
    }

    if (establishment) {
      this.editingId = establishment.id;
      this.form.patchValue({
        name: establishment.name,
        description: establishment.description || '',
      });
    } else {
      this.editingId = null;
      this.form.reset();
    }
    this.showForm = true;
    this.error = '';
  }

  closeForm() {
    this.showForm = false;
    this.editingId = null;
    this.form.reset();
    this.error = '';
  }

  onSubmit() {
    if (this.form.valid) {
      this.loading = true;
      const data = {
        ...this.form.value,
        owner_id: this.authService.getCurrentUser()?.id,
      };

      const request = this.editingId
        ? this.establishmentService.update(this.editingId, data)
        : this.establishmentService.create(data);

      request.subscribe({
        next: () => {
          this.alertService.success(
            'Estabelecimento salvo',
            this.editingId
              ? 'O estabelecimento foi atualizado com sucesso.'
              : 'O estabelecimento foi criado com sucesso.'
          );
          this.loadEstablishments();
          this.closeForm();
        },
        error: (err) => {
          this.error = err.error?.message || 'Erro ao salvar estabelecimento';
          this.loading = false;
          this.alertService.validationError(err);
        },
      });
    }
  }

  delete(id: number) {
    this.alertService
      .confirm(
        'Excluir Estabelecimento',
        'Tem certeza que deseja excluir este estabelecimento? Esta ação não pode ser desfeita.',
        'Excluir',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.establishmentService.delete(id).subscribe({
            next: () => {
              this.alertService.success(
                'Estabelecimento excluído',
                'O estabelecimento foi excluído com sucesso.'
              );
              this.loadEstablishments();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.error(
                'Erro ao excluir',
                err.error?.message ||
                  'Não foi possível excluir o estabelecimento.'
              );
            },
          });
        }
      });
  }
}

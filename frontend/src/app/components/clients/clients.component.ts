import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
  FormsModule,
} from '@angular/forms';
import { ClientService } from '../../services/client.service';
import { Client } from '../../models/client.model';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';
import { TooltipDirective } from '../../directives/tooltip.directive';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-clients',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    RouterLink,
    DatePipe,
    BreadcrumbsComponent,
    TooltipDirective,
  ],
  templateUrl: './clients.component.html',
  styleUrl: './clients.component.scss',
})
export class ClientsComponent implements OnInit {
  clients: Client[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;
  searchTerm = '';
  isEmailVerified = false;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Clientes' },
  ];

  constructor(
    private clientService: ClientService,
    private authService: AuthService,
    private alertService: AlertService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(3)]],
      phone: [''],
      email: ['', [Validators.email]],
      cpf: [''],
      birth_date: [''],
      address: [''],
      anamnesis: [''],
      notes: [''],
      photo: [''],
      allergies: [[]],
    });
  }

  ngOnInit() {
    const user = this.authService.getCurrentUser();
    this.isEmailVerified = !!user?.email_verified_at;
    this.loadClients();
  }

  loadClients() {
    this.loading = true;
    const params: any = {};
    if (this.searchTerm) {
      params.search = this.searchTerm;
    }

    this.clientService.getAll(params).subscribe({
      next: (response) => {
        this.clients = response.data || response || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar clientes',
          err.error.message || 'Ocorreu um erro inesperado.'
        );
      },
    });
  }

  openForm(client?: Client) {
    if (!this.isEmailVerified) {
      this.alertService.warning(
        'Email não verificado',
        'Verifique seu email para criar ou editar clientes.'
      );
      return;
    }

    this.showForm = true;
    if (client) {
      this.editingId = client.id;
      this.form.patchValue({
        ...client,
        birth_date: client.birth_date
          ? new Date(client.birth_date).toISOString().split('T')[0]
          : '',
      });
    } else {
      this.editingId = null;
      this.form.reset({ allergies: [] });
    }
  }

  closeForm() {
    this.showForm = false;
    this.form.reset({ allergies: [] });
  }

  onSubmit() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.alertService.warning(
        'Formulário inválido',
        'Por favor, preencha todos os campos obrigatórios.'
      );
      return;
    }

    this.loading = true;
    const data = this.form.value;

    if (this.editingId) {
      this.clientService.update(this.editingId, data).subscribe({
        next: () => {
          this.alertService.success(
            'Cliente atualizado',
            'O cliente foi atualizado com sucesso.'
          );
          this.closeForm();
          this.loadClients();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.validationError(err);
        },
      });
    } else {
      this.clientService.create(data).subscribe({
        next: () => {
          this.alertService.success(
            'Cliente criado',
            'O cliente foi criado com sucesso.'
          );
          this.closeForm();
          this.loadClients();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.validationError(err);
        },
      });
    }
  }

  delete(id: number) {
    this.alertService
      .confirm(
        'Excluir Cliente',
        'Tem certeza que deseja excluir este cliente? Esta ação não pode ser desfeita.',
        'Excluir',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.clientService.delete(id).subscribe({
            next: () => {
              this.alertService.success(
                'Cliente excluído',
                'O cliente foi excluído com sucesso.'
              );
              this.loadClients();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.error(
                'Erro ao excluir cliente',
                err.error.message || 'Ocorreu um erro inesperado.'
              );
            },
          });
        }
      });
  }

  onSearch() {
    this.loadClients();
  }

  clearSearch() {
    this.searchTerm = '';
    this.loadClients();
  }

  async addAllergy() {
    const allergies = this.form.get('allergies')?.value || [];
    const allergy = await this.alertService.prompt(
      'Adicionar Alergia',
      'Digite o nome da alergia:',
      'Ex: Pólen, Ácaros, etc.'
    );
    if (allergy && allergy.trim()) {
      allergies.push(allergy.trim());
      this.form.patchValue({ allergies });
    }
  }

  removeAllergy(index: number) {
    const allergies = this.form.get('allergies')?.value || [];
    allergies.splice(index, 1);
    this.form.patchValue({ allergies });
  }

  onPhotoChange(event: any) {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e: any) => {
        this.form.patchValue({ photo: e.target.result });
      };
      reader.readAsDataURL(file);
    }
  }

  getPhotoUrl(client?: Client | string): string {
    // Se receber um Client, usar photo_url se disponível, senão photo
    if (typeof client === 'object' && client) {
      if (client.photo_url) {
        return this.buildPhotoUrl(client.photo_url);
      }
      if (client.photo) {
        return this.getPhotoUrl(client.photo);
      }
      return '';
    }

    // Se receber uma string (caminho ou URL)
    const photo = client as string;
    if (!photo) return '';

    return this.buildPhotoUrl(photo);
  }

  private buildPhotoUrl(photo: string): string {
    // Se já for uma URL completa (data:image, http, https), retornar como está
    if (
      photo.startsWith('data:image') ||
      photo.startsWith('http://') ||
      photo.startsWith('https://')
    ) {
      return photo;
    }

    // Se começar com /storage/, construir URL completa baseada no backend
    if (photo.startsWith('/storage/')) {
      // Remover /api do apiUrl e adicionar o caminho do storage
      const baseUrl = environment.apiUrl.replace('/api', '');
      return `${baseUrl}${photo}`;
    }

    // Se for apenas o caminho relativo, construir URL completa
    const baseUrl = environment.apiUrl.replace('/api', '');
    return `${baseUrl}/storage/${photo}`;
  }
}

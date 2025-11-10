import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { EstablishmentService } from '../../services/establishment.service';
import { Establishment } from '../../models/establishment.model';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-establishments',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
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

  constructor(
    private establishmentService: EstablishmentService,
    private authService: AuthService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(3)]],
      description: [''],
    });
  }

  ngOnInit() {
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
          this.loadEstablishments();
          this.closeForm();
        },
        error: (err) => {
          this.error = err.error?.message || 'Erro ao salvar estabelecimento';
          this.loading = false;
        },
      });
    }
  }

  delete(id: number) {
    if (confirm('Tem certeza que deseja excluir este estabelecimento?')) {
      this.loading = true;
      this.establishmentService.delete(id).subscribe({
        next: () => {
          this.loadEstablishments();
        },
        error: () => {
          this.loading = false;
        },
      });
    }
  }
}

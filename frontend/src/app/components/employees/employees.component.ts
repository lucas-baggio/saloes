import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { EmployeeService, Employee } from '../../services/employee.service';
import { AuthService } from '../../services/auth.service';
import { EstablishmentService } from '../../services/establishment.service';
import { Establishment } from '../../models/establishment.model';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-employees',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './employees.component.html',
  styleUrl: './employees.component.scss',
})
export class EmployeesComponent implements OnInit {
  employees: Employee[] = [];
  establishments: Establishment[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;

  constructor(
    private employeeService: EmployeeService,
    private authService: AuthService,
    private establishmentService: EstablishmentService,
    private alertService: AlertService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(3)]],
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      establishment_id: ['', [Validators.required]],
    });
  }

  ngOnInit() {
    this.loadEmployees();
    this.loadEstablishments();
  }

  loadEstablishments() {
    this.establishmentService.getAll().subscribe({
      next: (data) => {
        this.establishments = Array.isArray(data) ? data : data.data || [];
      },
    });
  }

  loadEmployees() {
    this.loading = true;
    this.employeeService.getAll().subscribe({
      next: (data) => {
        this.employees = data;
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar funcionários',
          'Não foi possível carregar a lista de funcionários.'
        );
      },
    });
  }

  openForm(employee?: Employee) {
    if (employee) {
      this.editingId = employee.id;
      this.form.patchValue({
        name: employee.name,
        email: employee.email,
      });
      // Não preencher senha e estabelecimento ao editar
      this.form.get('password')?.clearValidators();
      this.form.get('password')?.updateValueAndValidity();
      this.form.get('establishment_id')?.clearValidators();
      this.form.get('establishment_id')?.updateValueAndValidity();
    } else {
      this.editingId = null;
      this.form.reset();
      this.form
        .get('password')
        ?.setValidators([Validators.required, Validators.minLength(8)]);
      this.form.get('password')?.updateValueAndValidity();
      this.form.get('establishment_id')?.setValidators([Validators.required]);
      this.form.get('establishment_id')?.updateValueAndValidity();
    }
    this.showForm = true;
  }

  closeForm() {
    this.showForm = false;
    this.editingId = null;
    this.form.reset();
    this.form
      .get('password')
      ?.setValidators([Validators.required, Validators.minLength(8)]);
    this.form.get('password')?.updateValueAndValidity();
    this.form.get('establishment_id')?.setValidators([Validators.required]);
    this.form.get('establishment_id')?.updateValueAndValidity();
  }

  onSubmit() {
    if (this.form.valid) {
      this.loading = true;
      const data = { ...this.form.value };

      // Se estiver editando e não tiver senha, remover do payload
      if (this.editingId) {
        if (!data.password) {
          delete data.password;
        }
        // Não enviar establishment_id ao editar
        delete data.establishment_id;
      }

      const request = this.editingId
        ? this.employeeService.update(this.editingId, data)
        : this.employeeService.create(data);

      request.subscribe({
        next: () => {
          this.alertService.success(
            'Sucesso!',
            this.editingId
              ? 'Funcionário atualizado com sucesso.'
              : 'Funcionário criado com sucesso.'
          );
          this.loadEmployees();
          this.closeForm();
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
        'Excluir funcionário',
        'Tem certeza que deseja excluir este funcionário? Esta ação não pode ser desfeita.',
        'Sim, excluir',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.employeeService.delete(id).subscribe({
            next: () => {
              this.alertService.success(
                'Funcionário excluído',
                'O funcionário foi excluído com sucesso.'
              );
              this.loadEmployees();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.validationError(err);
            },
          });
        }
      });
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value);
  }
}

import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
  FormsModule,
} from '@angular/forms';
import { ServiceService } from '../../services/service.service';
import { EstablishmentService } from '../../services/establishment.service';
import { EmployeeService } from '../../services/employee.service';
import { Service, SubService } from '../../models/service.model';
import { Establishment } from '../../models/establishment.model';
import { Employee } from '../../services/employee.service';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-services',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './services.component.html',
  styleUrl: './services.component.scss',
})
export class ServicesComponent implements OnInit {
  services: Service[] = [];
  establishments: Establishment[] = [];
  employees: Employee[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;
  subServices: SubService[] = [];

  constructor(
    private serviceService: ServiceService,
    private establishmentService: EstablishmentService,
    private employeeService: EmployeeService,
    private authService: AuthService,
    private alertService: AlertService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(3)]],
      description: [''],
      price: ['', [Validators.min(0)]],
      establishment_id: ['', [Validators.required]],
      user_id: [''],
    });
  }

  ngOnInit() {
    this.loadServices();
    this.loadEstablishments();
    this.loadEmployees();
  }

  loadEmployees() {
    this.employeeService.getAll().subscribe({
      next: (data) => {
        this.employees = Array.isArray(data) ? data : [];
      },
    });
  }

  loadServices() {
    this.loading = true;
    this.serviceService.getAll().subscribe({
      next: (data) => {
        this.services = data.data || data || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar serviços',
          'Não foi possível carregar a lista de serviços.'
        );
      },
    });
  }

  loadEstablishments() {
    this.establishmentService.getAll().subscribe({
      next: (data) => {
        this.establishments = data.data || data || [];
      },
    });
  }

  openForm(service?: Service) {
    if (service) {
      this.editingId = service.id;
      this.form.patchValue({
        name: service.name,
        description: service.description || '',
        price: service.price,
        establishment_id: service.establishment_id,
        user_id: service.user_id || '',
      });
      this.subServices = service.sub_services || service.subServices || [];
    } else {
      this.editingId = null;
      this.form.reset();
      this.subServices = [];
    }
    this.showForm = true;
  }

  closeForm() {
    this.showForm = false;
    this.editingId = null;
    this.form.reset();
    this.subServices = [];
  }

  onSubmit() {
    if (this.form.valid && (this.form.value.price || this.subServices.length > 0)) {
      this.loading = true;
      const data: any = {
        name: this.form.value.name,
        description: this.form.value.description || null,
        establishment_id: this.form.value.establishment_id,
      };

      // Se tiver subserviços, enviar eles e não enviar preço (será calculado)
      if (this.subServices.length > 0) {
        data.sub_services = this.subServices.map((sub) => ({
          name: sub.name,
          description: sub.description || null,
          price: sub.price,
        }));
      } else {
        // Se não tiver subserviços, usar o preço informado
        data.price = parseFloat(this.form.value.price);
      }

      // Adicionar user_id se fornecido
      if (this.form.value.user_id) {
        data.user_id = this.form.value.user_id;
      }

      const request = this.editingId
        ? this.serviceService.update(this.editingId, data)
        : this.serviceService.create(data);

      request.subscribe({
        next: () => {
          this.alertService.success(
            'Sucesso!',
            this.editingId
              ? 'Serviço atualizado com sucesso.'
              : 'Serviço criado com sucesso.'
          );
          this.loadServices();
          this.closeForm();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.validationError(err);
        },
      });
    } else {
      if (!this.form.value.price && this.subServices.length === 0) {
        this.alertService.warning(
          'Atenção',
          'É necessário informar um preço ou criar pelo menos um subserviço.'
        );
      }
    }
  }

  delete(id: number) {
    this.alertService
      .confirm(
        'Excluir serviço',
        'Tem certeza que deseja excluir este serviço? Esta ação não pode ser desfeita.',
        'Sim, excluir',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.serviceService.delete(id).subscribe({
            next: () => {
              this.alertService.success(
                'Serviço excluído',
                'O serviço foi excluído com sucesso.'
              );
              this.loadServices();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.validationError(err);
            },
          });
        }
      });
  }

  addSubService() {
    this.subServices.push({
      id: 0,
      name: '',
      description: '',
      price: 0,
      service_id: 0,
    });
  }

  removeSubService(index: number) {
    this.subServices.splice(index, 1);
  }

  getTotalPrice(): number {
    if (this.subServices.length > 0) {
      return this.subServices.reduce((sum, sub) => sum + (sub.price || 0), 0);
    }
    return parseFloat(this.form.value.price) || 0;
  }

  getEmployeesForEstablishment(establishmentId: number): Employee[] {
    return this.employees.filter((emp) => {
      // Filtrar funcionários que trabalham no estabelecimento selecionado
      // Por enquanto retornar todos, pois não temos essa info no frontend
      return true;
    });
  }

  formatPrice(price: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(price);
  }
}

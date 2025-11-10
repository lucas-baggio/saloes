import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { SchedulingService } from '../../services/scheduling.service';
import { ServiceService } from '../../services/service.service';
import { EstablishmentService } from '../../services/establishment.service';
import { Scheduling, Service } from '../../models/service.model';
import { Establishment } from '../../models/establishment.model';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-schedulings',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './schedulings.component.html',
  styleUrl: './schedulings.component.scss',
})
export class SchedulingsComponent implements OnInit {
  schedulings: Scheduling[] = [];
  services: Service[] = [];
  establishments: Establishment[] = [];
  filteredServices: Service[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;

  constructor(
    private schedulingService: SchedulingService,
    private serviceService: ServiceService,
    private establishmentService: EstablishmentService,
    private alertService: AlertService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      scheduled_date: ['', [Validators.required]],
      scheduled_time: ['', [Validators.required]],
      establishment_id: ['', [Validators.required]],
      service_id: ['', [Validators.required]],
    });
  }

  ngOnInit() {
    this.loadSchedulings();
    this.loadEstablishments();
    this.loadServices();

    // Recarregar serviços quando o formulário for aberto
    // Isso garante que serviços recém-criados apareçam
  }

  loadEstablishments() {
    this.establishmentService.getAll().subscribe({
      next: (data) => {
        this.establishments = Array.isArray(data) ? data : data.data || [];
      },
    });
  }

  loadSchedulings() {
    this.loading = true;
    this.schedulingService.getAll().subscribe({
      next: (data) => {
        this.schedulings = data.data || data || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar agendamentos',
          'Não foi possível carregar a lista de agendamentos.'
        );
      },
    });
  }

  loadServices() {
    this.serviceService.getAll({ per_page: 1000 }).subscribe({
      next: (data) => {
        // Lidar com paginação ou array direto
        if (data.data) {
          this.services = Array.isArray(data.data) ? data.data : [];
        } else if (Array.isArray(data)) {
          this.services = data;
        } else {
          this.services = [];
        }
        // Se já tiver estabelecimento selecionado, filtrar novamente
        if (this.form.value.establishment_id) {
          this.filterServicesByEstablishment(this.form.value.establishment_id);
        }
      },
      error: (err) => {
        console.error('Erro ao carregar serviços:', err);
        this.services = [];
      },
    });
  }

  openForm(scheduling?: Scheduling) {
    // Recarregar serviços para garantir que serviços recém-criados apareçam
    this.serviceService.getAll({ per_page: 1000 }).subscribe({
      next: (data) => {
        // Lidar com paginação ou array direto
        if (data.data) {
          this.services = Array.isArray(data.data) ? data.data : [];
        } else if (Array.isArray(data)) {
          this.services = data;
        } else {
          this.services = [];
        }

        if (scheduling) {
          this.editingId = scheduling.id;
          const date = new Date(scheduling.scheduled_date);
          this.form.patchValue({
            scheduled_date: date.toISOString().split('T')[0],
            scheduled_time: scheduling.scheduled_time,
            establishment_id: scheduling.establishment_id,
            service_id: scheduling.service_id,
          });
          this.filterServicesByEstablishment(scheduling.establishment_id);
        } else {
          this.editingId = null;
          this.form.reset();
          this.filteredServices = [];
        }
        this.showForm = true;
      },
      error: (err) => {
        console.error('Erro ao carregar serviços:', err);
        this.services = [];
        this.showForm = true;
      },
    });
  }

  onEstablishmentChange() {
    const establishmentId = this.form.value.establishment_id;
    if (establishmentId) {
      this.filterServicesByEstablishment(Number(establishmentId));
      // Limpar service_id quando mudar estabelecimento
      this.form.patchValue({ service_id: '' });
    } else {
      this.filteredServices = [];
    }
  }

  filterServicesByEstablishment(establishmentId: number | string) {
    // Converter para número para garantir comparação correta
    const id = Number(establishmentId);
    if (!id || this.services.length === 0) {
      this.filteredServices = [];
      return;
    }
    this.filteredServices = this.services.filter(
      (service) => Number(service.establishment_id) === id
    );
  }

  closeForm() {
    this.showForm = false;
    this.editingId = null;
    this.form.reset();
    this.filteredServices = [];
  }

  onSubmit() {
    if (this.form.valid) {
      this.loading = true;
      // O input type="time" retorna no formato HH:mm, que é exatamente o que o backend espera (H:i)
      // Garantir que não tenha segundos
      let scheduledTime = this.form.value.scheduled_time;
      if (scheduledTime && scheduledTime.includes(':')) {
        // Remover segundos se houver (HH:mm:ss -> HH:mm)
        const parts = scheduledTime.split(':');
        scheduledTime = `${parts[0]}:${parts[1]}`;
      }

      const data = {
        scheduled_date: this.form.value.scheduled_date,
        scheduled_time: scheduledTime,
        establishment_id: Number(this.form.value.establishment_id),
        service_id: Number(this.form.value.service_id),
      };

      const request = this.editingId
        ? this.schedulingService.update(this.editingId, data)
        : this.schedulingService.create(data);

      request.subscribe({
        next: () => {
          this.alertService.success(
            'Sucesso!',
            this.editingId
              ? 'Agendamento atualizado com sucesso.'
              : 'Agendamento criado com sucesso.'
          );
          this.loadSchedulings();
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
        'Excluir agendamento',
        'Tem certeza que deseja excluir este agendamento? Esta ação não pode ser desfeita.',
        'Sim, excluir',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.schedulingService.delete(id).subscribe({
            next: () => {
              this.alertService.success(
                'Agendamento excluído',
                'O agendamento foi excluído com sucesso.'
              );
              this.loadSchedulings();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.validationError(err);
            },
          });
        }
      });
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('pt-BR');
  }

  getServiceName(serviceId: number): string {
    const service = this.services.find((s) => s.id === serviceId);
    return service ? service.name : 'Serviço não encontrado';
  }
}

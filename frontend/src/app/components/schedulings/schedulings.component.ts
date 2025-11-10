import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
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
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-schedulings',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './schedulings.component.html',
  styleUrl: './schedulings.component.scss',
})
export class SchedulingsComponent implements OnInit {
  schedulings: Scheduling[] = [];
  filteredSchedulings: Scheduling[] = [];
  services: Service[] = [];
  establishments: Establishment[] = [];
  filteredServices: Service[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;
  user: any = null;

  // Filtros
  searchTerm = '';
  filterDate = '';
  filterServiceId = '';
  filterEstablishmentId = '';
  filterStatus = '';

  constructor(
    private schedulingService: SchedulingService,
    private serviceService: ServiceService,
    private establishmentService: EstablishmentService,
    private alertService: AlertService,
    private authService: AuthService,
    private fb: FormBuilder,
    private cdr: ChangeDetectorRef
  ) {
    this.form = this.fb.group({
      scheduled_date: ['', [Validators.required]],
      scheduled_time: ['', [Validators.required]],
      establishment_id: ['', [Validators.required]],
      service_id: ['', [Validators.required]],
      client_name: ['', [Validators.required]],
      status: ['pending', [Validators.required]],
    });
  }

  ngOnInit() {
    this.user = this.authService.getCurrentUser();
    this.loadSchedulings();

    // Funcionários não precisam carregar estabelecimentos (não podem selecionar)
    if (this.user?.role !== 'employee') {
      this.loadEstablishments();
    }

    this.loadServices();
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
        const schedulingsData = data.data || data || [];
        this.schedulings = Array.isArray(schedulingsData)
          ? schedulingsData
          : [];
        // Aplicar filtros e forçar detecção de mudanças
        this.applyFilters();
        console.log('📊 Após carregar:', {
          schedulings: this.schedulings.length,
          filtered: this.filteredSchedulings.length,
          loading: this.loading,
          showForm: this.showForm,
        });
        this.loading = false;
        // Forçar detecção de mudanças após atualizar os dados
        setTimeout(() => {
          this.cdr.detectChanges();
        }, 0);
      },
      error: (err) => {
        console.error('❌ Erro ao carregar agendamentos:', err);
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar agendamentos',
          'Não foi possível carregar a lista de agendamentos.'
        );
      },
    });
  }

  applyFilters() {
    // Se não houver agendamentos, limpar a lista filtrada
    if (!this.schedulings || this.schedulings.length === 0) {
      this.filteredSchedulings = [];
      return;
    }

    let filtered = [...this.schedulings];

    // Filtro por busca (nome do cliente)
    if (this.searchTerm && this.searchTerm.trim()) {
      const search = this.searchTerm.toLowerCase().trim();
      filtered = filtered.filter(
        (s) =>
          s.client_name?.toLowerCase().includes(search) ||
          s.service?.name?.toLowerCase().includes(search)
      );
    }

    // Filtro por data
    if (this.filterDate) {
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      filtered = filtered.filter((s) => {
        if (!s.scheduled_date) return false;
        const scheduleDate = new Date(s.scheduled_date);
        scheduleDate.setHours(0, 0, 0, 0);

        switch (this.filterDate) {
          case 'today':
            return scheduleDate.getTime() === today.getTime();
          case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(weekAgo.getDate() - 7);
            return scheduleDate >= weekAgo && scheduleDate <= today;
          case 'month':
            const monthAgo = new Date(today);
            monthAgo.setMonth(monthAgo.getMonth() - 1);
            return scheduleDate >= monthAgo && scheduleDate <= today;
          case 'future':
            return scheduleDate > today;
          case 'past':
            return scheduleDate < today;
          default:
            return true;
        }
      });
    }

    // Filtro por serviço
    if (this.filterServiceId) {
      filtered = filtered.filter(
        (s) => s.service_id === Number(this.filterServiceId)
      );
    }

    // Filtro por estabelecimento
    if (this.filterEstablishmentId) {
      filtered = filtered.filter(
        (s) => s.establishment_id === Number(this.filterEstablishmentId)
      );
    }

    // Filtro por status
    if (this.filterStatus) {
      filtered = filtered.filter(
        (s) => (s.status || 'pending') === this.filterStatus
      );
    }

    // Ordenar por data e hora (mais recentes primeiro)
    filtered.sort((a, b) => {
      try {
        const dateA = new Date(
          `${a.scheduled_date}T${a.scheduled_time || '00:00'}`
        );
        const dateB = new Date(
          `${b.scheduled_date}T${b.scheduled_time || '00:00'}`
        );
        return dateB.getTime() - dateA.getTime();
      } catch (e) {
        return 0;
      }
    });

    this.filteredSchedulings = filtered;
  }

  onSearchChange() {
    this.applyFilters();
  }

  onFilterChange() {
    this.applyFilters();
  }

  clearFilters() {
    this.searchTerm = '';
    this.filterDate = '';
    this.filterServiceId = '';
    this.filterEstablishmentId = '';
    this.filterStatus = '';
    this.applyFilters();
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

        // Para funcionários, filtrar automaticamente
        if (this.user?.role === 'employee') {
          this.filterServicesByEstablishment('');
        } else if (this.form.value.establishment_id) {
          // Se já tiver estabelecimento selecionado, filtrar novamente
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
    const user = this.authService.getCurrentUser();

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
            client_name: scheduling.client_name || '',
            status: scheduling.status || 'pending',
          });

          // Para funcionários, filtrar serviços diretamente
          if (user?.role === 'employee') {
            this.filterServicesByEstablishment('');
          } else {
            this.filterServicesByEstablishment(scheduling.establishment_id);
          }
        } else {
          this.editingId = null;
          this.form.reset();

          // Para funcionários, já filtrar serviços atribuídos a ele
          if (user?.role === 'employee') {
            this.filterServicesByEstablishment('');
          } else {
            this.filteredServices = [];
          }
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
    const user = this.authService.getCurrentUser();

    // Para funcionários, não há mudança de estabelecimento
    if (user?.role === 'employee') {
      return;
    }

    const establishmentId = this.form.value.establishment_id;
    if (establishmentId) {
      this.filterServicesByEstablishment(Number(establishmentId));
      // Limpar service_id quando mudar estabelecimento
      this.form.patchValue({ service_id: '' });
    } else {
      this.filteredServices = [];
    }
  }

  onServiceChange() {
    const user = this.authService.getCurrentUser();
    const serviceId = this.form.value.service_id;

    // Para funcionários, preencher establishment_id automaticamente do serviço
    if (user?.role === 'employee' && serviceId) {
      const service = this.services.find((s) => s.id === Number(serviceId));
      if (service && service.establishment_id) {
        this.form.patchValue({ establishment_id: service.establishment_id });
      }
    }
  }

  filterServicesByEstablishment(establishmentId: number | string) {
    const user = this.authService.getCurrentUser();

    // Se for funcionário, mostrar apenas serviços atribuídos a ele
    if (user?.role === 'employee') {
      this.filteredServices = this.services.filter(
        (service) => service.user?.id === user.id
      );
      return;
    }

    // Para owners e admins, filtrar por estabelecimento
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
    // Forçar detecção de mudanças para garantir que a lista apareça
    this.cdr.detectChanges();
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
        client_name: this.form.value.client_name,
        status: this.form.value.status || 'pending',
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
          this.closeForm();
          // Recarregar agendamentos após fechar o formulário
          this.loadSchedulings();
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

  trackBySchedulingId(index: number, scheduling: Scheduling): number {
    return scheduling.id;
  }

  getStatusLabel(status?: string): string {
    const labels: { [key: string]: string } = {
      pending: 'Pendente',
      confirmed: 'Confirmado',
      completed: 'Concluído',
      cancelled: 'Cancelado',
    };
    return labels[status || 'pending'] || 'Pendente';
  }

  getStatusColor(status?: string): string {
    const colors: { [key: string]: string } = {
      pending: 'bg-yellow-100 text-yellow-800',
      confirmed: 'bg-blue-100 text-blue-800',
      completed: 'bg-green-100 text-green-800',
      cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status || 'pending'] || 'bg-yellow-100 text-yellow-800';
  }

  updateStatus(
    id: number,
    status: 'pending' | 'confirmed' | 'completed' | 'cancelled'
  ) {
    this.alertService
      .confirm(
        'Alterar status',
        `Deseja alterar o status para "${this.getStatusLabel(status)}"?`,
        'Sim, alterar',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.schedulingService.update(id, { status }).subscribe({
            next: () => {
              this.alertService.success(
                'Status atualizado',
                'O status do agendamento foi atualizado com sucesso.'
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
}

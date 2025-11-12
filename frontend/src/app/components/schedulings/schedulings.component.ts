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
import { ClientService } from '../../services/client.service';
import { Scheduling, Service } from '../../models/service.model';
import { Establishment } from '../../models/establishment.model';
import { Client } from '../../models/client.model';
import { AlertService } from '../../services/alert.service';
import { AuthService } from '../../services/auth.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';
import { TooltipDirective } from '../../directives/tooltip.directive';

@Component({
  selector: 'app-schedulings',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    BreadcrumbsComponent,
    TooltipDirective,
  ],
  templateUrl: './schedulings.component.html',
  styleUrl: './schedulings.component.scss',
})
export class SchedulingsComponent implements OnInit {
  schedulings: Scheduling[] = [];
  filteredSchedulings: Scheduling[] = [];
  allFilteredSchedulings: Scheduling[] = []; // Todos os agendamentos filtrados (antes da paginação)
  services: Service[] = [];
  establishments: Establishment[] = [];
  clients: Client[] = [];
  filteredServices: Service[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;
  user: any = null;
  isEmailVerified = false;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Agendamentos' },
  ];

  // Filtros
  searchTerm = '';
  filterDate = '';
  filterServiceId = '';
  filterEstablishmentId = '';
  filterStatus = '';

  // Paginação
  currentPage = 1;
  itemsPerPage = 10;
  totalPages = 1;

  // Ordenação
  sortField: 'date' | 'client' | 'service' | 'status' = 'date';
  sortDirection: 'asc' | 'desc' = 'desc';

  // Histórico de ações
  actionHistory: Array<{
    id: number;
    action: string;
    description: string;
    timestamp: Date;
  }> = [];
  showHistory = false;

  constructor(
    private schedulingService: SchedulingService,
    private serviceService: ServiceService,
    private establishmentService: EstablishmentService,
    private clientService: ClientService,
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
      client_id: [null],
      client_name: ['', [Validators.required]],
      status: ['pending', [Validators.required]],
    });

    // Quando um cliente for selecionado, preencher o nome automaticamente
    this.form.get('client_id')?.valueChanges.subscribe((clientId) => {
      if (clientId) {
        const client = this.clients.find((c) => c.id === clientId);
        if (client) {
          this.form.patchValue(
            { client_name: client.name },
            { emitEvent: false }
          );
        }
      }
    });
  }

  ngOnInit() {
    this.user = this.authService.getCurrentUser();
    this.isEmailVerified = !!this.user?.email_verified_at;
    this.loadSchedulings();

    // Funcionários não precisam carregar estabelecimentos (não podem selecionar)
    if (this.user?.role !== 'employee') {
      this.loadEstablishments();
      this.loadClients();
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

  loadClients() {
    // Apenas owners e admins podem ter clientes
    if (this.user?.role === 'employee') {
      return;
    }
    this.clientService.getAll().subscribe({
      next: (response) => {
        this.clients = response.data || response || [];
      },
      error: (err) => {
        // Não mostrar erro se não houver clientes cadastrados
        this.clients = [];
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
        // Aplicar filtros
        this.applyFilters();
        this.loading = false;
        // Forçar detecção de mudanças após atualizar os dados
        this.cdr.detectChanges();
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

    // Aplicar ordenação
    this.sortSchedulings(filtered);

    // Salvar todos os itens filtrados (antes da paginação)
    this.allFilteredSchedulings = filtered;

    // Calcular total de itens filtrados (antes da paginação)
    const totalFiltered = filtered.length;

    // Calcular paginação
    this.totalPages = Math.ceil(totalFiltered / this.itemsPerPage) || 1;
    if (this.currentPage > this.totalPages && this.totalPages > 0) {
      this.currentPage = 1;
    }

    // Aplicar paginação
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    const endIndex = startIndex + this.itemsPerPage;
    this.filteredSchedulings = filtered.slice(startIndex, endIndex);
  }

  sortSchedulings(schedulings: Scheduling[]) {
    schedulings.sort((a, b) => {
      let comparison = 0;

      switch (this.sortField) {
        case 'date':
          try {
            const dateA = new Date(
              `${a.scheduled_date}T${a.scheduled_time || '00:00'}`
            );
            const dateB = new Date(
              `${b.scheduled_date}T${b.scheduled_time || '00:00'}`
            );
            comparison = dateA.getTime() - dateB.getTime();
          } catch (e) {
            comparison = 0;
          }
          break;
        case 'client':
          const clientA = (a.client_name || '').toLowerCase();
          const clientB = (b.client_name || '').toLowerCase();
          comparison = clientA.localeCompare(clientB);
          break;
        case 'service':
          const serviceA = (a.service?.name || '').toLowerCase();
          const serviceB = (b.service?.name || '').toLowerCase();
          comparison = serviceA.localeCompare(serviceB);
          break;
        case 'status':
          const statusA = (a.status || 'pending').toLowerCase();
          const statusB = (b.status || 'pending').toLowerCase();
          comparison = statusA.localeCompare(statusB);
          break;
      }

      return this.sortDirection === 'asc' ? comparison : -comparison;
    });
  }

  changeSort(field: 'date' | 'client' | 'service' | 'status') {
    if (this.sortField === field) {
      // Se já está ordenando por este campo, inverte a direção
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      // Se é um novo campo, define como descendente por padrão
      this.sortField = field;
      this.sortDirection = 'desc';
    }
    this.currentPage = 1; // Resetar para primeira página
    this.applyFilters();
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
      this.applyFilters();
    }
  }

  get paginatedSchedulings(): Scheduling[] {
    return this.filteredSchedulings;
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

  getActiveFiltersCount(): number {
    let count = 0;
    if (this.searchTerm && this.searchTerm.trim()) count++;
    if (this.filterDate) count++;
    if (this.filterServiceId) count++;
    if (this.filterEstablishmentId) count++;
    if (this.filterStatus) count++;
    return count;
  }

  getFilterLabel(filterType: string): string {
    switch (filterType) {
      case 'searchTerm':
        return `Busca: "${this.searchTerm}"`;
      case 'filterDate':
        const dateLabels: { [key: string]: string } = {
          today: 'Hoje',
          week: 'Esta semana',
          month: 'Este mês',
          year: 'Este ano',
        };
        return `Data: ${dateLabels[this.filterDate] || this.filterDate}`;
      case 'filterServiceId':
        const service = this.services.find(
          (s) => s.id === Number(this.filterServiceId)
        );
        return `Serviço: ${service?.name || 'N/A'}`;
      case 'filterEstablishmentId':
        const establishment = this.establishments.find(
          (e) => e.id === Number(this.filterEstablishmentId)
        );
        return `Estabelecimento: ${establishment?.name || 'N/A'}`;
      case 'filterStatus':
        const statusLabels: { [key: string]: string } = {
          pending: 'Pendente',
          confirmed: 'Confirmado',
          cancelled: 'Cancelado',
          completed: 'Concluído',
        };
        return `Status: ${
          statusLabels[this.filterStatus] || this.filterStatus
        }`;
      default:
        return '';
    }
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
    if (!this.isEmailVerified) {
      this.alertService.warning(
        'Email não verificado',
        'Você precisa verificar seu email para criar ou editar agendamentos.'
      );
      return;
    }

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
          // Se a data já vier no formato YYYY-MM-DD, usar diretamente
          // Caso contrário, converter
          let dateStr = scheduling.scheduled_date;
          if (dateStr && !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            const date = new Date(scheduling.scheduled_date);
            dateStr = date.toISOString().split('T')[0];
          }
          this.form.patchValue({
            scheduled_date: dateStr,
            scheduled_time: scheduling.scheduled_time,
            establishment_id: scheduling.establishment_id,
            service_id: scheduling.service_id,
            client_id: scheduling.client_id || null,
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
          this.form.reset({
            status: 'pending',
            client_id: null,
          });

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

  onDateChange() {
    // Quando a data mudar, validar se a hora ainda é válida
    // Se a data selecionada for hoje, atualizar o minTime
    const selectedDate = this.form.value.scheduled_date;
    if (selectedDate) {
      const today = new Date();
      const selected = new Date(selectedDate + 'T00:00:00');
      const isToday = selected.toDateString() === today.toDateString();

      if (isToday) {
        const currentTime = this.minTime;
        const selectedTime = this.form.value.scheduled_time;

        // Se a hora selecionada for menor que a hora mínima, limpar
        if (selectedTime && selectedTime < currentTime) {
          this.form.patchValue({ scheduled_time: '' });
        }
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
    this.form.reset({
      status: 'pending',
      client_id: null,
    });
    this.filteredServices = [];
    // Forçar detecção de mudanças para garantir que a lista apareça
    this.cdr.detectChanges();
  }

  onClientChange(event: any) {
    const clientId = event.target.value;
    if (clientId) {
      const client = this.clients.find((c) => c.id === Number(clientId));
      if (client) {
        this.form.patchValue(
          { client_name: client.name },
          { emitEvent: false }
        );
      }
    } else {
      this.form.patchValue({ client_name: '' }, { emitEvent: false });
    }
  }

  get minDate(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  get minTime(): string {
    const today = new Date();
    const selectedDate = this.form.value.scheduled_date;

    if (selectedDate) {
      const selected = new Date(selectedDate + 'T00:00:00');
      const isToday = selected.toDateString() === today.toDateString();

      if (isToday) {
        const hours = String(today.getHours()).padStart(2, '0');
        const minutes = String(today.getMinutes() + 1).padStart(2, '0'); // +1 minuto para evitar conflito
        return `${hours}:${minutes}`;
      }
    }

    return '00:00';
  }

  onSubmit() {
    if (this.form.valid) {
      // A validação de data/hora no passado é feita no backend
      // Removida validação do frontend para evitar problemas de timezone

      const scheduledDate = this.form.value.scheduled_date;
      const scheduledTime = this.form.value.scheduled_time;

      this.loading = true;
      // O input type="time" retorna no formato HH:mm, que é exatamente o que o backend espera (H:i)
      // Garantir que não tenha segundos
      let timeValue = scheduledTime;
      if (timeValue && timeValue.includes(':')) {
        // Remover segundos se houver (HH:mm:ss -> HH:mm)
        const parts = timeValue.split(':');
        timeValue = `${parts[0]}:${parts[1]}`;
      }

      const data = {
        scheduled_date: scheduledDate,
        scheduled_time: timeValue,
        establishment_id: Number(this.form.value.establishment_id),
        service_id: Number(this.form.value.service_id),
        client_id: this.form.value.client_id || null,
        client_name: this.form.value.client_name,
        status: this.form.value.status || 'pending',
      };

      const request = this.editingId
        ? this.schedulingService.update(this.editingId, data)
        : this.schedulingService.create(data);

      request.subscribe({
        next: () => {
          const action = this.editingId ? 'atualizado' : 'criado';
          const schedulingId = this.editingId || Date.now(); // Usar timestamp temporário se for criação
          this.addToHistory(
            schedulingId,
            action === 'criado' ? 'create' : 'update',
            `Agendamento ${action === 'criado' ? 'criado' : 'atualizado'}: ${
              this.form.value.client_name
            } - ${this.formatDate(this.form.value.scheduled_date)}`
          );
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
              const scheduling = this.schedulings.find((s) => s.id === id);
              this.addToHistory(
                id,
                'delete',
                `Agendamento excluído: ${
                  scheduling?.client_name || 'Cliente desconhecido'
                } - ${
                  scheduling ? this.formatDate(scheduling.scheduled_date) : ''
                }`
              );
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
    // Se a data já vier no formato YYYY-MM-DD, formatar diretamente
    // para evitar problemas de timezone com new Date()
    if (date && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
      const [year, month, day] = date.split('-');
      return `${day}/${month}/${year}`;
    }
    // Se não for formato YYYY-MM-DD, tentar parsear normalmente
    const dateObj = new Date(date);
    if (isNaN(dateObj.getTime())) {
      return date; // Retornar a string original se não conseguir parsear
    }
    return dateObj.toLocaleDateString('pt-BR');
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value);
  }

  addToHistory(id: number, action: string, description: string) {
    this.actionHistory.unshift({
      id: this.actionHistory.length + 1,
      action,
      description,
      timestamp: new Date(),
    });
    // Manter apenas os últimos 50 registros
    if (this.actionHistory.length > 50) {
      this.actionHistory = this.actionHistory.slice(0, 50);
    }
  }

  getActionIcon(action: string): string {
    const icons: { [key: string]: string } = {
      create: 'M12 4v16m8-8H4',
      update:
        'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
      delete:
        'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
      status_change: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    };
    return (
      icons[action] ||
      'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    );
  }

  getActionColor(action: string): string {
    const colors: { [key: string]: string } = {
      create: 'text-green-600 bg-green-100',
      update: 'text-blue-600 bg-blue-100',
      delete: 'text-red-600 bg-red-100',
      status_change: 'text-purple-600 bg-purple-100',
    };
    return colors[action] || 'text-gray-600 bg-gray-100';
  }

  formatHistoryTime(date: Date): string {
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (minutes < 1) return 'Agora';
    if (minutes < 60) return `${minutes} min atrás`;
    if (hours < 24) return `${hours} h atrás`;
    if (days < 7) return `${days} dias atrás`;
    return date.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  getServiceName(serviceId: number): string {
    const service = this.services.find((s) => s.id === serviceId);
    return service ? service.name : 'Serviço não encontrado';
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

  // Expose Math to template
  Math = Math;

  exportToCSV() {
    if (this.allFilteredSchedulings.length === 0) {
      this.alertService.warning(
        'Nenhum dado para exportar',
        'Não há agendamentos para exportar com os filtros aplicados.'
      );
      return;
    }

    // Preparar dados para CSV
    const headers = [
      'ID',
      'Data',
      'Hora',
      'Cliente',
      'Serviço',
      'Estabelecimento',
      'Status',
      'Valor',
    ];

    const rows = this.allFilteredSchedulings.map((s) => {
      const service = this.services.find((sv) => sv.id === s.service_id);
      const establishment = this.establishments.find(
        (e) => e.id === s.establishment_id
      );
      const price = service?.price || 0;

      return [
        s.id,
        this.formatDate(s.scheduled_date),
        s.scheduled_time,
        s.client_name || '',
        service?.name || 'Serviço não encontrado',
        establishment?.name || 'Estabelecimento não encontrado',
        this.getStatusLabel(s.status),
        this.formatCurrency(price),
      ];
    });

    // Criar conteúdo CSV
    const csvContent = [
      headers.join(','),
      ...rows.map((row) =>
        row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')
      ),
    ].join('\n');

    // Adicionar BOM para Excel reconhecer UTF-8
    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csvContent], {
      type: 'text/csv;charset=utf-8;',
    });

    // Criar link de download
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute(
      'download',
      `agendamentos_${new Date().toISOString().split('T')[0]}.csv`
    );
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    this.alertService.success(
      'Exportação concluída',
      `${this.allFilteredSchedulings.length} agendamento(s) exportado(s) com sucesso.`
    );
  }

  getStatusColor(status?: string): string {
    const colors: { [key: string]: string } = {
      pending: 'bg-amber-100 text-amber-800 border border-amber-200',
      confirmed: 'bg-blue-100 text-blue-800 border border-blue-200',
      completed: 'bg-green-100 text-green-800 border border-green-200',
      cancelled: 'bg-red-100 text-red-800 border border-red-200',
    };
    return (
      colors[status || 'pending'] ||
      'bg-amber-100 text-amber-800 border border-amber-200'
    );
  }

  getStatusIcon(status?: string): string {
    const icons: { [key: string]: string } = {
      pending: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
      confirmed: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      completed: 'M5 13l4 4L19 7',
      cancelled: 'M6 18L18 6M6 6l12 12',
    };
    return (
      icons[status || 'pending'] ||
      'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
    );
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
              const scheduling = this.schedulings.find((s) => s.id === id);
              this.addToHistory(
                id,
                'status_change',
                `Status alterado para "${this.getStatusLabel(status)}": ${
                  scheduling?.client_name || 'Cliente desconhecido'
                }`
              );
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

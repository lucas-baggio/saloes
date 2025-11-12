import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe, CurrencyPipe } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
  FormsModule,
} from '@angular/forms';
import { SaleService } from '../../services/sale.service';
import { ClientService } from '../../services/client.service';
import { ServiceService } from '../../services/service.service';
import { EstablishmentService } from '../../services/establishment.service';
import { Sale } from '../../models/sale.model';
import { Client } from '../../models/client.model';
import { Service } from '../../models/service.model';
import { Establishment } from '../../models/establishment.model';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';

@Component({
  selector: 'app-sales',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    DatePipe,
    CurrencyPipe,
    BreadcrumbsComponent,
  ],
  templateUrl: './sales.component.html',
  styleUrl: './sales.component.scss',
})
export class SalesComponent implements OnInit {
  sales: Sale[] = [];
  clients: Client[] = [];
  services: Service[] = [];
  establishments: Establishment[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;
  searchTerm = '';
  filterStatus = '';
  filterPaymentMethod = '';
  filterFromDate = '';
  filterToDate = '';
  filterEstablishmentId = '';
  isEmailVerified = false;
  isEmployee = false;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Vendas' },
  ];

  paymentMethods = [
    { value: 'pix', label: 'PIX' },
    { value: 'cartao_credito', label: 'Cartão de Crédito' },
    { value: 'cartao_debito', label: 'Cartão de Débito' },
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'outro', label: 'Outro' },
  ];

  statusOptions = [
    { value: 'pending', label: 'Pendente' },
    { value: 'paid', label: 'Pago' },
    { value: 'cancelled', label: 'Cancelado' },
  ];

  constructor(
    private saleService: SaleService,
    private clientService: ClientService,
    private serviceService: ServiceService,
    private establishmentService: EstablishmentService,
    private authService: AuthService,
    private alertService: AlertService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      client_id: [null],
      service_id: [null],
      scheduling_id: [null],
      establishment_id: ['', Validators.required],
      user_id: [null], // Funcionário responsável (será preenchido automaticamente pelo serviço)
      amount: ['', [Validators.required, Validators.min(0.01)]],
      payment_method: ['pix', Validators.required],
      sale_date: ['', Validators.required],
      status: ['pending', Validators.required],
      notes: [''],
    });
  }

  ngOnInit() {
    const user = this.authService.getCurrentUser();
    this.isEmailVerified = !!user?.email_verified_at;
    this.isEmployee = user?.role === 'employee';
    this.loadEstablishments();
    this.loadClients();
    this.loadSales();
  }

  loadSales() {
    this.loading = true;
    const params: any = {};
    if (this.searchTerm) params.search = this.searchTerm;
    if (this.filterStatus) params.status = this.filterStatus;
    if (this.filterPaymentMethod)
      params.payment_method = this.filterPaymentMethod;
    if (this.filterFromDate) params.from = this.filterFromDate;
    if (this.filterToDate) params.to = this.filterToDate;
    if (this.filterEstablishmentId)
      params.establishment_id = this.filterEstablishmentId;

    this.saleService.getAll(params).subscribe({
      next: (response) => {
        this.sales = response.data || response || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar vendas',
          err.error.message || 'Ocorreu um erro inesperado.'
        );
      },
    });
  }

  loadClients() {
    this.clientService.getAll({ per_page: 1000 }).subscribe({
      next: (response) => {
        this.clients = response.data || response || [];
      },
      error: () => {
        // Silenciar erro, pode não ter permissão
      },
    });
  }

  loadServices(establishmentId?: number) {
    const params: any = { per_page: 1000 };
    if (establishmentId) params.establishment_id = establishmentId;

    this.serviceService.getAll(params).subscribe({
      next: (response) => {
        this.services = response.data || response || [];
      },
      error: () => {
        // Silenciar erro
      },
    });
  }

  loadEstablishments() {
    this.establishmentService.getAll().subscribe({
      next: (response) => {
        this.establishments = response.data || response || [];
        if (
          this.establishments.length > 0 &&
          !this.form.get('establishment_id')?.value
        ) {
          this.form.patchValue({ establishment_id: this.establishments[0].id });
          this.loadServices(this.establishments[0].id);
        }
      },
      error: () => {
        // Silenciar erro
      },
    });
  }

  onEstablishmentChange(establishmentId: number) {
    this.loadServices(establishmentId);
    // Limpar service_id e user_id quando mudar estabelecimento
    this.form.patchValue({ service_id: null, user_id: null });
  }

  onServiceChange(serviceId: number | string) {
    const serviceIdNum =
      typeof serviceId === 'string' ? parseInt(serviceId) : serviceId;
    if (!serviceIdNum || isNaN(serviceIdNum)) {
      // Se não selecionou nenhum serviço, limpar valor e funcionário
      this.form.patchValue({ amount: '', user_id: null });
      return;
    }
    const service = this.services.find((s) => s.id === serviceIdNum);
    if (service) {
      this.form.patchValue({
        amount: service.price,
        // Se o serviço tem um funcionário atribuído, usar ele
        user_id: service.user_id || null,
      });
    } else {
      // Serviço não encontrado, limpar
      this.form.patchValue({ amount: '', user_id: null });
    }
  }

  openForm(sale?: Sale) {
    if (!this.isEmailVerified) {
      this.alertService.warning(
        'Email não verificado',
        'Verifique seu email para criar ou editar vendas.'
      );
      return;
    }

    this.showForm = true;
    if (sale) {
      this.editingId = sale.id;
      const saleDate = sale.sale_date;
      let dateStr = saleDate;
      if (saleDate && !/^\d{4}-\d{2}-\d{2}$/.test(saleDate)) {
        const date = new Date(sale.sale_date);
        dateStr = date.toISOString().split('T')[0];
      }
      this.form.patchValue({
        ...sale,
        sale_date: dateStr,
      });
      if (sale.establishment_id) {
        this.loadServices(sale.establishment_id);
      }
    } else {
      this.editingId = null;
      this.form.reset({
        establishment_id: this.establishments[0]?.id || '',
        payment_method: 'pix',
        status: 'pending',
        sale_date: new Date().toISOString().split('T')[0],
      });
      if (this.establishments[0]?.id) {
        this.loadServices(this.establishments[0].id);
      }
    }
  }

  closeForm() {
    this.showForm = false;
    this.form.reset({
      establishment_id: this.establishments[0]?.id || '',
      payment_method: 'pix',
      status: 'pending',
    });
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
      this.saleService.update(this.editingId, data).subscribe({
        next: () => {
          this.alertService.success(
            'Venda atualizada',
            'A venda foi atualizada com sucesso.'
          );
          this.closeForm();
          this.loadSales();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.validationError(err);
        },
      });
    } else {
      this.saleService.create(data).subscribe({
        next: () => {
          this.alertService.success(
            'Venda criada',
            'A venda foi criada com sucesso.'
          );
          this.closeForm();
          this.loadSales();
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
        'Excluir Venda',
        'Tem certeza que deseja excluir esta venda? Esta ação não pode ser desfeita.',
        'Excluir',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.saleService.delete(id).subscribe({
            next: () => {
              this.alertService.success(
                'Venda excluída',
                'A venda foi excluída com sucesso.'
              );
              this.loadSales();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.error(
                'Erro ao excluir venda',
                err.error.message || 'Ocorreu um erro inesperado.'
              );
            },
          });
        }
      });
  }

  onSearch() {
    this.loadSales();
  }

  clearFilters() {
    this.searchTerm = '';
    this.filterStatus = '';
    this.filterPaymentMethod = '';
    this.filterFromDate = '';
    this.filterToDate = '';
    this.filterEstablishmentId = '';
    this.loadSales();
  }

  getStatusColor(status?: string): string {
    const colors: { [key: string]: string } = {
      pending: 'bg-amber-100 text-amber-800 border border-amber-200',
      paid: 'bg-green-100 text-green-800 border border-green-200',
      cancelled: 'bg-red-100 text-red-800 border border-red-200',
    };
    return (
      colors[status || 'pending'] ||
      'bg-amber-100 text-amber-800 border border-amber-200'
    );
  }

  getStatusLabel(status?: string): string {
    const labels: { [key: string]: string } = {
      pending: 'Pendente',
      paid: 'Pago',
      cancelled: 'Cancelado',
    };
    return labels[status || 'pending'] || 'Pendente';
  }

  getPaymentMethodLabel(method?: string): string {
    const methodObj = this.paymentMethods.find((m) => m.value === method);
    return methodObj?.label || method || 'N/A';
  }

  formatDate(date: string): string {
    if (date && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
      const [year, month, day] = date.split('-');
      return `${day}/${month}/${year}`;
    }
    const dateObj = new Date(date);
    if (isNaN(dateObj.getTime())) {
      return date;
    }
    return dateObj.toLocaleDateString('pt-BR');
  }
}

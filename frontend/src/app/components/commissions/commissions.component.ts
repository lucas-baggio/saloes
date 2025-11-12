import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe, CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CommissionService } from '../../services/commission.service';
import { Commission } from '../../models/commission.model';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';

@Component({
  selector: 'app-commissions',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    DatePipe,
    CurrencyPipe,
    BreadcrumbsComponent,
  ],
  templateUrl: './commissions.component.html',
  styleUrl: './commissions.component.scss',
})
export class CommissionsComponent implements OnInit {
  commissions: Commission[] = [];
  loading = false;
  searchTerm = '';
  filterStatus = '';
  filterFromDate = '';
  filterToDate = '';
  isEmailVerified = false;
  isEmployee = false;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Comissões' },
  ];

  statusOptions = [
    { value: 'pending', label: 'Pendente' },
    { value: 'paid', label: 'Paga' },
    { value: 'cancelled', label: 'Cancelada' },
  ];

  constructor(
    private commissionService: CommissionService,
    private authService: AuthService,
    private alertService: AlertService
  ) {}

  ngOnInit() {
    const user = this.authService.getCurrentUser();
    this.isEmailVerified = !!user?.email_verified_at;
    this.isEmployee = user?.role === 'employee';
    this.loadCommissions();
  }

  loadCommissions() {
    this.loading = true;
    const params: any = {};
    if (this.searchTerm) params.search = this.searchTerm;
    if (this.filterStatus) params.status = this.filterStatus;
    if (this.filterFromDate) params.from = this.filterFromDate;
    if (this.filterToDate) params.to = this.filterToDate;

    this.commissionService.getAll(params).subscribe({
      next: (response) => {
        this.commissions = response.data || response || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar comissões',
          err.error.message || 'Ocorreu um erro inesperado.'
        );
      },
    });
  }

  markAsPaid(commission: Commission) {
    this.alertService
      .confirm(
        'Marcar como Paga',
        'Deseja marcar esta comissão como paga?',
        'Confirmar',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          const paymentDate = new Date().toISOString().split('T')[0];
          this.commissionService
            .markAsPaid(commission.id, paymentDate)
            .subscribe({
              next: () => {
                this.alertService.success(
                  'Comissão marcada como paga',
                  'A comissão foi atualizada com sucesso.'
                );
                this.loadCommissions();
              },
              error: (err) => {
                this.loading = false;
                this.alertService.error(
                  'Erro ao atualizar comissão',
                  err.error.message || 'Ocorreu um erro inesperado.'
                );
              },
            });
        }
      });
  }

  onSearch() {
    this.loadCommissions();
  }

  clearFilters() {
    this.searchTerm = '';
    this.filterStatus = '';
    this.filterFromDate = '';
    this.filterToDate = '';
    this.loadCommissions();
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
      paid: 'Paga',
      cancelled: 'Cancelada',
    };
    return labels[status || 'pending'] || 'Pendente';
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

  getTotalPending(): number {
    return this.commissions
      .filter((c) => c.status === 'pending')
      .reduce((sum, c) => sum + c.amount, 0);
  }

  getTotalPaid(): number {
    return this.commissions
      .filter((c) => c.status === 'paid')
      .reduce((sum, c) => sum + c.amount, 0);
  }
}

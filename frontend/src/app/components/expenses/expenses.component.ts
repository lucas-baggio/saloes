import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe, CurrencyPipe } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
  FormsModule,
} from '@angular/forms';
import { ExpenseService } from '../../services/expense.service';
import { EstablishmentService } from '../../services/establishment.service';
import { Expense } from '../../models/expense.model';
import { Establishment } from '../../models/establishment.model';
import { AuthService } from '../../services/auth.service';
import { AlertService } from '../../services/alert.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';

@Component({
  selector: 'app-expenses',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    DatePipe,
    CurrencyPipe,
    BreadcrumbsComponent,
  ],
  templateUrl: './expenses.component.html',
  styleUrl: './expenses.component.scss',
})
export class ExpensesComponent implements OnInit {
  expenses: Expense[] = [];
  establishments: Establishment[] = [];
  loading = false;
  showForm = false;
  editingId: number | null = null;
  form: FormGroup;
  searchTerm = '';
  filterStatus = '';
  filterCategory = '';
  filterFromDate = '';
  filterToDate = '';
  filterEstablishmentId = '';
  isEmailVerified = false;
  isEmployee = false;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Despesas' },
  ];

  categories = [
    { value: 'aluguel', label: 'Aluguel' },
    { value: 'salarios', label: 'Salários' },
    { value: 'materiais', label: 'Materiais' },
    { value: 'servicos', label: 'Serviços' },
    { value: 'marketing', label: 'Marketing' },
    { value: 'utilities', label: 'Utilidades (água, luz, internet)' },
    { value: 'manutencao', label: 'Manutenção' },
    { value: 'outros', label: 'Outros' },
  ];

  paymentMethods = [
    { value: 'pix', label: 'PIX' },
    { value: 'cartao_credito', label: 'Cartão de Crédito' },
    { value: 'cartao_debito', label: 'Cartão de Débito' },
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'transferencia', label: 'Transferência' },
    { value: 'boleto', label: 'Boleto' },
    { value: 'outro', label: 'Outro' },
  ];

  statusOptions = [
    { value: 'pending', label: 'Pendente' },
    { value: 'paid', label: 'Paga' },
    { value: 'overdue', label: 'Vencida' },
  ];

  constructor(
    private expenseService: ExpenseService,
    private establishmentService: EstablishmentService,
    private authService: AuthService,
    private alertService: AlertService,
    private fb: FormBuilder
  ) {
    this.form = this.fb.group({
      establishment_id: ['', Validators.required],
      description: ['', [Validators.required, Validators.minLength(3)]],
      category: ['', Validators.required],
      amount: ['', [Validators.required, Validators.min(0.01)]],
      due_date: ['', Validators.required],
      payment_date: [null],
      payment_method: ['pix', Validators.required],
      status: ['pending', Validators.required],
      notes: [''],
    });
  }

  ngOnInit() {
    const user = this.authService.getCurrentUser();
    this.isEmailVerified = !!user?.email_verified_at;
    this.isEmployee = user?.role === 'employee';
    this.loadEstablishments();
    this.loadExpenses();
  }

  loadExpenses() {
    this.loading = true;
    const params: any = {};
    if (this.searchTerm) params.search = this.searchTerm;
    if (this.filterStatus) params.status = this.filterStatus;
    if (this.filterCategory) params.category = this.filterCategory;
    if (this.filterFromDate) params.from = this.filterFromDate;
    if (this.filterToDate) params.to = this.filterToDate;
    if (this.filterEstablishmentId)
      params.establishment_id = this.filterEstablishmentId;

    this.expenseService.getAll(params).subscribe({
      next: (response) => {
        this.expenses = response.data || response || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(
          'Erro ao carregar despesas',
          err.error.message || 'Ocorreu um erro inesperado.'
        );
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
        }
      },
      error: () => {
        // Silenciar erro
      },
    });
  }

  openForm(expense?: Expense) {
    if (!this.isEmailVerified) {
      this.alertService.warning(
        'Email não verificado',
        'Verifique seu email para criar ou editar despesas.'
      );
      return;
    }

    if (this.isEmployee) {
      this.alertService.warning(
        'Acesso restrito',
        'Funcionários não podem criar ou editar despesas.'
      );
      return;
    }

    this.showForm = true;
    if (expense) {
      this.editingId = expense.id;
      const dueDate = expense.due_date;
      let dueDateStr = dueDate;
      if (dueDate && !/^\d{4}-\d{2}-\d{2}$/.test(dueDate)) {
        const date = new Date(expense.due_date);
        dueDateStr = date.toISOString().split('T')[0];
      }
      let paymentDateStr = expense.payment_date || null;
      if (paymentDateStr && !/^\d{4}-\d{2}-\d{2}$/.test(paymentDateStr)) {
        const date = new Date(expense.payment_date!);
        paymentDateStr = date.toISOString().split('T')[0];
      }
      this.form.patchValue({
        ...expense,
        due_date: dueDateStr,
        payment_date: paymentDateStr,
      });
    } else {
      this.editingId = null;
      this.form.reset({
        establishment_id: this.establishments[0]?.id || '',
        payment_method: 'pix',
        status: 'pending',
        due_date: new Date().toISOString().split('T')[0],
      });
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
      this.expenseService.update(this.editingId, data).subscribe({
        next: () => {
          this.alertService.success(
            'Despesa atualizada',
            'A despesa foi atualizada com sucesso.'
          );
          this.closeForm();
          this.loadExpenses();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.validationError(err);
        },
      });
    } else {
      this.expenseService.create(data).subscribe({
        next: () => {
          this.alertService.success(
            'Despesa criada',
            'A despesa foi criada com sucesso.'
          );
          this.closeForm();
          this.loadExpenses();
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
        'Excluir Despesa',
        'Tem certeza que deseja excluir esta despesa? Esta ação não pode ser desfeita.',
        'Excluir',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          this.expenseService.delete(id).subscribe({
            next: () => {
              this.alertService.success(
                'Despesa excluída',
                'A despesa foi excluída com sucesso.'
              );
              this.loadExpenses();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.error(
                'Erro ao excluir despesa',
                err.error.message || 'Ocorreu um erro inesperado.'
              );
            },
          });
        }
      });
  }

  markAsPaid(expense: Expense) {
    this.alertService
      .confirm(
        'Marcar como Paga',
        'Deseja marcar esta despesa como paga?',
        'Confirmar',
        'Cancelar'
      )
      .then((result) => {
        if (result.isConfirmed) {
          this.loading = true;
          const paymentDate = new Date().toISOString().split('T')[0];
          this.expenseService.markAsPaid(expense.id, paymentDate).subscribe({
            next: () => {
              this.alertService.success(
                'Despesa marcada como paga',
                'A despesa foi atualizada com sucesso.'
              );
              this.loadExpenses();
            },
            error: (err) => {
              this.loading = false;
              this.alertService.error(
                'Erro ao atualizar despesa',
                err.error.message || 'Ocorreu um erro inesperado.'
              );
            },
          });
        }
      });
  }

  onSearch() {
    this.loadExpenses();
  }

  clearFilters() {
    this.searchTerm = '';
    this.filterStatus = '';
    this.filterCategory = '';
    this.filterFromDate = '';
    this.filterToDate = '';
    this.filterEstablishmentId = '';
    this.loadExpenses();
  }

  getStatusColor(status?: string): string {
    const colors: { [key: string]: string } = {
      pending: 'bg-amber-100 text-amber-800 border border-amber-200',
      paid: 'bg-green-100 text-green-800 border border-green-200',
      overdue: 'bg-red-100 text-red-800 border border-red-200',
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
      overdue: 'Vencida',
    };
    return labels[status || 'pending'] || 'Pendente';
  }

  getCategoryLabel(category?: string): string {
    const categoryObj = this.categories.find((c) => c.value === category);
    return categoryObj?.label || category || 'N/A';
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

  isOverdue(expense: Expense): boolean {
    if (expense.status === 'paid') {
      return false;
    }
    if (!expense.due_date) {
      return false;
    }
    const dueDate = new Date(expense.due_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return dueDate < today;
  }
}

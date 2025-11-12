import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { SchedulingService } from '../../services/scheduling.service';
import { Scheduling } from '../../models/service.model';
import { AuthService } from '../../services/auth.service';
import {
  BreadcrumbsComponent,
  BreadcrumbItem,
} from '../breadcrumbs/breadcrumbs.component';

interface CalendarDay {
  date: Date;
  day: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  schedulings: Scheduling[];
}

@Component({
  selector: 'app-calendar',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    DatePipe,
    BreadcrumbsComponent,
  ],
  templateUrl: './calendar.component.html',
  styleUrl: './calendar.component.scss',
})
export class CalendarComponent implements OnInit {
  currentDate: Date = new Date();
  calendarDays: CalendarDay[] = [];
  schedulings: Scheduling[] = [];
  loading = false;
  viewMode: 'month' | 'week' = 'month';
  selectedDate: Date | null = null;
  breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', route: '/dashboard' },
    { label: 'Calendário' },
  ];
  selectedScheduling: Scheduling | null = null;
  user: any = null;

  weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  months = [
    'Janeiro',
    'Fevereiro',
    'Março',
    'Abril',
    'Maio',
    'Junho',
    'Julho',
    'Agosto',
    'Setembro',
    'Outubro',
    'Novembro',
    'Dezembro',
  ];

  constructor(
    private schedulingService: SchedulingService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    this.user = this.authService.getCurrentUser();
    this.loadSchedulings();
  }

  loadSchedulings() {
    this.loading = true;
    this.schedulingService.getAll().subscribe({
      next: (data) => {
        this.schedulings = Array.isArray(data) ? data : data.data || [];
        this.generateCalendar();
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
      },
    });
  }

  generateCalendar() {
    const year = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();

    // Primeiro dia do mês
    const firstDay = new Date(year, month, 1);
    // Último dia do mês
    const lastDay = new Date(year, month + 1, 0);

    // Dia da semana do primeiro dia (0 = domingo, 6 = sábado)
    const startDayOfWeek = firstDay.getDay();

    // Total de dias no mês
    const daysInMonth = lastDay.getDate();

    // Dias do mês anterior para preencher o início
    const prevMonthLastDay = new Date(year, month, 0).getDate();

    this.calendarDays = [];

    // Dias do mês anterior
    for (let i = startDayOfWeek - 1; i >= 0; i--) {
      const date = new Date(year, month - 1, prevMonthLastDay - i);
      this.calendarDays.push({
        date,
        day: date.getDate(),
        isCurrentMonth: false,
        isToday: this.isToday(date),
        schedulings: this.getSchedulingsForDate(date),
      });
    }

    // Dias do mês atual
    const today = new Date();
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(year, month, day);
      this.calendarDays.push({
        date,
        day,
        isCurrentMonth: true,
        isToday: this.isToday(date),
        schedulings: this.getSchedulingsForDate(date),
      });
    }

    // Dias do próximo mês para completar a última semana
    const remainingDays = 42 - this.calendarDays.length; // 6 semanas * 7 dias
    for (let day = 1; day <= remainingDays; day++) {
      const date = new Date(year, month + 1, day);
      this.calendarDays.push({
        date,
        day: date.getDate(),
        isCurrentMonth: false,
        isToday: this.isToday(date),
        schedulings: this.getSchedulingsForDate(date),
      });
    }
  }

  getSchedulingsForDate(date: Date): Scheduling[] {
    const dateStr = this.formatDate(date);
    return this.schedulings.filter((scheduling) => {
      const schedulingDate = new Date(scheduling.scheduled_date);
      const schedulingDateStr = this.formatDate(schedulingDate);
      return schedulingDateStr === dateStr;
    });
  }

  formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  isToday(date: Date): boolean {
    const today = new Date();
    return (
      date.getDate() === today.getDate() &&
      date.getMonth() === today.getMonth() &&
      date.getFullYear() === today.getFullYear()
    );
  }

  previousMonth() {
    this.currentDate = new Date(
      this.currentDate.getFullYear(),
      this.currentDate.getMonth() - 1,
      1
    );
    this.generateCalendar();
  }

  nextMonth() {
    this.currentDate = new Date(
      this.currentDate.getFullYear(),
      this.currentDate.getMonth() + 1,
      1
    );
    this.generateCalendar();
  }

  goToToday() {
    this.currentDate = new Date();
    this.generateCalendar();
  }

  selectDate(day: CalendarDay) {
    this.selectedDate = day.date;
    this.selectedScheduling = null;
  }

  selectScheduling(scheduling: Scheduling, event?: Event) {
    if (event) {
      event.stopPropagation();
    }
    this.selectedScheduling = scheduling;
    this.selectedDate = new Date(scheduling.scheduled_date);
  }

  closeSchedulingDetails() {
    this.selectedScheduling = null;
  }

  getCurrentMonthYear(): string {
    return `${
      this.months[this.currentDate.getMonth()]
    } ${this.currentDate.getFullYear()}`;
  }

  getStatusColor(status: string): string {
    const colors: { [key: string]: string } = {
      pending: 'bg-yellow-100 text-yellow-800 border-yellow-300',
      confirmed: 'bg-blue-100 text-blue-800 border-blue-300',
      completed: 'bg-green-100 text-green-800 border-green-300',
      cancelled: 'bg-red-100 text-red-800 border-red-300',
    };
    return colors[status] || 'bg-gray-100 text-gray-800 border-gray-300';
  }

  getStatusLabel(status: string): string {
    const labels: { [key: string]: string } = {
      pending: 'Pendente',
      confirmed: 'Confirmado',
      completed: 'Concluído',
      cancelled: 'Cancelado',
    };
    return labels[status] || status;
  }

  formatTime(time: string): string {
    if (!time) return '';
    return time.substring(0, 5); // HH:mm
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value);
  }
}

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
  viewMode: 'month' | 'week' | 'day' = 'day';
  selectedDate: Date | null = null;
  timeSlots: string[] = [];
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
    this.generateTimeSlots();
    this.selectedDate = new Date();
    this.loadSchedulings();
  }

  generateTimeSlots() {
    // Gera horários de 06:00 até 23:00 em intervalos de 30 minutos
    this.timeSlots = [];
    for (let hour = 6; hour < 24; hour++) {
      for (let minute = 0; minute < 60; minute += 30) {
        const time = `${String(hour).padStart(2, '0')}:${String(
          minute
        ).padStart(2, '0')}`;
        this.timeSlots.push(time);
      }
    }
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
      // Se a data já vier no formato YYYY-MM-DD, usar diretamente
      // Caso contrário, converter
      let schedulingDateStr = scheduling.scheduled_date;
      if (schedulingDateStr && !/^\d{4}-\d{2}-\d{2}$/.test(schedulingDateStr)) {
        const schedulingDate = new Date(scheduling.scheduled_date);
        schedulingDateStr = this.formatDate(schedulingDate);
      }
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
    // Converter a data string YYYY-MM-DD para Date sem problemas de timezone
    const dateStr = scheduling.scheduled_date;
    if (dateStr && /^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
      const [year, month, day] = dateStr.split('-').map(Number);
      this.selectedDate = new Date(year, month - 1, day);
    } else {
      this.selectedDate = new Date(scheduling.scheduled_date);
    }
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

  getStatusDotColor(status: string): string {
    const colors: { [key: string]: string } = {
      pending: 'bg-yellow-500',
      confirmed: 'bg-blue-500',
      completed: 'bg-green-500',
      cancelled: 'bg-red-500',
    };
    return colors[status] || 'bg-gray-500';
  }

  toggleViewMode() {
    if (this.viewMode === 'month') {
      this.viewMode = 'day';
      if (!this.selectedDate) {
        this.selectedDate = new Date();
      }
    } else if (this.viewMode === 'day') {
      this.viewMode = 'month';
    }
    this.cdr.detectChanges();
  }

  getSchedulingsForTimeSlot(timeSlot: string, date: Date): Scheduling[] {
    if (!date) return [];
    const dateStr = this.formatDate(date);
    return this.schedulings.filter((scheduling) => {
      let schedulingDateStr = scheduling.scheduled_date;
      if (schedulingDateStr && !/^\d{4}-\d{2}-\d{2}$/.test(schedulingDateStr)) {
        const schedulingDate = new Date(scheduling.scheduled_date);
        schedulingDateStr = this.formatDate(schedulingDate);
      }

      if (schedulingDateStr !== dateStr) return false;

      // Compara o horário (formato HH:mm)
      const schedulingTime = scheduling.scheduled_time?.substring(0, 5) || '';
      if (!schedulingTime) return false;

      // Arredonda o horário do agendamento para o timeSlot mais próximo (para baixo)
      const roundedTime = this.roundTimeToSlot(schedulingTime);
      return roundedTime === timeSlot;
    });
  }

  roundTimeToSlot(time: string): string {
    // Converte "HH:mm" para minutos desde meia-noite
    const [hours, minutes] = time.split(':').map(Number);
    const totalMinutes = hours * 60 + minutes;

    // Arredonda para baixo para o intervalo de 30 minutos mais próximo
    const roundedMinutes = Math.floor(totalMinutes / 30) * 30;

    // Converte de volta para "HH:mm"
    const roundedHours = Math.floor(roundedMinutes / 60);
    const roundedMins = roundedMinutes % 60;

    return `${String(roundedHours).padStart(2, '0')}:${String(
      roundedMins
    ).padStart(2, '0')}`;
  }

  previousDay() {
    if (this.selectedDate) {
      this.selectedDate = new Date(
        this.selectedDate.getFullYear(),
        this.selectedDate.getMonth(),
        this.selectedDate.getDate() - 1
      );
    }
  }

  nextDay() {
    if (this.selectedDate) {
      this.selectedDate = new Date(
        this.selectedDate.getFullYear(),
        this.selectedDate.getMonth(),
        this.selectedDate.getDate() + 1
      );
    }
  }

  goToTodayDay() {
    this.selectedDate = new Date();
  }

  openWhatsApp(scheduling: Scheduling) {
    if (!scheduling.client?.phone) {
      return;
    }

    // Remove caracteres não numéricos
    let phone = scheduling.client.phone.replace(/\D/g, '');

    // Se não começar com 55 (código do Brasil), adiciona
    if (!phone.startsWith('55')) {
      phone = '55' + phone;
    }

    // Mensagem padrão - parsear data manualmente para evitar problemas de timezone
    let formattedDate = '';
    if (scheduling.scheduled_date) {
      // Se a data já está no formato YYYY-MM-DD, parsear manualmente
      if (/^\d{4}-\d{2}-\d{2}$/.test(scheduling.scheduled_date)) {
        const [year, month, day] = scheduling.scheduled_date
          .split('-')
          .map(Number);
        const date = new Date(year, month - 1, day); // month - 1 porque Date usa 0-11
        formattedDate = date.toLocaleDateString('pt-BR', {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        });
      } else {
        // Se não estiver no formato esperado, usar o método anterior
        const date = new Date(scheduling.scheduled_date);
        formattedDate = date.toLocaleDateString('pt-BR', {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        });
      }
    }
    const message = encodeURIComponent(
      `Olá ${
        scheduling.client_name
      }! Lembramos que você tem um agendamento conosco no dia ${formattedDate} às ${this.formatTime(
        scheduling.scheduled_time
      )}.${
        scheduling.service?.name ? ` Serviço: ${scheduling.service.name}` : ''
      }`
    );

    // Abre WhatsApp Web ou App
    const url = `https://wa.me/${phone}?text=${message}`;
    window.open(url, '_blank');
  }

  updateStatus(status: 'pending' | 'confirmed' | 'completed' | 'cancelled') {
    if (!this.selectedScheduling) {
      return;
    }

    this.schedulingService
      .update(this.selectedScheduling.id, { status })
      .subscribe({
        next: (updated) => {
          // Atualiza o agendamento na lista
          const index = this.schedulings.findIndex((s) => s.id === updated.id);
          if (index !== -1) {
            this.schedulings[index] = updated;
          }
          this.selectedScheduling = updated;
          this.generateCalendar();
          this.cdr.detectChanges();
        },
        error: (err) => {
          console.error('Erro ao atualizar status:', err);
          // Aqui você pode adicionar uma notificação de erro
        },
      });
  }
}

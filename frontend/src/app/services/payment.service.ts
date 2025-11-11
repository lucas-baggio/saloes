import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { PaymentData, PaymentResponse } from '../models/payment.model';

@Injectable({
  providedIn: 'root',
})
export class PaymentService {
  constructor(private api: ApiService) {}

  processPayment(data: PaymentData): Observable<PaymentResponse> {
    return this.api.post<PaymentResponse>('payments/process', data);
  }

  getPaymentStatus(paymentId: string): Observable<PaymentResponse> {
    return this.api.get<PaymentResponse>(`payments/${paymentId}/status`);
  }
}

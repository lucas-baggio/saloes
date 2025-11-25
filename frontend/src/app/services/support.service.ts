import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface SupportMessage {
  subject: string;
  message: string;
  name?: string;
  email?: string;
}

@Injectable({
  providedIn: 'root',
})
export class SupportService {
  constructor(private api: ApiService) {}

  sendMessage(data: SupportMessage): Observable<any> {
    return this.api.post('support/send', data);
  }
}

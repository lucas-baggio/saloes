import { Injectable } from '@angular/core';
import { Observable, tap, finalize } from 'rxjs';
import { ApiService } from './api.service';
import {
  AuthResponse,
  LoginRequest,
  RegisterRequest,
  User,
} from '../models/user.model';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  constructor(private api: ApiService) {}

  login(credentials: LoginRequest): Observable<AuthResponse> {
    return this.api.post<AuthResponse>('auth/login', credentials).pipe(
      tap((response) => {
        localStorage.setItem('token', response.token);
        localStorage.setItem('user', JSON.stringify(response.user));
      })
    );
  }

  register(data: RegisterRequest): Observable<AuthResponse> {
    return this.api.post<AuthResponse>('auth/register', data).pipe(
      tap((response) => {
        localStorage.setItem('token', response.token);
        localStorage.setItem('user', JSON.stringify(response.user));
      })
    );
  }

  logout(): Observable<any> {
    return this.api.post('auth/logout', {}).pipe(
      finalize(() => {
        // Always clear localStorage, even on error
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      })
    );
  }

  me(): Observable<User> {
    return this.api.get<User>('auth/me');
  }

  isAuthenticated(): boolean {
    return !!localStorage.getItem('token');
  }

  getCurrentUser(): User | null {
    const userStr = localStorage.getItem('user');
    if (!userStr) {
      return null;
    }
    try {
      return JSON.parse(userStr);
    } catch (e) {
      return null;
    }
  }

  getToken(): string | null {
    return localStorage.getItem('token');
  }
}

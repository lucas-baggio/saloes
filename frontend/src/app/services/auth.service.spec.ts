import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { AuthService } from './auth.service';
import { ApiService } from './api.service';
import { environment } from '../../environments/environment';
import {
  User,
  AuthResponse,
  LoginRequest,
  RegisterRequest,
} from '../models/user.model';

describe('AuthService', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;
  let apiService: ApiService;

  const mockUser: User = {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    role: 'owner',
    email_verified_at: null,
  };

  const mockAuthResponse: AuthResponse = {
    token: 'test-token',
    token_type: 'Bearer',
    expires_at: '2024-12-31T23:59:59Z',
    user: mockUser,
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [AuthService, ApiService],
    });
    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
    apiService = TestBed.inject(ApiService);
    localStorage.clear();
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('login', () => {
    it('should login user and store token and user in localStorage', () => {
      const credentials: LoginRequest = {
        email: 'test@example.com',
        password: 'password123',
      };

      service.login(credentials).subscribe((response) => {
        expect(response).toEqual(mockAuthResponse);
        expect(localStorage.getItem('token')).toBe('test-token');
        expect(localStorage.getItem('user')).toBe(JSON.stringify(mockUser));
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/login`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(credentials);
      req.flush(mockAuthResponse);
    });

    it('should handle login errors', () => {
      const credentials: LoginRequest = {
        email: 'test@example.com',
        password: 'wrongpassword',
      };

      service.login(credentials).subscribe({
        next: () => fail('should have failed'),
        error: (error) => {
          expect(error.status).toBe(401);
          expect(localStorage.getItem('token')).toBeNull();
        },
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/login`);
      req.flush(
        { message: 'Invalid credentials' },
        { status: 401, statusText: 'Unauthorized' }
      );
    });
  });

  describe('register', () => {
    it('should register user and store token and user in localStorage', () => {
      const registerData: RegisterRequest = {
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123',
        role: 'owner',
      };

      service.register(registerData).subscribe((response) => {
        expect(response).toEqual(mockAuthResponse);
        expect(localStorage.getItem('token')).toBe('test-token');
        expect(localStorage.getItem('user')).toBe(JSON.stringify(mockUser));
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/register`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(registerData);
      req.flush(mockAuthResponse);
    });

    it('should handle registration errors', () => {
      const registerData: RegisterRequest = {
        name: 'Test User',
        email: 'existing@example.com',
        password: 'password123',
        role: 'owner',
      };

      service.register(registerData).subscribe({
        next: () => fail('should have failed'),
        error: (error) => {
          expect(error.status).toBe(422);
        },
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/register`);
      req.flush(
        { message: 'Email already taken' },
        { status: 422, statusText: 'Unprocessable Entity' }
      );
    });
  });

  describe('logout', () => {
    it('should logout user and remove token and user from localStorage', () => {
      localStorage.setItem('token', 'test-token');
      localStorage.setItem('user', JSON.stringify(mockUser));

      service.logout().subscribe();

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/logout`);
      expect(req.request.method).toBe('POST');
      req.flush({});
      
      // finalize executes after next/error, so check after flush
      expect(localStorage.getItem('token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();
    });

    it('should clear localStorage even if API call fails', () => {
      localStorage.setItem('token', 'test-token');
      localStorage.setItem('user', JSON.stringify(mockUser));

      service.logout().subscribe({
        next: () => {},
        error: () => {},
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/logout`);
      req.flush(
        { error: 'Error' },
        { status: 500, statusText: 'Server Error' }
      );
      
      // finalize executes after next/error, so check after flush
      expect(localStorage.getItem('token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();
    });
  });

  describe('me', () => {
    it('should get current user', () => {
      service.me().subscribe((user) => {
        expect(user).toEqual(mockUser);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/me`);
      expect(req.request.method).toBe('GET');
      req.flush(mockUser);
    });
  });

  describe('isAuthenticated', () => {
    it('should return true when token exists', () => {
      localStorage.setItem('token', 'test-token');
      expect(service.isAuthenticated()).toBe(true);
    });

    it('should return false when token does not exist', () => {
      expect(service.isAuthenticated()).toBe(false);
    });
  });

  describe('getCurrentUser', () => {
    it('should return user when user exists in localStorage', () => {
      localStorage.setItem('user', JSON.stringify(mockUser));
      const user = service.getCurrentUser();
      expect(user).toEqual(mockUser);
    });

    it('should return null when user does not exist in localStorage', () => {
      expect(service.getCurrentUser()).toBeNull();
    });

    it('should return null when user is invalid JSON', () => {
      localStorage.setItem('user', 'invalid-json');
      // Should handle JSON parse error gracefully
      const user = service.getCurrentUser();
      expect(user).toBeNull();
    });
  });

  describe('getToken', () => {
    it('should return token when token exists', () => {
      localStorage.setItem('token', 'test-token');
      expect(service.getToken()).toBe('test-token');
    });

    it('should return null when token does not exist', () => {
      expect(service.getToken()).toBeNull();
    });
  });
});

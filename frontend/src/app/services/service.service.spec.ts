import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { ServiceService } from './service.service';
import { ApiService } from './api.service';
import { environment } from '../../environments/environment';
import { Service } from '../models/service.model';

describe('ServiceService', () => {
  let service: ServiceService;
  let httpMock: HttpTestingController;

  const mockService: Service = {
    id: 1,
    name: 'Test Service',
    description: 'Test Description',
    price: 100,
    establishment_id: 1,
    user_id: 1,
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [ServiceService, ApiService],
    });
    service = TestBed.inject(ServiceService);
    httpMock = TestBed.inject(HttpTestingController);
    localStorage.clear();
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('getAll', () => {
    it('should fetch all services without params', () => {
      const mockResponse = {
        data: [mockService],
        total: 1,
      };

      service.getAll().subscribe((response) => {
        expect(response).toEqual(mockResponse);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/services`);
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
    });

    it('should fetch services with establishment_id param', () => {
      service.getAll({ establishment_id: 1 }).subscribe();

      const req = httpMock.expectOne(
        `${environment.apiUrl}/services?establishment_id=1`
      );
      expect(req.request.method).toBe('GET');
      req.flush({ data: [mockService] });
    });

    it('should fetch services with user_id param', () => {
      service.getAll({ user_id: 1 }).subscribe();

      const req = httpMock.expectOne(
        `${environment.apiUrl}/services?user_id=1`
      );
      expect(req.request.method).toBe('GET');
      req.flush({ data: [mockService] });
    });

    it('should fetch services with multiple params', () => {
      const mockResponse = {
        data: [mockService],
        total: 1,
      };

      service
        .getAll({ establishment_id: 1, user_id: 1, per_page: 10 })
        .subscribe((response) => {
          expect(response).toEqual(mockResponse);
        });

      // URLSearchParams maintains insertion order, so the URL will be predictable
      const req = httpMock.expectOne(
        (request) =>
          request.method === 'GET' &&
          request.url.startsWith(`${environment.apiUrl}/services`) &&
          request.url.includes('establishment_id=1') &&
          request.url.includes('user_id=1') &&
          request.url.includes('per_page=10')
      );
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
    });
  });

  describe('getById', () => {
    it('should fetch service by id', () => {
      service.getById(1).subscribe((service) => {
        expect(service).toEqual(mockService);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/services/1`);
      expect(req.request.method).toBe('GET');
      req.flush(mockService);
    });
  });

  describe('create', () => {
    it('should create service', () => {
      const createData = {
        name: 'New Service',
        description: 'New Description',
        establishment_id: 1,
      };

      service.create(createData).subscribe((service) => {
        expect(service).toEqual(mockService);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/services`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(createData);
      req.flush(mockService);
    });
  });

  describe('update', () => {
    it('should update service', () => {
      const updateData = {
        name: 'Updated Service',
      };

      service.update(1, updateData).subscribe((service) => {
        expect(service.name).toBe('Updated Service');
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/services/1`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual(updateData);
      req.flush({ ...mockService, ...updateData });
    });
  });

  describe('delete', () => {
    it('should delete service', () => {
      service.delete(1).subscribe((response) => {
        expect(response).toBeDefined();
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/services/1`);
      expect(req.request.method).toBe('DELETE');
      req.flush({});
    });
  });
});

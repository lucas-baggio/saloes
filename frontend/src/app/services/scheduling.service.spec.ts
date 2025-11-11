import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { SchedulingService } from './scheduling.service';
import { ApiService } from './api.service';
import { environment } from '../../environments/environment';
import { Scheduling } from '../models/service.model';

describe('SchedulingService', () => {
  let service: SchedulingService;
  let httpMock: HttpTestingController;

  const mockScheduling: Scheduling = {
    id: 1,
    scheduled_date: '2024-01-15',
    scheduled_time: '10:00',
    service_id: 1,
    establishment_id: 1,
    client_name: 'Test Client',
    status: 'pending',
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [SchedulingService, ApiService],
    });
    service = TestBed.inject(SchedulingService);
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
    it('should fetch all schedulings', () => {
      const mockResponse = {
        data: [mockScheduling],
        total: 1,
      };

      service.getAll().subscribe((response) => {
        expect(response).toEqual(mockResponse);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/schedulings`);
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
    });
  });

  describe('getById', () => {
    it('should fetch scheduling by id', () => {
      service.getById(1).subscribe((scheduling) => {
        expect(scheduling).toEqual(mockScheduling);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/schedulings/1`);
      expect(req.request.method).toBe('GET');
      req.flush(mockScheduling);
    });
  });

  describe('create', () => {
    it('should create scheduling', () => {
      const createData = {
        scheduled_date: '2024-01-20',
        scheduled_time: '14:00',
        service_id: 1,
        client_name: 'New Client',
      };

      service.create(createData).subscribe((scheduling) => {
        expect(scheduling).toEqual(mockScheduling);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/schedulings`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(createData);
      req.flush(mockScheduling);
    });
  });

  describe('update', () => {
    it('should update scheduling', () => {
      const updateData: Partial<Scheduling> = {
        status: 'confirmed' as const,
      };

      service.update(1, updateData).subscribe((scheduling) => {
        expect(scheduling.status).toBe('confirmed');
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/schedulings/1`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual(updateData);
      req.flush({ ...mockScheduling, ...updateData });
    });
  });

  describe('delete', () => {
    it('should delete scheduling', () => {
      service.delete(1).subscribe((response) => {
        expect(response).toBeDefined();
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/schedulings/1`);
      expect(req.request.method).toBe('DELETE');
      req.flush({});
    });
  });
});

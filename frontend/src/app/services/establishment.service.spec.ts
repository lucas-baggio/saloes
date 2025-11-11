import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { EstablishmentService } from './establishment.service';
import { ApiService } from './api.service';
import { environment } from '../../environments/environment';
import { Establishment } from '../models/establishment.model';

describe('EstablishmentService', () => {
  let service: EstablishmentService;
  let httpMock: HttpTestingController;

  const mockEstablishment: Establishment = {
    id: 1,
    name: 'Test Establishment',
    description: 'Test Description',
    owner_id: 1,
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [EstablishmentService, ApiService],
    });
    service = TestBed.inject(EstablishmentService);
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
    it('should fetch all establishments', () => {
      const mockResponse = {
        data: [mockEstablishment],
        total: 1,
      };

      service.getAll().subscribe((response) => {
        expect(response).toEqual(mockResponse);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/establishments`);
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
    });
  });

  describe('getById', () => {
    it('should fetch establishment by id', () => {
      service.getById(1).subscribe((establishment) => {
        expect(establishment).toEqual(mockEstablishment);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/establishments/1`);
      expect(req.request.method).toBe('GET');
      req.flush(mockEstablishment);
    });
  });

  describe('create', () => {
    it('should create establishment', () => {
      const createData = {
        name: 'New Establishment',
        description: 'New Description',
      };

      service.create(createData).subscribe((establishment) => {
        expect(establishment).toEqual(mockEstablishment);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/establishments`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(createData);
      req.flush(mockEstablishment);
    });
  });

  describe('update', () => {
    it('should update establishment', () => {
      const updateData = {
        name: 'Updated Establishment',
      };

      service.update(1, updateData).subscribe((establishment) => {
        expect(establishment.name).toBe('Updated Establishment');
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/establishments/1`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual(updateData);
      req.flush({ ...mockEstablishment, ...updateData });
    });
  });

  describe('delete', () => {
    it('should delete establishment', () => {
      service.delete(1).subscribe((response) => {
        expect(response).toBeDefined();
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/establishments/1`);
      expect(req.request.method).toBe('DELETE');
      req.flush({});
    });
  });
});

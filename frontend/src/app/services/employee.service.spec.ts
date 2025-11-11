import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { EmployeeService, Employee } from './employee.service';
import { ApiService } from './api.service';
import { environment } from '../../environments/environment';

describe('EmployeeService', () => {
  let service: EmployeeService;
  let httpMock: HttpTestingController;

  const mockEmployee: Employee = {
    id: 1,
    name: 'Test Employee',
    email: 'employee@example.com',
    role: 'employee',
    created_at: '2024-01-01',
    services_count: 5,
    revenue: 1000,
    schedulings_count: 10,
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [EmployeeService, ApiService],
    });
    service = TestBed.inject(EmployeeService);
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
    it('should fetch all employees with array response', () => {
      const mockResponse = [mockEmployee];

      service.getAll().subscribe((employees) => {
        expect(employees).toEqual(mockResponse);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/employees`);
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
    });

    it('should fetch all employees with object response', () => {
      const mockResponse = {
        data: [mockEmployee],
        total: 1,
      };

      service.getAll().subscribe((employees) => {
        expect(employees).toEqual([mockEmployee]);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/employees`);
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
    });

    it('should handle empty data array', () => {
      const mockResponse = {
        data: [],
        total: 0,
      };

      service.getAll().subscribe((employees) => {
        expect(employees).toEqual([]);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/employees`);
      req.flush(mockResponse);
    });
  });

  describe('getById', () => {
    it('should fetch employee by id', () => {
      service.getById(1).subscribe((employee) => {
        expect(employee).toEqual(mockEmployee);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/employees/1`);
      expect(req.request.method).toBe('GET');
      req.flush(mockEmployee);
    });
  });

  describe('create', () => {
    it('should create employee', () => {
      const createData = {
        name: 'New Employee',
        email: 'new@example.com',
        password: 'password123',
        establishment_id: 1,
      };

      service.create(createData).subscribe((employee) => {
        expect(employee).toEqual(mockEmployee);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/employees`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(createData);
      req.flush(mockEmployee);
    });
  });

  describe('update', () => {
    it('should update employee', () => {
      const updateData = {
        name: 'Updated Employee',
      };

      service.update(1, updateData).subscribe((employee) => {
        expect(employee.name).toBe('Updated Employee');
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/employees/1`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual(updateData);
      req.flush({ ...mockEmployee, ...updateData });
    });
  });

  describe('delete', () => {
    it('should delete employee', () => {
      service.delete(1).subscribe((response) => {
        expect(response).toBeDefined();
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/employees/1`);
      expect(req.request.method).toBe('DELETE');
      req.flush({});
    });
  });
});

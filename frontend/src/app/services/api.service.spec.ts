import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { ApiService } from './api.service';
import { environment } from '../../environments/environment';

describe('ApiService', () => {
  let service: ApiService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [ApiService],
    });
    service = TestBed.inject(ApiService);
    httpMock = TestBed.inject(HttpTestingController);
    localStorage.clear();
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('GET requests', () => {
    it('should make GET request without token', () => {
      const mockData = { id: 1, name: 'Test' };
      service.get('test').subscribe((data) => {
        expect(data).toEqual(mockData);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/test`);
      expect(req.request.method).toBe('GET');
      expect(req.request.headers.get('Authorization')).toBeNull();
      req.flush(mockData);
    });

    it('should make GET request with token', () => {
      localStorage.setItem('token', 'test-token');
      const mockData = { id: 1, name: 'Test' };

      service.get('test').subscribe((data) => {
        expect(data).toEqual(mockData);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/test`);
      expect(req.request.method).toBe('GET');
      expect(req.request.headers.get('Authorization')).toBe(
        'Bearer test-token'
      );
      req.flush(mockData);
    });

    it('should set correct headers', () => {
      service.get('test').subscribe();

      const req = httpMock.expectOne(`${environment.apiUrl}/test`);
      expect(req.request.headers.get('Content-Type')).toBe('application/json');
      expect(req.request.headers.get('Accept')).toBe('application/json');
      req.flush({});
    });
  });

  describe('POST requests', () => {
    it('should make POST request with data', () => {
      const mockData = { id: 1, name: 'Test' };
      const postData = { name: 'Test' };

      service.post('test', postData).subscribe((data) => {
        expect(data).toEqual(mockData);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/test`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(postData);
      req.flush(mockData);
    });

    it('should include token in POST request when available', () => {
      localStorage.setItem('token', 'test-token');
      service.post('test', {}).subscribe();

      const req = httpMock.expectOne(`${environment.apiUrl}/test`);
      expect(req.request.headers.get('Authorization')).toBe(
        'Bearer test-token'
      );
      req.flush({});
    });
  });

  describe('PUT requests', () => {
    it('should make PUT request with data', () => {
      const mockData = { id: 1, name: 'Updated' };
      const putData = { name: 'Updated' };

      service.put('test/1', putData).subscribe((data) => {
        expect(data).toEqual(mockData);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/test/1`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual(putData);
      req.flush(mockData);
    });
  });

  describe('DELETE requests', () => {
    it('should make DELETE request', () => {
      service.delete('test/1').subscribe((data) => {
        expect(data).toBeNull();
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/test/1`);
      expect(req.request.method).toBe('DELETE');
      req.flush(null);
    });

    it('should include token in DELETE request when available', () => {
      localStorage.setItem('token', 'test-token');
      service.delete('test/1').subscribe();

      const req = httpMock.expectOne(`${environment.apiUrl}/test/1`);
      expect(req.request.headers.get('Authorization')).toBe(
        'Bearer test-token'
      );
      req.flush(null);
    });
  });

  describe('Error handling', () => {
    it('should handle HTTP errors', () => {
      service.get('test').subscribe({
        next: () => fail('should have failed'),
        error: (error) => {
          expect(error.status).toBe(404);
        },
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/test`);
      req.flush(
        { error: 'Not found' },
        { status: 404, statusText: 'Not Found' }
      );
    });
  });
});

import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import {
  DashboardService,
  DashboardStats,
  RevenueChart,
  TopService,
} from './dashboard.service';
import { ApiService } from './api.service';
import { environment } from '../../environments/environment';

describe('DashboardService', () => {
  let service: DashboardService;
  let httpMock: HttpTestingController;

  const mockStats: DashboardStats = {
    period: 'month',
    start_date: '2024-01-01',
    end_date: '2024-01-31',
    establishments: 5,
    services: 10,
    schedulings: {
      total: 50,
      growth: 10,
      previous: 40,
    },
    revenue: {
      total: 5000,
      growth: 20,
      previous: 4000,
    },
    average_ticket: 100,
  };

  const mockRevenueChart: RevenueChart = {
    labels: ['2024-01-01', '2024-01-02'],
    revenue: [1000, 2000],
    count: [10, 20],
  };

  const mockTopServices: TopService[] = [
    {
      id: 1,
      name: 'Service 1',
      establishment: 'Establishment 1',
      schedulings: 20,
      revenue: 2000,
      average_price: 100,
    },
  ];

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [DashboardService, ApiService],
    });
    service = TestBed.inject(DashboardService);
    httpMock = TestBed.inject(HttpTestingController);
    localStorage.clear();
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('getStats', () => {
    it('should fetch stats with default period', () => {
      service.getStats().subscribe((stats) => {
        expect(stats).toEqual(mockStats);
      });

      const req = httpMock.expectOne(
        `${environment.apiUrl}/dashboard/stats?period=month`
      );
      expect(req.request.method).toBe('GET');
      req.flush(mockStats);
    });

    it('should fetch stats with custom period', () => {
      service.getStats('week').subscribe((stats) => {
        expect(stats).toEqual(mockStats);
      });

      const req = httpMock.expectOne(
        `${environment.apiUrl}/dashboard/stats?period=week`
      );
      expect(req.request.method).toBe('GET');
      req.flush(mockStats);
    });
  });

  describe('getRevenueChart', () => {
    it('should fetch revenue chart with default period', () => {
      service.getRevenueChart().subscribe((chart) => {
        expect(chart).toEqual(mockRevenueChart);
      });

      const req = httpMock.expectOne(
        `${environment.apiUrl}/dashboard/revenue-chart?period=month`
      );
      expect(req.request.method).toBe('GET');
      req.flush(mockRevenueChart);
    });

    it('should fetch revenue chart with custom period', () => {
      service.getRevenueChart('year').subscribe((chart) => {
        expect(chart).toEqual(mockRevenueChart);
      });

      const req = httpMock.expectOne(
        `${environment.apiUrl}/dashboard/revenue-chart?period=year`
      );
      expect(req.request.method).toBe('GET');
      req.flush(mockRevenueChart);
    });
  });

  describe('getTopServices', () => {
    it('should fetch top services with default params', () => {
      service.getTopServices().subscribe((services) => {
        expect(services).toEqual(mockTopServices);
      });

      const req = httpMock.expectOne(
        `${environment.apiUrl}/dashboard/top-services?period=month&limit=5`
      );
      expect(req.request.method).toBe('GET');
      req.flush(mockTopServices);
    });

    it('should fetch top services with custom params', () => {
      service.getTopServices('week', 10).subscribe((services) => {
        expect(services).toEqual(mockTopServices);
      });

      const req = httpMock.expectOne(
        `${environment.apiUrl}/dashboard/top-services?period=week&limit=10`
      );
      expect(req.request.method).toBe('GET');
      req.flush(mockTopServices);
    });
  });
});

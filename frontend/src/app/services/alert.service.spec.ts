import { TestBed } from '@angular/core/testing';
import { AlertService } from './alert.service';
import Swal from 'sweetalert2';

describe('AlertService', () => {
  let service: AlertService;
  let swalFireSpy: jasmine.Spy;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [AlertService],
    });
    service = TestBed.inject(AlertService);
    swalFireSpy = spyOn(Swal, 'fire').and.returnValue(
      Promise.resolve({
        isConfirmed: true,
        isDenied: false,
        isDismissed: false,
        value: true,
      } as any)
    );
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('success', () => {
    it('should show success alert with title and message', () => {
      service.success('Success Title', 'Success message');
      expect(swalFireSpy).toHaveBeenCalledWith({
        icon: 'success',
        title: 'Success Title',
        text: 'Success message',
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#ef4444',
      });
    });

    it('should show success alert with only title', () => {
      service.success('Success Title');
      expect(swalFireSpy).toHaveBeenCalledWith(
        jasmine.objectContaining({
          icon: 'success',
          title: 'Success Title',
        })
      );
    });
  });

  describe('error', () => {
    it('should show error alert with title and message', () => {
      service.error('Error Title', 'Error message');
      expect(swalFireSpy).toHaveBeenCalledWith({
        icon: 'error',
        title: 'Error Title',
        text: 'Error message',
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#ef4444',
      });
    });
  });

  describe('warning', () => {
    it('should show warning alert with title and message', () => {
      service.warning('Warning Title', 'Warning message');
      expect(swalFireSpy).toHaveBeenCalledWith({
        icon: 'warning',
        title: 'Warning Title',
        text: 'Warning message',
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#ef4444',
      });
    });
  });

  describe('info', () => {
    it('should show info alert with title and message', () => {
      service.info('Info Title', 'Info message');
      expect(swalFireSpy).toHaveBeenCalledWith({
        icon: 'info',
        title: 'Info Title',
        text: 'Info message',
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#ef4444',
      });
    });
  });

  describe('confirm', () => {
    it('should show confirmation dialog with default texts', () => {
      service.confirm('Confirm Title', 'Confirm message');
      expect(swalFireSpy).toHaveBeenCalledWith({
        icon: 'question',
        title: 'Confirm Title',
        text: 'Confirm message',
        showCancelButton: true,
        confirmButtonText: 'Sim',
        cancelButtonText: 'Não',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#ef4444',
      });
    });

    it('should show confirmation dialog with custom texts', () => {
      service.confirm('Confirm Title', 'Confirm message', 'Yes', 'No');
      expect(swalFireSpy).toHaveBeenCalledWith({
        icon: 'question',
        title: 'Confirm Title',
        text: 'Confirm message',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#ef4444',
      });
    });
  });

  describe('validationError', () => {
    it('should handle error with message', () => {
      const error = {
        error: {
          message: 'Custom error message',
        },
      };
      service.validationError(error);
      expect(swalFireSpy).toHaveBeenCalledWith(
        jasmine.objectContaining({
          icon: 'error',
          title: 'Erro',
          text: 'Custom error message',
        })
      );
    });

    it('should handle error with email validation error', () => {
      const error = {
        error: {
          errors: {
            email: ['Email já cadastrado'],
          },
        },
      };
      service.validationError(error);
      expect(swalFireSpy).toHaveBeenCalledWith(
        jasmine.objectContaining({
          icon: 'error',
          title: 'Email já cadastrado',
          text: 'Email já cadastrado',
        })
      );
    });

    it('should handle error with multiple validation errors', () => {
      const error = {
        error: {
          errors: {
            name: ['Name is required'],
            password: ['Password is required'],
          },
        },
      };
      service.validationError(error);
      expect(swalFireSpy).toHaveBeenCalledWith(
        jasmine.objectContaining({
          icon: 'error',
          title: 'Erro',
        })
      );
    });

    it('should handle error with message property', () => {
      const error = {
        message: 'Network error',
      };
      service.validationError(error);
      expect(swalFireSpy).toHaveBeenCalledWith(
        jasmine.objectContaining({
          icon: 'error',
          text: 'Network error',
        })
      );
    });

    it('should translate common Laravel messages', () => {
      const error = {
        error: {
          message: 'The email has already been taken.',
        },
      };
      service.validationError(error);
      expect(swalFireSpy).toHaveBeenCalledWith(
        jasmine.objectContaining({
          text: 'Já existe um cadastro com este email. Por favor, use outro email.',
        })
      );
    });

    it('should handle unknown error format', () => {
      const error = {};
      service.validationError(error);
      expect(swalFireSpy).toHaveBeenCalledWith(
        jasmine.objectContaining({
          icon: 'error',
          title: 'Erro',
          text: 'Ocorreu um erro ao processar sua solicitação.',
        })
      );
    });
  });
});

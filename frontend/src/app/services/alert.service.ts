import { Injectable } from '@angular/core';
import Swal from 'sweetalert2';

@Injectable({
  providedIn: 'root',
})
export class AlertService {
  // Configuração padrão em português
  private defaultConfig = {
    confirmButtonText: 'OK',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#3b82f6',
    cancelButtonColor: '#ef4444',
  };

  // Sucesso
  success(title: string, message?: string) {
    return Swal.fire({
      icon: 'success',
      title,
      text: message,
      ...this.defaultConfig,
    });
  }

  // Erro
  error(title: string, message?: string) {
    return Swal.fire({
      icon: 'error',
      title,
      text: message,
      ...this.defaultConfig,
    });
  }

  // Aviso
  warning(title: string, message?: string) {
    return Swal.fire({
      icon: 'warning',
      title,
      text: message,
      ...this.defaultConfig,
    });
  }

  // Informação
  info(title: string, message?: string) {
    return Swal.fire({
      icon: 'info',
      title,
      text: message,
      ...this.defaultConfig,
    });
  }

  // Confirmação
  confirm(
    title: string,
    message?: string,
    confirmText: string = 'Sim',
    cancelText: string = 'Não'
  ) {
    return Swal.fire({
      icon: 'question',
      title,
      text: message,
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      confirmButtonColor: '#3b82f6',
      cancelButtonColor: '#ef4444',
    });
  }

  // Erro de validação (para erros do backend)
  validationError(error: any) {
    let title = 'Erro';
    let message = 'Ocorreu um erro ao processar sua solicitação.';

    if (error?.error?.message) {
      message = error.error.message;
    } else if (error?.error?.errors) {
      // Erros de validação do Laravel
      const errors = error.error.errors;

      // Tratar erros específicos
      if (errors.email) {
        title = 'Email já cadastrado';
        message = Array.isArray(errors.email)
          ? errors.email[0]
          : 'Já existe um cadastro com este email. Por favor, use outro email.';
      } else {
        const firstError = Object.values(errors)[0];
        if (Array.isArray(firstError)) {
          message = firstError[0] as string;
        } else {
          message = firstError as string;
        }
      }
    } else if (error?.message) {
      message = error.message;
    }

    // Traduzir mensagens comuns do Laravel
    message = this.translateMessage(message);

    return this.error(title, message);
  }

  // Input (para substituir prompt)
  async prompt(
    title: string,
    message?: string,
    placeholder: string = 'Digite aqui...'
  ): Promise<string | null> {
    const { value } = await Swal.fire({
      title,
      text: message,
      input: 'text',
      inputPlaceholder: placeholder,
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#3b82f6',
      cancelButtonColor: '#ef4444',
      inputValidator: (value) => {
        if (!value || !value.trim()) {
          return 'Por favor, preencha o campo!';
        }
        return null;
      },
    });

    return value || null;
  }

  // Traduzir mensagens comuns do Laravel para português
  private translateMessage(message: string): string {
    const translations: { [key: string]: string } = {
      'The email has already been taken.':
        'Já existe um cadastro com este email. Por favor, use outro email.',
      'The email field is required.': 'O campo email é obrigatório.',
      'The name field is required.': 'O campo nome é obrigatório.',
      'The password field is required.': 'O campo senha é obrigatório.',
      'The password must be at least 8 characters.':
        'A senha deve ter pelo menos 8 caracteres.',
      'The establishment id field is required.':
        'O campo estabelecimento é obrigatório.',
      'The selected establishment id is invalid.':
        'O estabelecimento selecionado é inválido.',
      'Estabelecimento não pertence a você.':
        'Este estabelecimento não pertence a você.',
    };

    return translations[message] || message;
  }
}

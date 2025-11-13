import { Injectable } from '@angular/core';

export interface ContactInfo {
  name?: string;
  phone?: string;
  email?: string;
}

@Injectable({
  providedIn: 'root',
})
export class ContactsService {
  /**
   * Verifica se a API de contatos está disponível
   */
  isAvailable(): boolean {
    // Verifica se está em um dispositivo móvel
    const isMobile =
      /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent
      );

    // Verifica se a API de contatos está disponível
    const hasContactsAPI =
      'contacts' in navigator && 'select' in (navigator as any).contacts;

    return isMobile && hasContactsAPI;
  }

  /**
   * Solicita permissão e importa contatos do celular
   */
  async importContacts(): Promise<ContactInfo[]> {
    if (!this.isAvailable()) {
      throw new Error(
        'A importação de contatos não está disponível neste dispositivo/navegador.'
      );
    }

    try {
      // A API permite seleção múltipla - o usuário pode marcar vários contatos
      // Na interface nativa, geralmente há checkboxes ou modo de seleção múltipla
      const contacts = await (navigator as any).contacts.select(
        ['name', 'tel', 'email'],
        {
          multiple: true,
          // Alguns navegadores suportam propriedades adicionais
        }
      );

      return this.processContacts(contacts);
    } catch (error: any) {
      if (error.name === 'AbortError' || error.name === 'NotAllowedError') {
        throw new Error('Permissão negada ou operação cancelada.');
      }
      throw error;
    }
  }

  /**
   * Processa os contatos retornados pela API
   */
  private processContacts(contacts: any[]): ContactInfo[] {
    const processedContacts: ContactInfo[] = [];

    for (const contact of contacts) {
      const contactInfo: ContactInfo = {};

      // Nome
      if (contact.name && contact.name.length > 0) {
        contactInfo.name = contact.name[0];
      }

      // Telefone (pega o primeiro)
      if (contact.tel && contact.tel.length > 0) {
        // Remove caracteres não numéricos, exceto + no início
        let phone = contact.tel[0].replace(/[^\d+]/g, '');
        // Se não começar com +, adiciona código do Brasil se necessário
        if (!phone.startsWith('+')) {
          // Remove zeros à esquerda e formata
          phone = phone.replace(/^0+/, '');
          if (phone.length > 0 && !phone.startsWith('55')) {
            // Se não tiver código do país, assume Brasil
            if (phone.length === 10 || phone.length === 11) {
              phone = '55' + phone;
            }
          }
        }
        contactInfo.phone = this.formatPhone(phone);
      }

      // Email (pega o primeiro)
      if (contact.email && contact.email.length > 0) {
        contactInfo.email = contact.email[0].toLowerCase().trim();
      }

      // Só adiciona se tiver pelo menos nome ou telefone
      if (contactInfo.name || contactInfo.phone) {
        processedContacts.push(contactInfo);
      }
    }

    return processedContacts;
  }

  /**
   * Formata o telefone para o padrão brasileiro
   */
  private formatPhone(phone: string): string {
    // Remove tudo exceto números
    let cleaned = phone.replace(/\D/g, '');

    // Se começar com 55 (código do Brasil), remove
    if (cleaned.startsWith('55')) {
      cleaned = cleaned.substring(2);
    }

    // Formata: (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
    if (cleaned.length === 10) {
      return `(${cleaned.substring(0, 2)}) ${cleaned.substring(
        2,
        6
      )}-${cleaned.substring(6)}`;
    } else if (cleaned.length === 11) {
      return `(${cleaned.substring(0, 2)}) ${cleaned.substring(
        2,
        7
      )}-${cleaned.substring(7)}`;
    }

    // Se não conseguir formatar, retorna limpo
    return cleaned;
  }
}

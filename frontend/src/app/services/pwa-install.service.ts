import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class PwaInstallService {
  private deferredPrompt: any = null;
  private isInstalled = false;
  private canInstallSubject = new BehaviorSubject<boolean>(true); // Inicia como true
  public canInstall$: Observable<boolean> =
    this.canInstallSubject.asObservable();

  constructor() {
    // Verifica se o app já está instalado
    this.checkIfInstalled();

    // Detecta quando o navegador está pronto para mostrar o prompt de instalação
    window.addEventListener('beforeinstallprompt', (e: Event) => {
      // Previne o prompt automático
      e.preventDefault();
      // Salva o evento para usar depois
      this.deferredPrompt = e;
      this.updateCanInstall();
    });

    // Detecta quando o app é instalado
    window.addEventListener('appinstalled', () => {
      this.isInstalled = true;
      // Não limpa o deferredPrompt para permitir reinstalação
      this.updateCanInstall();
    });

    // Atualiza periodicamente (caso o evento não tenha sido capturado)
    this.updateCanInstall();
    setInterval(() => this.updateCanInstall(), 2000);
  }

  private updateCanInstall(): void {
    // Sempre mostra o botão se for PWA (tem manifest e service worker)
    // Verifica se é um ambiente que suporta PWA
    const isPwaAvailable = this.isPwaAvailable();
    this.canInstallSubject.next(isPwaAvailable);
  }

  /**
   * Verifica se o PWA está disponível (tem manifest e service worker)
   */
  private isPwaAvailable(): boolean {
    // Verifica se está em HTTPS ou localhost
    const isSecure =
      window.location.protocol === 'https:' ||
      window.location.hostname === 'localhost' ||
      window.location.hostname === '127.0.0.1';

    // Verifica se tem service worker registrado
    const hasServiceWorker = 'serviceWorker' in navigator;

    // Verifica se tem manifest
    const hasManifest = document.querySelector('link[rel="manifest"]') !== null;

    return isSecure && hasServiceWorker && hasManifest;
  }

  /**
   * Verifica se o app já está instalado
   */
  private checkIfInstalled(): void {
    // Verifica se está rodando em modo standalone (app instalado)
    if (
      window.matchMedia('(display-mode: standalone)').matches ||
      (window.navigator as any).standalone === true
    ) {
      this.isInstalled = true;
    }
  }

  /**
   * Verifica se o app pode ser instalado
   */
  canInstall(): boolean {
    return this.canInstallSubject.value;
  }

  /**
   * Verifica se o app já está instalado
   */
  isAppInstalled(): boolean {
    return this.isInstalled;
  }

  /**
   * Mostra o prompt de instalação ou instruções
   */
  async install(): Promise<boolean> {
    // Se tiver o evento beforeinstallprompt, usa ele
    if (this.deferredPrompt) {
      try {
        // Mostra o prompt de instalação
        this.deferredPrompt.prompt();

        // Espera pela resposta do usuário
        const { outcome } = await this.deferredPrompt.userChoice;

        if (outcome === 'accepted') {
          this.isInstalled = true;
          return true;
        }

        return false;
      } catch (error) {
        console.error('Erro ao instalar PWA:', error);
        return false;
      }
    }

    // Se não tiver o evento, retorna false (o componente mostrará instruções)
    return false;
  }
}

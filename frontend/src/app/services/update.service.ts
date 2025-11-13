import { Injectable, OnDestroy } from '@angular/core';
import { SwUpdate, VersionReadyEvent } from '@angular/service-worker';
import { filter } from 'rxjs/operators';
import { AlertService } from './alert.service';

@Injectable({
  providedIn: 'root',
})
export class UpdateService implements OnDestroy {
  constructor(private swUpdate: SwUpdate, private alertService: AlertService) {
    // Verificar se o service worker está habilitado
    if (this.swUpdate.isEnabled) {
      this.checkForUpdates();
      this.handleUpdates();
    }
  }

  /**
   * Verifica atualizações periodicamente
   */
  private checkForUpdates(): void {
    // Verificar atualizações a cada 6 horas
    setInterval(() => {
      this.swUpdate.checkForUpdate();
    }, 6 * 60 * 60 * 1000);

    // Verificar na inicialização
    this.swUpdate.checkForUpdate();
  }

  /**
   * Detecta quando uma nova versão está disponível
   */
  private handleUpdates(): void {
    this.swUpdate.versionUpdates
      .pipe(
        filter((evt): evt is VersionReadyEvent => evt.type === 'VERSION_READY')
      )
      .subscribe((evt) => {
        this.promptUserToUpdate(evt);
      });
  }

  /**
   * Notifica o usuário sobre a atualização disponível
   */
  private async promptUserToUpdate(event: VersionReadyEvent): Promise<void> {
    const result = await this.alertService.confirm(
      'Nova versão disponível!',
      'Uma nova versão do aplicativo está disponível. Deseja atualizar agora?',
      'Atualizar',
      'Depois'
    );

    if (result.isConfirmed) {
      // Atualizar imediatamente
      await this.swUpdate.activateUpdate();
      // Recarregar a página para aplicar a atualização
      window.location.reload();
    }
  }

  /**
   * Força verificação de atualização manualmente
   */
  async checkForUpdate(): Promise<void> {
    if (this.swUpdate.isEnabled) {
      const updateAvailable = await this.swUpdate.checkForUpdate();
      if (updateAvailable) {
        this.alertService.info(
          'Atualização disponível',
          'Uma nova versão será baixada em segundo plano. Você será notificado quando estiver pronta.'
        );
      } else {
        this.alertService.info(
          'Aplicativo atualizado',
          'Você já está usando a versão mais recente do aplicativo.'
        );
      }
    }
  }

  ngOnDestroy(): void {
    // Cleanup se necessário
  }
}

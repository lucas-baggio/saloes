import {
  Directive,
  ElementRef,
  HostListener,
  Input,
  OnInit,
  OnDestroy,
  Renderer2,
} from '@angular/core';

@Directive({
  selector: '[appTooltip]',
  standalone: true,
})
export class TooltipDirective implements OnInit, OnDestroy {
  @Input() appTooltip: string = '';
  @Input() tooltipPosition: 'top' | 'bottom' | 'left' | 'right' = 'top';

  private tooltipElement?: HTMLElement;
  private showTimeout?: any;

  constructor(private elementRef: ElementRef, private renderer: Renderer2) {}

  ngOnInit() {
    if (this.appTooltip) {
      this.renderer.addClass(this.elementRef.nativeElement, 'has-tooltip');
    }
  }

  @HostListener('mouseenter')
  onMouseEnter() {
    if (this.appTooltip) {
      this.showTimeout = setTimeout(() => {
        this.showTooltip();
      }, 300);
    }
  }

  @HostListener('mouseleave')
  onMouseLeave() {
    if (this.showTimeout) {
      clearTimeout(this.showTimeout);
    }
    this.hideTooltip();
  }

  private showTooltip() {
    if (!this.appTooltip || this.tooltipElement) return;

    // Criar elemento de tooltip
    this.tooltipElement = this.renderer.createElement('div');
    this.renderer.addClass(this.tooltipElement, 'custom-tooltip');
    this.renderer.addClass(
      this.tooltipElement,
      `tooltip-${this.tooltipPosition}`
    );
    this.renderer.setProperty(
      this.tooltipElement,
      'textContent',
      this.appTooltip
    );

    // Adicionar ao body
    this.renderer.appendChild(document.body, this.tooltipElement);

    // Calcular e aplicar posição
    setTimeout(() => {
      this.updateTooltipPosition();
    }, 0);
  }

  private updateTooltipPosition() {
    if (!this.tooltipElement) return;

    const hostRect = this.elementRef.nativeElement.getBoundingClientRect();
    const tooltipRect = this.tooltipElement.getBoundingClientRect();
    const scrollX = window.scrollX || window.pageXOffset;
    const scrollY = window.scrollY || window.pageYOffset;

    let top = 0;
    let left = 0;

    switch (this.tooltipPosition) {
      case 'top':
        top = hostRect.top + scrollY - tooltipRect.height - 8;
        left =
          hostRect.left + scrollX + hostRect.width / 2 - tooltipRect.width / 2;
        break;
      case 'bottom':
        top = hostRect.bottom + scrollY + 8;
        left =
          hostRect.left + scrollX + hostRect.width / 2 - tooltipRect.width / 2;
        break;
      case 'left':
        top =
          hostRect.top + scrollY + hostRect.height / 2 - tooltipRect.height / 2;
        left = hostRect.left + scrollX - tooltipRect.width - 8;
        break;
      case 'right':
        top =
          hostRect.top + scrollY + hostRect.height / 2 - tooltipRect.height / 2;
        left = hostRect.right + scrollX + 8;
        break;
    }

    // Ajustar para não sair da tela
    const padding = 10;
    if (left < padding) left = padding;
    if (left + tooltipRect.width > window.innerWidth - padding) {
      left = window.innerWidth - tooltipRect.width - padding;
    }
    if (top < padding) top = padding;
    if (top + tooltipRect.height > window.innerHeight - padding) {
      top = window.innerHeight - tooltipRect.height - padding;
    }

    this.renderer.setStyle(this.tooltipElement, 'position', 'fixed');
    this.renderer.setStyle(this.tooltipElement, 'top', `${top}px`);
    this.renderer.setStyle(this.tooltipElement, 'left', `${left}px`);
    this.renderer.setStyle(this.tooltipElement, 'opacity', '1');
  }

  private hideTooltip() {
    if (this.tooltipElement) {
      this.renderer.setStyle(this.tooltipElement, 'opacity', '0');
      setTimeout(() => {
        if (this.tooltipElement) {
          this.renderer.removeChild(document.body, this.tooltipElement);
          this.tooltipElement = undefined;
        }
      }, 200);
    }
  }

  ngOnDestroy() {
    if (this.showTimeout) {
      clearTimeout(this.showTimeout);
    }
    this.hideTooltip();
  }
}

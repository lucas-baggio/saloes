import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { PaymentService } from '../../services/payment.service';
import { PlanService } from '../../services/plan.service';
import { AlertService } from '../../services/alert.service';
import {
  PaymentMethod,
  PaymentData,
  PaymentResponse,
} from '../../models/payment.model';
import { Plan } from '../../models/plan.model';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-payment',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, DatePipe],
  templateUrl: './payment.component.html',
  styleUrl: './payment.component.scss',
})
export class PaymentComponent implements OnInit, OnDestroy {
  planId: number | null = null;
  plan: Plan | null = null;
  selectedMethod: PaymentMethod = 'pix';
  paymentForm: FormGroup;
  creditCardForm: FormGroup;
  loading = false;
  processing = false;
  paymentResponse: PaymentResponse | null = null;
  showSuccess = false;
  cardToken: string | null = null;
  private statusCheckInterval: any = null;
  private readonly STATUS_CHECK_INTERVAL = 5000; // 5 segundos
  private readonly MAX_STATUS_CHECKS = 120; // Máximo de 10 minutos (120 * 5s)
  private statusCheckCount = 0;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private fb: FormBuilder,
    private paymentService: PaymentService,
    private planService: PlanService,
    private alertService: AlertService
  ) {
    this.paymentForm = this.fb.group({
      payment_method: ['pix', Validators.required],
    });

    this.creditCardForm = this.fb.group({
      number: ['', [Validators.required]],
      name: ['', [Validators.required, Validators.minLength(3)]],
      cpf: ['', [Validators.required, Validators.pattern(/^\d{11}$/)]],
      expiry_month: [
        '',
        [Validators.required, Validators.pattern(/^(0[1-9]|1[0-2])$/)],
      ],
      expiry_year: [
        '',
        [
          Validators.required,
          Validators.pattern(/^\d{2}$/),
          Validators.minLength(2),
          Validators.maxLength(2),
        ],
      ],
      cvv: ['', [Validators.required, Validators.pattern(/^\d{3,4}$/)]],
      installments: [
        1,
        [Validators.required, Validators.min(1), Validators.max(12)],
      ],
    });
  }

  ngOnInit() {
    const planIdParam = this.route.snapshot.paramMap.get('planId');
    if (planIdParam) {
      this.planId = parseInt(planIdParam, 10);
      this.loadPlan();
    } else {
      this.alertService.error('Erro', 'Plano não especificado.');
      this.router.navigate(['/plans']);
    }

    this.paymentForm.get('payment_method')?.valueChanges.subscribe((method) => {
      this.selectedMethod = method;
      this.paymentResponse = null;
      // Para o polling se mudar o método de pagamento
      this.stopStatusPolling();
    });
  }

  loadPlan() {
    if (!this.planId) return;

    this.loading = true;
    this.planService.getById(this.planId).subscribe({
      next: (plan) => {
        this.plan = plan;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.alertService.error('Erro', 'Não foi possível carregar o plano.');
        this.router.navigate(['/plans']);
      },
    });
  }

  formatCardNumber(event: any) {
    let value = event.target.value.replace(/\D/g, ''); // Remove tudo que não é número

    // Limita a 19 dígitos (máximo para cartões)
    if (value.length > 19) {
      value = value.slice(0, 19);
    }

    // Adiciona espaços a cada 4 dígitos para formatação visual
    const formatted = value.replace(/(.{4})/g, '$1 ').trim();

    // Atualiza o valor do campo
    const control = this.creditCardForm.get('number');
    if (control) {
      control.setValue(formatted, { emitEvent: false });

      // Se o número está válido (13-19 dígitos), limpa erros imediatamente
      if (value.length >= 13 && value.length <= 19) {
        console.log('formatCardNumber - Número válido:', {
          value,
          length: value.length,
          errors: control.errors,
        });
        // FORÇA a remoção de TODOS os erros quando o número é válido
        control.setErrors(null);
        console.log('formatCardNumber - Erros após limpeza:', control.errors);
      }
    }
  }

  validateCardNumber() {
    const control = this.creditCardForm.get('number');
    if (!control) return;

    const cardNumber = control.value
      ? control.value.replace(/\s/g, '').replace(/\D/g, '')
      : '';

    control.markAsTouched();

    // Se não tem valor, deixa o validator required fazer o trabalho
    if (!cardNumber || cardNumber.length === 0) {
      return;
    }

    console.log('Validando número do cartão:', {
      cardNumber,
      length: cardNumber.length,
      isValid: cardNumber.length >= 13 && cardNumber.length <= 19,
      currentErrors: control.errors,
    });

    // Valida o tamanho quando o campo perde o foco
    if (cardNumber.length >= 13 && cardNumber.length <= 19) {
      // Número válido - FORÇA a remoção de TODOS os erros
      console.log('Número válido - limpando erros');
      control.setErrors(null);
      console.log('Erros após limpeza:', control.errors);
    } else {
      // Número inválido - adiciona erro de tamanho
      console.log('Número inválido - adicionando erro');
      const currentErrors = control.errors || {};
      // Remove required se tiver valor (mesmo que inválido)
      if (currentErrors['required']) {
        delete currentErrors['required'];
      }
      control.setErrors({
        ...currentErrors,
        invalidLength: true,
      });
      console.log('Erros após adicionar erro:', control.errors);
    }
  }

  formatExpiryMonth(event: any) {
    let value = event.target.value.replace(/\D/g, '');
    if (value.length > 2) value = value.slice(0, 2);
    if (value.length === 2 && parseInt(value) > 12) value = '12';
    this.creditCardForm.patchValue(
      { expiry_month: value },
      { emitEvent: false }
    );
  }

  formatExpiryYear(event: any) {
    let value = event.target.value.replace(/\D/g, '');
    if (value.length > 2) value = value.slice(0, 2);
    this.creditCardForm.patchValue(
      { expiry_year: value },
      { emitEvent: false }
    );
  }

  formatCVV(event: any) {
    let value = event.target.value.replace(/\D/g, '');
    if (value.length > 4) value = value.slice(0, 4);
    this.creditCardForm.patchValue({ cvv: value }, { emitEvent: false });
  }

  formatCPV(event: any) {
    let value = event.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.slice(0, 11);
    this.creditCardForm.patchValue({ cpf: value }, { emitEvent: false });
  }

  getCardNumberWithoutSpaces(): string {
    return this.creditCardForm.get('number')?.value?.replace(/\s/g, '') || '';
  }

  /**
   * Valida o número do cartão usando o algoritmo de Luhn
   */
  isValidCardNumber(cardNumber: string): boolean {
    // Remove espaços e caracteres não numéricos
    const cleaned = cardNumber.replace(/\D/g, '');

    // Deve ter entre 13 e 19 dígitos
    if (cleaned.length < 13 || cleaned.length > 19) {
      return false;
    }

    // Algoritmo de Luhn
    let sum = 0;
    let isEven = false;

    // Percorre os dígitos de trás para frente
    for (let i = cleaned.length - 1; i >= 0; i--) {
      let digit = parseInt(cleaned[i], 10);

      if (isEven) {
        digit *= 2;
        if (digit > 9) {
          digit -= 9;
        }
      }

      sum += digit;
      isEven = !isEven;
    }

    return sum % 10 === 0;
  }

  async createCardToken(): Promise<void> {
    // Valida o número do cartão antes de criar o token
    this.validateCardNumber();

    const control = this.creditCardForm.get('number');
    if (control && control.invalid) {
      if (control.errors?.['required']) {
        this.alertService.error('Erro', 'Número do cartão é obrigatório.');
      } else if (control.errors?.['invalidLength']) {
        this.alertService.error(
          'Erro',
          'Número do cartão inválido. Deve ter entre 13 e 19 dígitos.'
        );
      }
      return;
    }

    const cardData = this.creditCardForm.value;
    const cardNumber = this.getCardNumberWithoutSpaces();

    // Validação adicional de segurança
    if (!cardNumber || cardNumber.length < 13 || cardNumber.length > 19) {
      this.alertService.error(
        'Erro',
        'Número do cartão inválido. Deve ter entre 13 e 19 dígitos.'
      );
      return;
    }

    try {
      // Prepara os dados do cartão
      const tokenData = {
        card_number: cardNumber,
        cardholder: {
          name: cardData.name,
          identification: {
            type: 'CPF',
            number: cardData.cpf.replace(/\D/g, ''),
          },
        },
        card_expiration_month: String(cardData.expiry_month || '').padStart(
          2,
          '0'
        ),
        card_expiration_year: '20' + String(cardData.expiry_year || ''),
        security_code: cardData.cvv,
      };

      // Cria o token usando a API do Mercado Pago com a public key
      const response = await fetch(
        'https://api.mercadopago.com/v1/card_tokens?public_key=' +
          environment.mercadoPagoPublicKey,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(tokenData),
        }
      );

      if (!response.ok) {
        let errorMessage = 'Erro ao criar token do cartão';
        let errorDetails: any = null;
        try {
          errorDetails = await response.json();
          errorMessage =
            errorDetails.message ||
            errorDetails.error ||
            errorDetails.cause?.[0]?.description ||
            errorMessage;
          console.error('Erro da API Mercado Pago ao criar token:', {
            status: response.status,
            statusText: response.statusText,
            error: errorDetails,
          });
        } catch (e) {
          const text = await response.text();
          console.error('Erro ao processar resposta (não JSON):', text);
          errorMessage = `Erro ${response.status}: ${response.statusText}`;
        }
        throw new Error(errorMessage);
      }

      const data = await response.json();
      console.log('Token criado com sucesso - RESPOSTA COMPLETA:', {
        id: data.id,
        status: data.status,
        date_created: data.date_created,
        date_due: data.date_due,
        live_mode: data.live_mode,
        expiration_month: data.expiration_month,
        expiration_year: data.expiration_year,
        card_id: data.card_id,
        first_six_digits: data.first_six_digits,
        last_four_digits: data.last_four_digits,
        security_code_length: data.security_code_length,
        cardholder: data.cardholder,
        // Log completo para debug
        full_response: data,
      });

      this.cardToken = data.id;

      if (!this.cardToken) {
        console.error('Token não encontrado na resposta:', data);
        throw new Error('Token não foi gerado pela API do Mercado Pago');
      }

      // Valida que o token foi criado corretamente
      if (data.status !== 'active') {
        console.warn('Token criado mas status não é "active":', data.status);
      }
    } catch (error: any) {
      console.error('Erro ao criar token do cartão:', error);
      this.alertService.error(
        'Erro',
        error.message ||
          'Não foi possível processar o cartão. Verifique os dados e tente novamente.'
      );
      this.cardToken = null;
      throw error;
    }
  }

  async processPayment() {
    if (!this.planId || !this.plan) return;

    if (this.selectedMethod === 'credit_card' && this.creditCardForm.invalid) {
      this.alertService.warning(
        'Dados inválidos',
        'Por favor, preencha todos os dados do cartão corretamente.'
      );
      return;
    }

    this.processing = true;
    this.cardToken = null; // Limpa token anterior
    const paymentData: PaymentData = {
      plan_id: this.planId,
      payment_method: this.selectedMethod,
    };

    if (this.selectedMethod === 'credit_card') {
      // Cria o token do cartão ANTES de enviar para o backend
      // O token deve ser criado imediatamente antes de ser usado
      try {
        await this.createCardToken();
      } catch (error: any) {
        console.error('Erro ao criar token:', error);
        this.alertService.error(
          'Erro',
          error.message ||
            'Não foi possível criar o token do cartão. Verifique os dados e tente novamente.'
        );
        this.processing = false;
        return;
      }

      if (!this.cardToken) {
        this.alertService.error(
          'Erro',
          'Token do cartão não foi gerado. Verifique os dados e tente novamente.'
        );
        this.processing = false;
        return;
      }

      // Valida que o token foi criado recentemente (não deve ter mais de 1 minuto)
      const tokenStr = String(this.cardToken || '');
      console.log('Token criado, enviando para processamento imediatamente:', {
        token: tokenStr
          ? tokenStr.substring(0, 8) +
            '...' +
            tokenStr.substring(tokenStr.length - 4)
          : 'não criado',
        timestamp: new Date().toISOString(),
      });

      const cardData = this.creditCardForm.value;

      // IMPORTANTE: Log dos dados do formulário antes de enviar
      console.log('Dados do formulário antes de enviar:', {
        expiry_month: cardData.expiry_month,
        expiry_year: cardData.expiry_year,
        expiry_month_type: typeof cardData.expiry_month,
        expiry_year_type: typeof cardData.expiry_year,
        expiry_month_empty: !cardData.expiry_month,
        expiry_year_empty: !cardData.expiry_year,
        form_value: cardData,
      });

      // IMPORTANTE: Os dados de expiração são necessários APENAS para criar o token.
      // Após o token ser criado, esses dados JÁ ESTÃO ENCAPSULADOS NO TOKEN
      // e NÃO devem ser enviados no payload do pagamento.
      // NÃO devemos enviar o número do cartão (já está no token)
      // NÃO devemos enviar o CVV (já está no token)
      // NÃO devemos enviar expiry_month/expiry_year (já estão no token)

      // IMPORTANTE: Conforme a documentação oficial do Mercado Pago Brasil,
      // quando usamos token, os dados de expiração (expiration_month/expiration_year)
      // JÁ ESTÃO ENCAPSULADOS NO TOKEN gerado. Portanto, NÃO devemos enviá-los
      // no payload do pagamento.
      // Fonte: https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post
      paymentData.credit_card = {
        token: this.cardToken,
        // number: NÃO enviar - já está no token
        name: cardData.name,
        cpf: cardData.cpf.replace(/\D/g, ''), // Remove formatação do CPF
        // expiry_month: NÃO enviar - já está no token
        // expiry_year: NÃO enviar - já está no token
        // cvv: NÃO enviar - já está no token
      };
      paymentData.installments = cardData.installments || 1;

      console.log(
        'Dados do pagamento preparados (sem expiry_month/expiry_year - dados no token):',
        {
          token: this.cardToken
            ? '***' + String(this.cardToken).slice(-4)
            : 'não gerado',
          has_token: !!this.cardToken,
          name: paymentData.credit_card?.name,
          has_cpf: !!paymentData.credit_card?.cpf,
          installments: paymentData.installments,
          note: 'expiry_month e expiry_year estão encapsulados no token, não precisam ser enviados',
        }
      );
    }

    console.log('Enviando pagamento para o backend:', {
      plan_id: paymentData.plan_id,
      payment_method: paymentData.payment_method,
      has_credit_card: !!paymentData.credit_card,
      has_token: !!paymentData.credit_card?.token,
    });

    this.paymentService.processPayment(paymentData).subscribe({
      next: (response) => {
        console.log('Resposta do pagamento:', response);
        console.log('QR Code:', response.qr_code);
        console.log(
          'QR Code Length:',
          response.qr_code ? response.qr_code.length : 0
        );
        console.log(
          'QR Code Base64:',
          response.qr_code_base64
            ? 'presente (' + response.qr_code_base64.length + ' chars)'
            : 'ausente'
        );

        // Validação do código PIX
        if (response.qr_code) {
          // Verifica se o código PIX começa com o padrão correto
          if (!response.qr_code.startsWith('000201')) {
            console.warn('⚠️ Código PIX pode estar incompleto ou inválido');
          }
          // Verifica se tem tamanho mínimo esperado (geralmente > 100 caracteres)
          if (response.qr_code.length < 100) {
            console.warn('⚠️ Código PIX muito curto, pode estar incompleto');
          }
        }

        // Se o qr_code_base64 não tem o prefixo data:image, adiciona
        if (
          response.qr_code_base64 &&
          !response.qr_code_base64.startsWith('data:image')
        ) {
          response.qr_code_base64 =
            'data:image/png;base64,' + response.qr_code_base64;
        }

        this.paymentResponse = response;
        this.processing = false;

        if (response.status === 'approved') {
          this.stopStatusPolling();
          this.showSuccess = true;
          this.alertService.success(
            'Pagamento aprovado!',
            'Seu plano foi ativado com sucesso.'
          );
          setTimeout(() => {
            this.router.navigate(['/calendar']);
          }, 3000);
        } else if (response.status === 'pending') {
          if (this.selectedMethod === 'pix') {
            if (response.qr_code || response.qr_code_base64) {
              this.alertService.info(
                'QR Code gerado',
                'Escaneie o QR Code para concluir o pagamento. Aguardando confirmação...'
              );
              // Inicia o polling automático para verificar quando o pagamento for aprovado
              this.startStatusPolling();
            } else {
              // Se não tem QR Code ainda, tenta buscar o status
              if (response.id) {
                this.alertService.warning(
                  'Aguardando QR Code',
                  'O pagamento foi criado. Aguarde alguns segundos enquanto geramos o QR Code...'
                );
                // Aguarda 2 segundos e busca o status
                setTimeout(() => {
                  this.checkPaymentStatus();
                }, 2000);
              } else {
                this.alertService.warning(
                  'QR Code não disponível',
                  'O pagamento foi criado, mas o QR Code ainda não está disponível. Verifique o status em alguns instantes.'
                );
              }
            }
          }
        } else {
          this.stopStatusPolling();
          this.alertService.error(
            'Erro no pagamento',
            response.message || 'Não foi possível processar o pagamento.'
          );
        }
      },
      error: (err) => {
        console.error('Erro ao processar pagamento:', {
          error: err,
          status: err.status,
          statusText: err.statusText,
          errorBody: err.error,
          message: err.message,
        });
        this.processing = false;

        // Tenta extrair uma mensagem de erro mais detalhada
        let errorMessage = 'Erro ao processar pagamento.';
        if (err.error) {
          if (err.error.message) {
            errorMessage = err.error.message;
          } else if (err.error.error) {
            errorMessage = err.error.error;
          } else if (typeof err.error === 'string') {
            errorMessage = err.error;
          }

          // Adiciona detalhes adicionais se disponíveis
          if (err.error.details) {
            errorMessage += `: ${err.error.details}`;
          }
        }

        this.alertService.error('Erro', errorMessage);
      },
    });
  }

  copyToClipboard(text: string) {
    navigator.clipboard.writeText(text).then(() => {
      this.alertService.success(
        'Copiado!',
        'Código copiado para a área de transferência.'
      );
    });
  }

  downloadBoleto() {
    if (this.paymentResponse?.barcode_base64) {
      const link = document.createElement('a');
      link.href = this.paymentResponse.barcode_base64;
      link.download = `boleto-${this.planId}.png`;
      link.click();
    }
  }

  goBack() {
    this.router.navigate(['/plans']);
  }

  checkPaymentStatus() {
    if (!this.paymentResponse?.id) {
      this.alertService.warning('Erro', 'ID do pagamento não encontrado.');
      return;
    }

    // Incrementa o contador de verificações
    this.statusCheckCount++;

    // Para o polling se exceder o número máximo de verificações
    if (this.statusCheckCount > this.MAX_STATUS_CHECKS) {
      this.stopStatusPolling();
      this.alertService.warning(
        'Tempo esgotado',
        'O tempo de verificação do pagamento expirou. Verifique o status manualmente.'
      );
      return;
    }

    this.paymentService
      .getPaymentStatus(this.paymentResponse.id.toString())
      .subscribe({
        next: (response) => {
          console.log('Status atualizado:', response);
          this.paymentResponse = response;

          if (response.status === 'approved') {
            // Para o polling e mostra mensagem de sucesso
            this.stopStatusPolling();
            this.showSuccess = true;
            this.alertService.success(
              'Pagamento aprovado!',
              'Seu plano foi ativado com sucesso. Redirecionando...'
            );
            setTimeout(() => {
              this.router.navigate(['/calendar']);
            }, 2000);
          } else if (response.status === 'pending') {
            // Continua o polling se ainda estiver pendente
            // O polling já está ativo (chamado de dentro do intervalo)
          } else if (
            response.status === 'rejected' ||
            response.status === 'cancelled'
          ) {
            // Para o polling se foi rejeitado ou cancelado
            this.stopStatusPolling();
            this.alertService.error(
              'Pagamento não aprovado',
              'O pagamento foi rejeitado ou cancelado. Tente novamente.'
            );
          }
        },
        error: (err) => {
          console.error('Erro ao verificar status:', err);
          // Não para o polling em caso de erro, apenas loga
          // O próximo ciclo vai tentar novamente
        },
      });
  }

  /**
   * Inicia o polling automático para verificar o status do pagamento
   */
  startStatusPolling() {
    // Para qualquer polling anterior
    this.stopStatusPolling();

    // Reseta o contador
    this.statusCheckCount = 0;

    console.log('Iniciando polling de status do pagamento...');

    // Verifica o status a cada 5 segundos
    this.statusCheckInterval = setInterval(() => {
      this.checkPaymentStatus();
    }, this.STATUS_CHECK_INTERVAL);
  }

  /**
   * Para o polling automático
   */
  stopStatusPolling() {
    if (this.statusCheckInterval) {
      console.log('Parando polling de status do pagamento...');
      clearInterval(this.statusCheckInterval);
      this.statusCheckInterval = null;
      this.statusCheckCount = 0;
    }
  }

  ngOnDestroy() {
    // Para o polling quando o componente é destruído
    this.stopStatusPolling();
  }
}

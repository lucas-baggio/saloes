export type PaymentMethod = 'pix' | 'boleto' | 'credit_card';

export interface PaymentData {
  plan_id: number;
  payment_method: PaymentMethod;
  credit_card?: {
    token: string; // Token criado pelo SDK do Mercado Pago
    number?: string; // Opcional - não é necessário quando usamos token
    name?: string;
    cpf: string; // CPF necessário para cartão
    expiry_month?: string; // Mês de expiração (MM) - necessário mesmo com token
    expiry_year?: string; // Ano de expiração (AA ou AAAA) - necessário mesmo com token
    cvv?: string; // CVV - necessário mesmo com token
  };
  installments?: number;
}

export interface PaymentResponse {
  id: string;
  status: 'pending' | 'processing' | 'approved' | 'rejected' | 'cancelled';
  payment_method: PaymentMethod;
  qr_code?: string; // Para PIX
  qr_code_base64?: string; // Para PIX (imagem)
  barcode?: string; // Para boleto
  barcode_base64?: string; // Para boleto (imagem)
  due_date?: string; // Para boleto
  payment_url?: string; // URL para pagamento externo
  ticket_url?: string; // URL do Mercado Pago para testar pagamento (sandbox)
  transaction_id?: string;
  message?: string;
}

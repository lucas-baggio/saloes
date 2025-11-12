export interface Expense {
  id: number;
  establishment_id: number;
  description: string;
  category: string;
  amount: number;
  due_date: string; // YYYY-MM-DD
  payment_date?: string; // YYYY-MM-DD
  payment_method:
    | 'pix'
    | 'cartao_credito'
    | 'cartao_debito'
    | 'dinheiro'
    | 'transferencia'
    | 'boleto'
    | 'outro';
  status: 'pending' | 'paid' | 'overdue';
  notes?: string;
  created_at?: string;
  updated_at?: string;
  establishment?: {
    id: number;
    name: string;
  };
}

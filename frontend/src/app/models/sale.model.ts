export interface Sale {
  id: number;
  client_id?: number;
  service_id?: number;
  scheduling_id?: number;
  establishment_id: number;
  user_id: number;
  amount: number;
  payment_method:
    | 'pix'
    | 'cartao_credito'
    | 'cartao_debito'
    | 'dinheiro'
    | 'outro';
  sale_date: string; // YYYY-MM-DD
  status: 'pending' | 'paid' | 'cancelled';
  notes?: string;
  created_at?: string;
  updated_at?: string;
  client?: {
    id: number;
    name: string;
    phone?: string;
    email?: string;
  };
  service?: {
    id: number;
    name: string;
    price: number;
  };
  scheduling?: {
    id: number;
    scheduled_date: string;
    scheduled_time: string;
    status?: string;
  };
  establishment?: {
    id: number;
    name: string;
  };
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

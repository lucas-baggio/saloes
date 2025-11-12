export interface Commission {
  id: number;
  sale_id: number;
  user_id: number;
  percentage: number;
  amount: number;
  payment_date?: string; // YYYY-MM-DD
  status: 'pending' | 'paid' | 'cancelled';
  notes?: string;
  created_at?: string;
  updated_at?: string;
  sale?: {
    id: number;
    amount: number;
    sale_date: string;
    status: string;
    establishment_id: number;
    establishment?: {
      id: number;
      name: string;
    };
    service?: {
      id: number;
      name: string;
    };
    client?: {
      id: number;
      name: string;
    };
  };
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

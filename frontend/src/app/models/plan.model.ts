export interface Plan {
  id: number;
  name: string;
  description?: string;
  price: number;
  interval: 'monthly' | 'yearly';
  features: string[];
  max_establishments?: number | null;
  max_services?: number | null;
  max_employees?: number | null;
  is_popular?: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface UserPlan {
  id: number;
  user_id: number;
  plan_id: number;
  status: 'active' | 'cancelled' | 'expired';
  starts_at: string;
  ends_at?: string;
  plan?: Plan;
  created_at?: string;
  updated_at?: string;
}

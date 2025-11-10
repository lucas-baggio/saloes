export interface Service {
  id: number;
  name: string;
  description?: string;
  price: number;
  establishment_id: number;
  user_id: number;
  created_at?: string;
  updated_at?: string;
  establishment?: {
    id: number;
    name: string;
  };
  user?: {
    id: number;
    name: string;
    email: string;
    role: string;
  };
  sub_services?: SubService[];
  subServices?: SubService[];
  schedulings?: Scheduling[];
}

export interface SubService {
  id: number;
  name: string;
  description?: string;
  price: number;
  service_id: number;
  created_at?: string;
  updated_at?: string;
}

export interface Scheduling {
  id: number;
  scheduled_date: string;
  scheduled_time: string;
  service_id: number;
  establishment_id: number;
  created_at?: string;
  updated_at?: string;
  service?: {
    id: number;
    name: string;
  };
  establishment?: {
    id: number;
    name: string;
  };
}

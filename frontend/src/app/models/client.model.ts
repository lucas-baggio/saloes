export interface Client {
  id: number;
  owner_id: number;
  name: string;
  phone?: string;
  email?: string;
  cpf?: string;
  birth_date?: string;
  address?: string;
  anamnesis?: string;
  notes?: string;
  photo?: string;
  photo_url?: string; // URL completa da foto (vem do backend)
  allergies?: string[];
  created_at?: string;
  updated_at?: string;
  owner?: {
    id: number;
    name: string;
    email: string;
  };
  schedulings?: any[];
}

export interface Establishment {
  id: number;
  name: string;
  description?: string;
  owner_id: number;
  created_at?: string;
  updated_at?: string;
  owner?: {
    id: number;
    name: string;
  };
  services?: Array<{
    id: number;
    name: string;
  }>;
}

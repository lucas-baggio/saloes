export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'owner' | 'employee';
  email_verified_at?: string | null;
  created_at?: string;
  updated_at?: string;
  establishments?: Array<{
    id: number;
    name: string;
  }>;
  services?: Array<{
    id: number;
    name: string;
  }>;
}

export interface AuthResponse {
  token: string;
  token_type: string;
  expires_at?: string;
  user: User;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  role: 'admin' | 'owner' | 'employee';
}

// User Types
export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string;
  avatar?: string;
  title?: string;
  department?: string;
  type: 'super_admin' | 'company_admin' | 'user';
  is_active: boolean;
  preferences: UserPreferences;
  permissions: string[];
  roles: string[];
  company?: Company;
  created_at?: string;
  updated_at?: string;
}

export interface UserPreferences {
  theme: 'dark' | 'light';
  locale: 'tr' | 'en';
}

// Company Types
export interface Company {
  id: number;
  name: string;
  slug: string;
  legal_name?: string;
  tax_office?: string;
  tax_number?: string;
  phone?: string;
  email?: string;
  website?: string;
  address?: string;
  city?: string;
  district?: string;
  postal_code?: string;
  country?: string;
  sector?: string;
  employee_count?: string;
  logo?: string;
  settings?: Record<string, unknown>;
  package_type: 'starter' | 'professional' | 'enterprise';
  user_limit: number;
  current_users?: number;
  storage_limit: number;
  license_start_date?: string;
  license_end_date?: string;
  status: 'active' | 'suspended' | 'cancelled' | 'trial';
  trial_ends_at?: string;
  active_modules?: string[];
}

// Module Types
export interface Module {
  id: number;
  name: string;
  slug: string;
  description?: string;
  icon?: string;
  is_core: boolean;
  price_monthly: number;
  price_yearly: number;
  is_active: boolean;
  activated_at?: string;
  expires_at?: string;
}

// Role Types
export interface Role {
  id: number;
  name: string;
  permissions: string[];
  users_count?: number;
}

export interface Permission {
  group: string;
  permissions: string[];
}

// Auth Types
export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterData {
  company_name: string;
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

// API Response Types
export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]> | null;
  timestamp: string;
}

export interface PaginatedResponse<T> {
  success: boolean;
  message: string;
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  errors?: Record<string, string[]> | null;
  timestamp: string;
}

// Activity Log Types
export interface ActivityLog {
  id: number;
  user_id?: number;
  user_name?: string;
  action: string;
  model_type?: string;
  model_id?: number;
  description?: string;
  old_values?: Record<string, unknown>;
  new_values?: Record<string, unknown>;
  ip_address?: string;
  user_agent?: string;
  is_successful: boolean;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  company?: {
    id: number;
    name: string;
  };
}

// Dashboard Types
export interface DashboardData {
  welcome_message: string;
  company?: {
    name: string;
    status: string;
    package_type: string;
    trial_ends_at?: string;
    license_end_date?: string;
  };
  stats: {
    total_users?: number;
    active_modules?: number;
    [key: string]: number | undefined;
  };
  quick_actions: QuickAction[];
}

export interface QuickAction {
  label: string;
  icon: string;
  route: string;
}

// Admin Dashboard Types
export interface AdminDashboardData {
  stats: {
    total_companies: number;
    active_companies: number;
    trial_companies: number;
    suspended_companies: number;
    total_users: number;
    active_users: number;
    total_modules: number;
    new_companies_this_month: number;
  };
  package_distribution: Record<string, number>;
  recent_companies: Company[];
  recent_activities: ActivityLog[];
  expiring_trials: Company[];
  expiring_licenses: Company[];
}

// Notification Types
export interface Notification {
  id: string;
  type: string;
  data: Record<string, unknown>;
  read_at?: string;
  created_at: string;
}

// Common Types
export interface SelectOption {
  value: string | number;
  label: string;
}

export interface TableColumn<T> {
  key: keyof T | string;
  label: string;
  sortable?: boolean;
  render?: (value: unknown, row: T) => React.ReactNode;
}

// Form Types
export interface FormField {
  name: string;
  label: string;
  type: 'text' | 'email' | 'password' | 'textarea' | 'select' | 'checkbox' | 'file' | 'date' | 'number';
  placeholder?: string;
  required?: boolean;
  options?: SelectOption[];
  validation?: Record<string, unknown>;
}


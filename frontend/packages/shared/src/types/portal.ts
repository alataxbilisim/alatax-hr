// Portal (Employee Self-Service) Types
// User/Company/ApiResponse/PaginatedResponse → types/modules.ts ve types/api.ts kanonik

import type { User } from './modules';

export interface AuthResponse {
  user: User;
  token: string;
}

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
}

export interface PortalDashboardData {
  employee: {
    id: number;
    employee_code: string;
    title: string;
    department: {
      id: number;
      name: string;
    } | null;
    manager: {
      id: number;
      user: {
        name: string;
      };
    } | null;
    hire_date: string;
  };
  upcoming_leaves: PortalLeaveRequest[];
  pending_requests: EmployeeRequest[];
  latest_announcements: Announcement[];
}

export interface PortalLeaveRequest {
  id: number;
  leave_type_id: number;
  leave_type: {
    id: number;
    name: string;
    unit: string;
  };
  start_date: string;
  end_date: string;
  total_days: number;
  reason: string | null;
  status: 'pending' | 'approved' | 'rejected' | 'cancelled';
  document_path: string | null;
  approver: {
    id: number;
    name: string;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface PortalLeaveBalance {
  id: number;
  leave_type_id: number;
  leave_type: {
    id: number;
    name: string;
    unit: string;
  };
  year: number;
  total_days: number;
  used_days: number;
  remaining_days: number;
}

export interface PortalLeaveType {
  id: number;
  name: string;
  description: string | null;
  unit: 'day' | 'hour';
  default_limit: number;
  is_paid: boolean;
  requires_document: boolean;
}

export interface EmployeeDocument {
  id: number;
  employee_id: number;
  name: string;
  file_path: string;
  file_type: string | null;
  description: string | null;
  issue_date: string | null;
  expiry_date: string | null;
  is_private: boolean;
  uploaded_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface Payslip {
  id: number;
  employee_id: number;
  period: string; // YYYY-MM format
  period_label?: string;
  gross_salary: number;
  net_salary: number;
  deductions: Record<string, number> | null;
  bonuses: Record<string, number> | null;
  file_path: string | null;
  is_published: boolean;
  published_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Announcement {
  id: number;
  company_id: number;
  title: string;
  content: string;
  summary?: string;
  type: 'general' | 'urgent' | 'department' | 'personal';
  type_label?: string;
  target_departments: number[] | null;
  target_employees?: number[] | null;
  published_at: string;
  expires_at: string | null;
  is_active: boolean;
  is_pinned?: boolean;
  is_read?: boolean;
  created_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface EmployeeRequest {
  id: number;
  employee_id: number;
  type: string;
  request_type?: {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
  };
  title: string;
  description: string | null;
  details: Record<string, unknown> | null;
  status: 'pending' | 'in_review' | 'approved' | 'rejected' | 'cancelled';
  priority?: 'low' | 'normal' | 'high' | 'urgent';
  attachments: string[] | null;
  approved_by: number | null;
  approved_at: string | null;
  rejection_reason: string | null;
  created_at: string;
  updated_at: string;
}

export interface RequestType {
  id: number;
  company_id: number;
  name: string;
  slug: string;
  description: string | null;
  requires_approval: boolean;
  approver_role: string | null;
  form_schema: Record<string, unknown> | null;
  icon: string | null;
  color: string | null;
  is_active: boolean;
}

export interface PortalProfile {
  user: {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar: string | null;
  };
  employee: {
    id: number;
    employee_code: string;
    title: string;
    department: {
      id: number;
      name: string;
    } | null;
    hire_date: string;
    birth_date: string | null;
    personal_email: string | null;
    personal_phone: string | null;
    address: string | null;
    city: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    emergency_contact_relation: string | null;
  };
}

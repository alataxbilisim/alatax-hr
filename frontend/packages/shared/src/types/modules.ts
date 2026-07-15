/**
 * Module-specific Type Definitions
 * Her modül için standart type tanımları
 */

import { BaseEntity, CompanyScopedEntity, UserReference } from './api';
import { 
  LeaveStatus, 
  ApplicationStatus, 
  OnboardingStatus, 
  TrainingStatus, 
  AssetStatus, 
  SurveyStatus,
  ReviewStatus,
  ExpenseStatus 
} from '../constants/actions';

/** Özel alan değeri (file şimdilik path/id string) */
export type CustomFieldValue = string | number | boolean | string[] | null;

// ============================================
// USER & COMPANY TYPES
// ============================================

export interface User extends BaseEntity {
  company_id: number | null;
  name: string;
  email: string;
  phone?: string | null;
  avatar?: string | null;
  title?: string | null;
  department?: string | null;
  type: 'super_admin' | 'company_admin' | 'user';
  is_active: boolean;
  must_change_password?: boolean;
  two_factor_enabled?: boolean;
  last_login_at?: string | null;
  preferences?: {
    theme?: 'light' | 'dark';
    density?: 'comfortable' | 'compact';
    /** Company ContextSidebar geniş mi (true) / 48px daraltılmış mı (false) */
    contextSidebarExpanded?: boolean;
    locale?: string;
    notifications?: {
      email?: {
        approvals?: boolean;
        requests?: boolean;
        tasks?: boolean;
      };
    };
  };
  company?: Company | null;
  roles?: Role[];
  permissions?: string[];
}

export interface Company extends BaseEntity {
  name: string;
  slug: string;
  legal_name?: string | null;
  tax_office?: string | null;
  tax_number?: string | null;
  logo?: string | null;
  logo_path?: string | null;
  email?: string | null;
  phone?: string | null;
  website?: string | null;
  address?: string | null;
  city?: string | null;
  district?: string | null;
  postal_code?: string | null;
  country?: string | null;
  sector?: string | null;
  employee_count?: string | null;
  status: 'active' | 'inactive' | 'trial' | 'suspended' | 'cancelled';
  package_type?: string | null;
  user_limit?: number | null;
  current_users?: number | null;
  location_limit?: number | null;
  current_locations?: number | null;
  employee_limit?: number | null;
  storage_limit?: number | null;
  license_package_id?: number | null;
  license_start_date?: string | null;
  license_end_date?: string | null;
  license_expires_at?: string | null;
  trial_ends_at?: string | null;
  current_balance?: number | null;
  balance_label?: string | null;
  active_modules?: string[];
  settings?: CompanySettings | Record<string, unknown>;
}

export interface Department extends CompanyScopedEntity {
  name: string;
  code?: string | null;
  description?: string | null;
  manager_id?: number | null;
  parent_id?: number | null;
  is_active: boolean;
  sort_order: number;
  created_by?: number | null;
  updated_by?: number | null;
  deleted_at?: string | null;
  manager?: UserReference | null;
  parent?: Department | null;
}

export interface Branch extends CompanyScopedEntity {
  name: string;
  code?: string | null;
  address?: string | null;
  city?: string | null;
  district?: string | null;
  postal_code?: string | null;
  country?: string | null;
  phone?: string | null;
  email?: string | null;
  manager_id?: number | null;
  manager?: UserReference | null;
  is_active: boolean;
  is_headquarters: boolean;
  latitude?: number | null;
  longitude?: number | null;
  full_address?: string;
}

export interface Employee extends CompanyScopedEntity {
  user_id?: number | null;
  department_id?: number | null;
  employee_code: string;
  title?: string | null;
  position?: string | null;
  manager_id?: number | null;
  birth_date?: string | null;
  national_id?: string | null;
  gender?: 'male' | 'female' | 'other' | null;
  marital_status?: 'single' | 'married' | 'divorced' | 'widowed' | null;
  blood_type?: string | null;
  education_level?: string | null;
  personal_email?: string | null;
  personal_phone?: string | null;
  address?: string | null;
  city?: string | null;
  district?: string | null;
  postal_code?: string | null;
  emergency_contact_name?: string | null;
  emergency_contact_phone?: string | null;
  emergency_contact_relation?: string | null;
  hire_date?: string | null;
  contract_start_date?: string | null;
  contract_end_date?: string | null;
  contract_type?: 'permanent' | 'temporary' | 'intern' | 'contract' | null;
  work_type?: 'full_time' | 'part_time' | 'remote' | 'hybrid' | null;
  gross_salary?: number | null;
  net_salary?: number | null;
  currency?: string | null;
  bank_name?: string | null;
  iban?: string | null;
  sgk_number?: string | null;
  sgk_start_date?: string | null;
  status: 'active' | 'on_leave' | 'suspended' | 'terminated';
  termination_date?: string | null;
  termination_reason?: string | null;
  notes?: string | null;
  custom_fields?: Record<string, CustomFieldValue> | null;
  user?: User | null;
  department?: Department | null;
  manager?: Employee | null;
}

export interface CustomFieldDefinition extends CompanyScopedEntity {
  entity_type: string;
  field_key: string;
  field_label: string;
  field_type: 'text' | 'number' | 'date' | 'datetime' | 'select' | 'checkbox' | 'radio' | 'textarea' | 'file' | 'email' | 'phone' | 'url';
  field_options?: Array<{ value: string; label: string }> | null;
  is_required: boolean;
  is_active: boolean;
  sort_order: number;
  validation_rules?: string[] | null;
  placeholder?: string | null;
  help_text?: string | null;
  default_value?: string | null;
}

export interface CompanySettings {
  smtp?: SmtpSettings | null;
  sms?: SmsSettings | null;
  general?: GeneralSettings | null;
  integrations?: IntegrationSettings | null;
}

export interface SmtpSettings {
  host: string;
  port: number;
  encryption: 'tls' | 'ssl' | 'none';
  username: string;
  password?: string; // Frontend'de gösterilmez, sadece güncelleme için
  from_address: string;
  from_name: string;
}

export interface SmsSettings {
  provider: 'netgsm' | 'iletimerkezi' | 'twilio' | 'custom';
  username: string;
  password?: string; // Frontend'de gösterilmez, sadece güncelleme için
  sender: string;
  api_url?: string | null;
}

export interface GeneralSettings {
  timezone: string;
  language: 'tr' | 'en';
  date_format: string;
  currency: string;
  working_days: number[];
  default_work_start?: string;
  default_work_end?: string;
  late_tolerance_minutes?: number;
}

export interface IntegrationSettings {
  webhook_url?: string | null;
  api_key?: string | null;
}

export interface Role extends BaseEntity {
  name: string;
  guard_name: string;
  permissions?: Permission[];
}

export interface Permission {
  id: number;
  name: string;
  guard_name: string;
}

// ============================================
// RECRUITMENT TYPES
// ============================================

export interface JobPosition extends CompanyScopedEntity {
  title: string;
  slug: string;
  department: string;
  location: string;
  employment_type: 'full_time' | 'part_time' | 'contract' | 'internship' | 'remote';
  experience_level?: 'entry' | 'mid' | 'senior' | 'lead' | 'manager' | string | null;
  salary_min?: number | null;
  salary_max?: number | null;
  description: string;
  requirements?: string | null;
  benefits?: string | null;
  status: 'draft' | 'active' | 'paused' | 'closed';
  opens_at?: string | null;
  closes_at?: string | null;
  applications_count?: number;
}

export interface JobApplication extends CompanyScopedEntity {
  position_id: number;
  position?: JobPosition;
  name: string;
  email: string;
  phone?: string | null;
  cv_path?: string | null;
  cover_letter?: string | null;
  status: ApplicationStatus;
  rating?: number | null;
  notes?: string | null;
  source?: string | null;
}

// ============================================
// LEAVE MANAGEMENT TYPES
// ============================================

export interface LeaveType extends CompanyScopedEntity {
  name: string;
  max_days: number;
  is_paid: boolean;
  requires_approval: boolean;
  description?: string | null;
  color?: string | null;
}

export interface LeaveRequest extends CompanyScopedEntity {
  user_id: number;
  user?: UserReference;
  leave_type_id: number;
  leave_type?: LeaveType;
  start_date: string;
  end_date: string;
  days: number;
  reason: string;
  status: LeaveStatus;
  approved_by?: number | null;
  approver?: UserReference;
  approved_at?: string | null;
  rejection_reason?: string | null;
}

export interface LeaveBalance extends BaseEntity {
  user_id: number;
  leave_type_id: number;
  leave_type?: LeaveType;
  year: number;
  total_days: number;
  used_days: number;
  remaining_days: number;
  carried_over_days: number;
}

// ============================================
// DOCUMENT MANAGEMENT TYPES
// ============================================

export interface DocumentCategory extends CompanyScopedEntity {
  name: string;
  description?: string | null;
  color?: string | null;
  documents_count?: number;
}

export interface Document extends CompanyScopedEntity {
  category_id?: number | null;
  category?: DocumentCategory;
  user_id?: number | null;
  user?: UserReference;
  title: string;
  description?: string | null;
  file_path: string;
  file_name: string;
  file_size: number;
  mime_type: string;
  is_public: boolean;
  expires_at?: string | null;
}

// ============================================
// ONBOARDING TYPES
// ============================================

export interface OnboardingTemplate extends CompanyScopedEntity {
  name: string;
  description?: string | null;
  department?: string | null;
  duration_days: number;
  is_active: boolean;
  tasks?: OnboardingTask[];
}

export interface OnboardingTask extends BaseEntity {
  template_id?: number;
  process_id?: number;
  title: string;
  description?: string | null;
  order: number;
  required: boolean;
  assignee_type: 'hr' | 'manager' | 'employee' | 'it';
  due_days: number;
  is_completed?: boolean;
  completed_at?: string | null;
  completed_by?: number | null;
}

export interface OnboardingProcess extends CompanyScopedEntity {
  template_id?: number;
  template?: OnboardingTemplate;
  user_id: number;
  user?: UserReference;
  assigned_by?: number | null;
  start_date: string;
  expected_end_date?: string | null;
  actual_end_date?: string | null;
  status: OnboardingStatus;
  progress: number;
  tasks?: OnboardingTask[];
}

// ============================================
// PERFORMANCE TYPES
// ============================================

export interface PerformancePeriod extends CompanyScopedEntity {
  name: string;
  start_date: string;
  end_date: string;
  status: 'draft' | 'active' | 'closed';
  description?: string | null;
}

export interface PerformanceCriteria extends CompanyScopedEntity {
  name: string;
  description?: string | null;
  weight: number;
  category?: string | null;
  is_active: boolean;
}

export interface PerformanceReview extends CompanyScopedEntity {
  period_id: number;
  period?: PerformancePeriod;
  user_id: number;
  user?: UserReference;
  reviewer_id?: number | null;
  reviewer?: UserReference;
  status: ReviewStatus;
  overall_score?: number | null;
  comments?: string | null;
  submitted_at?: string | null;
  approved_at?: string | null;
  scores?: PerformanceScore[];
}

export interface PerformanceScore extends BaseEntity {
  review_id: number;
  criteria_id: number;
  criteria?: PerformanceCriteria;
  score: number;
  comments?: string | null;
}

// ============================================
// TRAINING TYPES
// ============================================

export interface Training extends CompanyScopedEntity {
  title: string;
  description?: string | null;
  category?: string | null;
  trainer?: string | null;
  duration_hours: number;
  max_participants?: number | null;
  is_mandatory: boolean;
  is_active: boolean;
  sessions_count?: number;
}

export interface TrainingSession extends CompanyScopedEntity {
  training_id: number;
  training?: Training;
  start_date: string;
  end_date: string;
  location?: string | null;
  status: TrainingStatus;
  max_participants?: number | null;
  participants_count?: number;
  participants?: TrainingParticipant[];
}

export interface TrainingParticipant extends BaseEntity {
  session_id: number;
  user_id: number;
  user?: UserReference;
  status: 'enrolled' | 'attended' | 'completed' | 'cancelled';
  score?: number | null;
  certificate_path?: string | null;
}

// ============================================
// ASSET MANAGEMENT TYPES
// ============================================

export interface AssetCategory extends CompanyScopedEntity {
  name: string;
  description?: string | null;
  depreciation_years?: number | null;
  assets_count?: number;
}

export interface Asset extends CompanyScopedEntity {
  category_id?: number | null;
  category?: AssetCategory;
  name: string;
  asset_code: string;
  serial_number?: string | null;
  brand?: string | null;
  model?: string | null;
  purchase_date?: string | null;
  purchase_price?: number | null;
  warranty_expires_at?: string | null;
  status: AssetStatus;
  current_assignment?: AssetAssignment | null;
  notes?: string | null;
}

export interface AssetAssignment extends BaseEntity {
  asset_id: number;
  asset?: Asset;
  user_id: number;
  user?: UserReference;
  assigned_at: string;
  returned_at?: string | null;
  notes?: string | null;
}

// ============================================
// SURVEY TYPES
// ============================================

export interface Survey extends CompanyScopedEntity {
  title: string;
  description?: string | null;
  type: 'general' | 'pulse' | 'feedback' | 'satisfaction';
  status: SurveyStatus;
  is_anonymous: boolean;
  starts_at?: string | null;
  ends_at?: string | null;
  questions?: SurveyQuestion[];
  responses_count?: number;
}

export interface SurveyQuestion extends BaseEntity {
  survey_id: number;
  question: string;
  type: 'text' | 'textarea' | 'radio' | 'checkbox' | 'rating' | 'scale';
  options?: string[];
  is_required: boolean;
  order: number;
}

export interface SurveyResponse extends BaseEntity {
  survey_id: number;
  user_id?: number | null;
  answers: SurveyAnswer[];
  submitted_at: string;
}

export interface SurveyAnswer extends BaseEntity {
  response_id: number;
  question_id: number;
  answer: string | string[] | number;
}

// ============================================
// TIMESHEET / ATTENDANCE TYPES
// ============================================

export interface AttendanceRecord extends CompanyScopedEntity {
  user_id: number;
  user?: UserReference;
  date: string;
  clock_in?: string | null;
  clock_out?: string | null;
  break_start?: string | null;
  break_end?: string | null;
  work_hours?: number | null;
  break_hours?: number | null;
  overtime_hours?: number | null;
  status: 'present' | 'absent' | 'late' | 'half_day' | 'remote';
  notes?: string | null;
  approved_by?: number | null;
  approved_at?: string | null;
}

export interface Shift extends CompanyScopedEntity {
  name: string;
  start_time: string;
  end_time: string;
  break_duration: number;
  is_active: boolean;
}

// ============================================
// EXPENSE MANAGEMENT TYPES
// ============================================

export interface ExpenseCategory extends CompanyScopedEntity {
  name: string;
  description?: string | null;
  max_amount?: number | null;
  requires_receipt: boolean;
}

export interface ExpenseClaim extends CompanyScopedEntity {
  user_id: number;
  user?: UserReference;
  title: string;
  description?: string | null;
  total_amount: number;
  currency: string;
  status: ExpenseStatus;
  submitted_at?: string | null;
  approved_by?: number | null;
  approved_at?: string | null;
  paid_at?: string | null;
  rejection_reason?: string | null;
  items?: ExpenseItem[];
}

export interface ExpenseItem extends BaseEntity {
  claim_id: number;
  category_id?: number | null;
  category?: ExpenseCategory;
  description: string;
  amount: number;
  date: string;
  receipt_path?: string | null;
}

// ============================================
// ACTIVITY LOG TYPES
// ============================================

export interface ActivityLog extends BaseEntity {
  company_id?: number | null;
  user_id?: number | null;
  user?: UserReference;
  user_name?: string | null;
  action: string;
  model_type?: string | null;
  model_id?: number | null;
  description?: string | null;
  old_values?: Record<string, unknown> | null;
  new_values?: Record<string, unknown> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  url?: string | null;
  method?: string | null;
  is_successful: boolean;
  error_message?: string | null;
}

// ============================================
// NOTIFICATION TYPES
// ============================================

export interface Notification {
  id: string;
  type: string;
  title: string;
  message: string;
  link?: string | null;
  panel?: string | null;
  data?: Record<string, unknown>;
  read_at?: string | null;
  created_at?: string;
  company_id?: number | null;
}


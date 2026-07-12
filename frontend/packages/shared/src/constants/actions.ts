/**
 * Action Type Constants
 * CRUD ve diğer action type'ları
 */

// CRUD Actions
export const CRUD_ACTIONS = {
  VIEW: 'view',
  CREATE: 'create',
  EDIT: 'edit',
  DELETE: 'delete',
} as const;

// Status Actions
export const STATUS_ACTIONS = {
  APPROVE: 'approve',
  REJECT: 'reject',
  CANCEL: 'cancel',
  SUBMIT: 'submit',
  COMPLETE: 'complete',
  ACTIVATE: 'activate',
  DEACTIVATE: 'deactivate',
} as const;

// Auth Actions
export const AUTH_ACTIONS = {
  LOGIN: 'login',
  LOGOUT: 'logout',
  REGISTER: 'register',
  PASSWORD_RESET: 'password_reset',
  PROFILE_UPDATE: 'profile_update',
} as const;

// Common Status Values
export const STATUS_VALUES = {
  PENDING: 'pending',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
  COMPLETED: 'completed',
  IN_PROGRESS: 'in_progress',
  DRAFT: 'draft',
  ACTIVE: 'active',
  INACTIVE: 'inactive',
  EXPIRED: 'expired',
} as const;

// User Types
export const USER_TYPES = {
  SUPER_ADMIN: 'super_admin',
  COMPANY_ADMIN: 'company_admin',
  USER: 'user',
} as const;

// Company Status
export const COMPANY_STATUS = {
  ACTIVE: 'active',
  INACTIVE: 'inactive',
  TRIAL: 'trial',
  SUSPENDED: 'suspended',
} as const;

// Leave Request Status
export const LEAVE_STATUS = {
  PENDING: 'pending',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
} as const;

// Application Status (Job Applications)
export const APPLICATION_STATUS = {
  PENDING: 'pending',
  REVIEWING: 'reviewing',
  INTERVIEWED: 'interviewed',
  OFFERED: 'offered',
  HIRED: 'hired',
  REJECTED: 'rejected',
  WITHDRAWN: 'withdrawn',
} as const;

// Onboarding Status
export const ONBOARDING_STATUS = {
  PENDING: 'pending',
  IN_PROGRESS: 'in_progress',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
} as const;

// Training Status
export const TRAINING_STATUS = {
  DRAFT: 'draft',
  ACTIVE: 'active',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
} as const;

// Asset Status
export const ASSET_STATUS = {
  AVAILABLE: 'available',
  ASSIGNED: 'assigned',
  MAINTENANCE: 'maintenance',
  DISPOSED: 'disposed',
} as const;

// Survey Status
export const SURVEY_STATUS = {
  DRAFT: 'draft',
  ACTIVE: 'active',
  CLOSED: 'closed',
} as const;

// Performance Review Status
export const REVIEW_STATUS = {
  DRAFT: 'draft',
  IN_PROGRESS: 'in_progress',
  SUBMITTED: 'submitted',
  APPROVED: 'approved',
  REJECTED: 'rejected',
} as const;

// Expense Claim Status
export const EXPENSE_STATUS = {
  DRAFT: 'draft',
  SUBMITTED: 'submitted',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  PAID: 'paid',
} as const;

// Type exports
export type CrudAction = typeof CRUD_ACTIONS[keyof typeof CRUD_ACTIONS];
export type StatusAction = typeof STATUS_ACTIONS[keyof typeof STATUS_ACTIONS];
export type UserType = typeof USER_TYPES[keyof typeof USER_TYPES];
export type CompanyStatus = typeof COMPANY_STATUS[keyof typeof COMPANY_STATUS];
export type LeaveStatus = typeof LEAVE_STATUS[keyof typeof LEAVE_STATUS];
export type ApplicationStatus = typeof APPLICATION_STATUS[keyof typeof APPLICATION_STATUS];
export type OnboardingStatus = typeof ONBOARDING_STATUS[keyof typeof ONBOARDING_STATUS];
export type TrainingStatus = typeof TRAINING_STATUS[keyof typeof TRAINING_STATUS];
export type AssetStatus = typeof ASSET_STATUS[keyof typeof ASSET_STATUS];
export type SurveyStatus = typeof SURVEY_STATUS[keyof typeof SURVEY_STATUS];
export type ReviewStatus = typeof REVIEW_STATUS[keyof typeof REVIEW_STATUS];
export type ExpenseStatus = typeof EXPENSE_STATUS[keyof typeof EXPENSE_STATUS];


/**
 * Route Path Constants
 * Tüm uygulamalarda kullanılan route path'leri
 */

// Auth Routes
export const AUTH_ROUTES = {
  LOGIN: '/login',
  REGISTER: '/register',
  FORGOT_PASSWORD: '/forgot-password',
  RESET_PASSWORD: '/reset-password',
} as const;

// Company Panel Routes
export const COMPANY_ROUTES = {
  DASHBOARD: '/dashboard',
  
  // User Management
  USERS: '/users',
  ROLES: '/roles',
  AUDIT_LOGS: '/audit-logs',
  
  // Recruitment
  RECRUITMENT: '/recruitment',
  RECRUITMENT_POSITIONS: '/recruitment/positions',
  RECRUITMENT_APPLICATIONS: '/recruitment/applications',
  
  // Documents
  DOCUMENTS: '/documents',
  DOCUMENTS_CATEGORIES: '/documents/categories',
  
  // Leaves
  LEAVES: '/leaves',
  LEAVES_TYPES: '/leaves/types',
  LEAVES_BALANCES: '/leaves/balances',
  
  // Onboarding
  ONBOARDING: '/onboarding',
  ONBOARDING_TEMPLATES: '/onboarding/templates',
  
  // Performance
  PERFORMANCE: '/performance',
  PERFORMANCE_PERIODS: '/performance/periods',
  PERFORMANCE_CRITERIA: '/performance/criteria',
  
  // Training
  TRAINING: '/training',
  TRAINING_SESSIONS: '/training/sessions',
  
  // Assets
  ASSETS: '/assets',
  ASSETS_CATEGORIES: '/assets/categories',
  ASSETS_ASSIGNMENTS: '/assets/assignments',
  
  // Surveys
  SURVEYS: '/surveys',
  
  // Analytics
  ANALYTICS: '/analytics',
  
  // Settings
  SETTINGS: '/settings',
} as const;

// Portal Routes (Employee Self-Service)
export const PORTAL_ROUTES = {
  DASHBOARD: '/dashboard',
  PROFILE: '/profile',
  
  // Leaves
  LEAVES: '/leaves',
  
  // Documents
  DOCUMENTS: '/documents',
  
  // Payslips
  PAYSLIPS: '/payslips',
  
  // Requests
  REQUESTS: '/requests',
  
  // Training
  TRAINING: '/training',
  
  // Performance
  PERFORMANCE: '/performance',
  
  // Surveys
  SURVEYS: '/surveys',
  
  // Timesheet
  TIMESHEET: '/timesheet',
  
  // Expenses
  EXPENSES: '/expenses',
} as const;

// Admin Panel Routes
export const ADMIN_ROUTES = {
  DASHBOARD: '/dashboard',
  
  // Companies
  COMPANIES: '/companies',
  
  // License Packages
  LICENSE_PACKAGES: '/packages',
  
  // Modules
  MODULES: '/modules',
  
  // Users
  USERS: '/users',
  
  // Logs
  LOGS: '/logs',
  
  // Ledger
  LEDGER: '/ledger',
} as const;

// API Endpoints
export const API_ENDPOINTS = {
  V1: '/api/v1',
  AUTH: {
    LOGIN: '/auth/login',
    LOGOUT: '/auth/logout',
    REGISTER: '/auth/register',
    ME: '/auth/me',
    FORGOT_PASSWORD: '/auth/forgot-password',
    RESET_PASSWORD: '/auth/reset-password',
  },
  ADMIN: '/admin',
  PORTAL: '/portal',
} as const;

export type CompanyRoute = typeof COMPANY_ROUTES[keyof typeof COMPANY_ROUTES];
export type PortalRoute = typeof PORTAL_ROUTES[keyof typeof PORTAL_ROUTES];
export type AdminRoute = typeof ADMIN_ROUTES[keyof typeof ADMIN_ROUTES];


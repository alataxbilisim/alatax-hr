/**
 * Module Key Constants
 * Backend'deki module slug'larıyla eşleşmeli
 */
export const MODULE_KEYS = {
  JOB_APPLICATIONS: 'job-applications',
  DOCUMENT_MANAGEMENT: 'document-management',
  LEAVE_MANAGEMENT: 'leave-management',
  ONBOARDING: 'onboarding',
  PERFORMANCE: 'performance',
  TRAINING: 'training',
  ASSET_MANAGEMENT: 'asset-management',
  SURVEYS: 'surveys',
  HR_ANALYTICS: 'hr-analytics',
  AUDIT_LOGS: 'audit-logs',
  TIMESHEET: 'timesheet',
  EXPENSE_MANAGEMENT: 'expense-management',
} as const;

export type ModuleKey = typeof MODULE_KEYS[keyof typeof MODULE_KEYS];

/**
 * Module Labels (Turkish)
 */
export const MODULE_LABELS: Record<ModuleKey, string> = {
  [MODULE_KEYS.JOB_APPLICATIONS]: 'İşe Alım',
  [MODULE_KEYS.DOCUMENT_MANAGEMENT]: 'Evrak Yönetimi',
  [MODULE_KEYS.LEAVE_MANAGEMENT]: 'İzin Yönetimi',
  [MODULE_KEYS.ONBOARDING]: 'Onboarding',
  [MODULE_KEYS.PERFORMANCE]: 'Performans',
  [MODULE_KEYS.TRAINING]: 'Eğitim',
  [MODULE_KEYS.ASSET_MANAGEMENT]: 'Varlık Yönetimi',
  [MODULE_KEYS.SURVEYS]: 'Anketler',
  [MODULE_KEYS.HR_ANALYTICS]: 'HR Analitik',
  [MODULE_KEYS.AUDIT_LOGS]: 'Log & Denetim',
  [MODULE_KEYS.TIMESHEET]: 'Puantaj',
  [MODULE_KEYS.EXPENSE_MANAGEMENT]: 'Masraf Yönetimi',
};

/**
 * Module Icons (Bootstrap Icons names)
 */
export const MODULE_ICONS: Record<ModuleKey, string> = {
  [MODULE_KEYS.JOB_APPLICATIONS]: 'briefcase',
  [MODULE_KEYS.DOCUMENT_MANAGEMENT]: 'file-earmark-text',
  [MODULE_KEYS.LEAVE_MANAGEMENT]: 'calendar-check',
  [MODULE_KEYS.ONBOARDING]: 'person-check',
  [MODULE_KEYS.PERFORMANCE]: 'graph-up',
  [MODULE_KEYS.TRAINING]: 'mortarboard',
  [MODULE_KEYS.ASSET_MANAGEMENT]: 'laptop',
  [MODULE_KEYS.SURVEYS]: 'clipboard-data',
  [MODULE_KEYS.HR_ANALYTICS]: 'bar-chart-line',
  [MODULE_KEYS.AUDIT_LOGS]: 'journal-text',
  [MODULE_KEYS.TIMESHEET]: 'clock',
  [MODULE_KEYS.EXPENSE_MANAGEMENT]: 'receipt',
};

/**
 * Module Colors (for UI theming)
 */
export const MODULE_COLORS: Record<ModuleKey, string> = {
  [MODULE_KEYS.JOB_APPLICATIONS]: '#f59e0b',
  [MODULE_KEYS.DOCUMENT_MANAGEMENT]: '#8b5cf6',
  [MODULE_KEYS.LEAVE_MANAGEMENT]: '#06b6d4',
  [MODULE_KEYS.ONBOARDING]: '#ec4899',
  [MODULE_KEYS.PERFORMANCE]: '#14b8a6',
  [MODULE_KEYS.TRAINING]: '#f97316',
  [MODULE_KEYS.ASSET_MANAGEMENT]: '#64748b',
  [MODULE_KEYS.SURVEYS]: '#a855f7',
  [MODULE_KEYS.HR_ANALYTICS]: '#0ea5e9',
  [MODULE_KEYS.AUDIT_LOGS]: '#6366f1',
  [MODULE_KEYS.TIMESHEET]: '#22c55e',
  [MODULE_KEYS.EXPENSE_MANAGEMENT]: '#ef4444',
};

/**
 * Check if a module key is valid
 */
export function isValidModuleKey(key: string): key is ModuleKey {
  return Object.values(MODULE_KEYS).includes(key as ModuleKey);
}


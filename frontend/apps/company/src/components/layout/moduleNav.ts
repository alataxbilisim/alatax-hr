import React from 'react';
import {
  BsSpeedometer2,
  BsBriefcase,
  BsFileEarmarkText,
  BsCalendarCheck,
  BsPersonCheck,
  BsPersonBadge,
  BsGraphUp,
  BsMortarboard,
  BsLaptop,
  BsClipboardData,
  BsBarChartLine,
  BsGear,
  BsPersonGear,
  BsReceipt,
  BsClockHistory,
} from 'react-icons/bs';

/** Menü öğesi için yetki bilgisi */
export interface MenuItemPermission {
  module: string;
  page: string;
}

export type StudioGroup = 'company' | 'users' | 'customize' | 'modules';

export interface MenuItem {
  path: string;
  /** i18n key (preferred) */
  labelKey: string;
  badge?: number;
  permission?: MenuItemPermission;
  /** Yönetim ContextSidebar grup başlığı */
  group?: StudioGroup;
}

export interface ModuleGroup {
  id: string;
  icon: React.ElementType;
  /** i18n key */
  labelKey: string;
  color?: string;
  basePath?: string;
  items: MenuItem[];
  moduleKey?: string;
  permissionModule?: string;
}

const STUDIO_GROUP_ORDER: StudioGroup[] = ['company', 'users', 'customize', 'modules'];

/** Operasyonel modüller (Yönetim hariç) */
export const operationalModuleGroups: ModuleGroup[] = [
  {
    id: 'dashboard',
    icon: BsSpeedometer2,
    labelKey: 'nav.dashboard',
    color: '#10b981',
    basePath: '/dashboard',
    items: [
      { path: '/dashboard', labelKey: 'nav.dashboardOverview' },
    ],
  },
  {
    id: 'employees',
    icon: BsPersonBadge,
    labelKey: 'nav.employees',
    color: '#22c55e',
    basePath: '/employees',
    permissionModule: 'employees',
    items: [
      { path: '/employees', labelKey: 'nav.employeesList', permission: { module: 'employees', page: 'list' } },
      { path: '/employees/departments', labelKey: 'nav.employeesDepartments', permission: { module: 'employees', page: 'departments' } },
      { path: '/employees/positions', labelKey: 'nav.employeesPositions', permission: { module: 'employees', page: 'positions' } },
      { path: '/employees/organization', labelKey: 'nav.employeesOrganization', permission: { module: 'employees', page: 'organization' } },
      { path: '/employees/custom-fields', labelKey: 'nav.employeesCustomFields', permission: { module: 'employees', page: 'custom_fields' } },
      { path: '/employees/reports', labelKey: 'nav.employeesReports', permission: { module: 'employees', page: 'reports' } },
    ],
  },
  {
    id: 'recruitment',
    icon: BsBriefcase,
    labelKey: 'nav.recruitment',
    color: '#f59e0b',
    basePath: '/recruitment',
    moduleKey: 'job-applications',
    permissionModule: 'recruitment',
    items: [
      { path: '/recruitment/positions', labelKey: 'nav.recruitmentPositions', permission: { module: 'recruitment', page: 'positions' } },
      { path: '/recruitment/applications', labelKey: 'nav.recruitmentApplications', permission: { module: 'recruitment', page: 'applications' } },
      { path: '/recruitment/interviews', labelKey: 'nav.recruitmentInterviews', permission: { module: 'recruitment', page: 'applications' } },
      { path: '/recruitment/cv-pool', labelKey: 'nav.recruitmentCvPool', permission: { module: 'recruitment', page: 'cv_pool' } },
      { path: '/recruitment/reports', labelKey: 'nav.recruitmentReports', permission: { module: 'recruitment', page: 'applications' } },
      { path: '/recruitment/custom-fields', labelKey: 'nav.recruitmentCustomFields', permission: { module: 'recruitment', page: 'custom_fields' } },
    ],
  },
  {
    id: 'leaves',
    icon: BsCalendarCheck,
    labelKey: 'nav.leaves',
    color: '#06b6d4',
    basePath: '/leaves',
    moduleKey: 'leave-management',
    permissionModule: 'leaves',
    items: [
      { path: '/leaves', labelKey: 'nav.leavesRequests', permission: { module: 'leaves', page: 'requests' } },
      { path: '/leaves/types', labelKey: 'nav.leavesTypes', permission: { module: 'leaves', page: 'types' } },
      { path: '/leaves/balances', labelKey: 'nav.leavesBalances', permission: { module: 'leaves', page: 'balances' } },
      { path: '/leaves/calendar', labelKey: 'nav.leavesCalendar', permission: { module: 'leaves', page: 'calendar' } },
      { path: '/leaves/holidays', labelKey: 'nav.leavesHolidays', permission: { module: 'leaves', page: 'holidays' } },
      { path: '/leaves/policies', labelKey: 'nav.leavesPolicies', permission: { module: 'leaves', page: 'accrual_policies' } },
      { path: '/leaves/reports', labelKey: 'nav.leavesReports', permission: { module: 'leaves', page: 'requests' } },
      { path: '/leaves/custom-fields', labelKey: 'nav.leavesCustomFields', permission: { module: 'leaves', page: 'custom_fields' } },
    ],
  },
  {
    id: 'expenses',
    icon: BsReceipt,
    labelKey: 'nav.expenses',
    color: '#ef4444',
    basePath: '/expenses',
    moduleKey: 'expense-management',
    permissionModule: 'expenses',
    items: [
      { path: '/expenses', labelKey: 'nav.expensesQueue', permission: { module: 'expenses', page: 'claims' } },
      { path: '/expenses/all', labelKey: 'nav.expensesAll', permission: { module: 'expenses', page: 'claims' } },
      { path: '/expenses/categories', labelKey: 'nav.expensesCategories', permission: { module: 'expenses', page: 'categories' } },
    ],
  },
  {
    id: 'timesheet',
    icon: BsClockHistory,
    labelKey: 'nav.timesheet',
    color: '#22c55e',
    basePath: '/attendance',
    moduleKey: 'timesheet',
    permissionModule: 'timesheet',
    items: [
      { path: '/attendance', labelKey: 'nav.timesheetAttendance', permission: { module: 'timesheet', page: 'attendance' } },
      { path: '/attendance/kiosk', labelKey: 'nav.timesheetKiosk', permission: { module: 'timesheet', page: 'kiosk' } },
    ],
  },
  {
    id: 'documents',
    icon: BsFileEarmarkText,
    labelKey: 'nav.documents',
    color: '#8b5cf6',
    basePath: '/documents',
    moduleKey: 'document-management',
    permissionModule: 'documents',
    items: [
      { path: '/documents', labelKey: 'nav.documentsList', permission: { module: 'documents', page: 'list' } },
      { path: '/documents/categories', labelKey: 'nav.documentsCategories', permission: { module: 'documents', page: 'categories' } },
      { path: '/documents/reports', labelKey: 'nav.documentsReports', permission: { module: 'documents', page: 'reports' } },
      { path: '/documents/custom-fields', labelKey: 'nav.documentsCustomFields', permission: { module: 'documents', page: 'custom_fields' } },
    ],
  },
  {
    id: 'onboarding',
    icon: BsPersonCheck,
    labelKey: 'nav.onboarding',
    color: '#ec4899',
    basePath: '/onboarding',
    moduleKey: 'onboarding',
    permissionModule: 'onboarding',
    items: [
      { path: '/onboarding', labelKey: 'nav.onboardingProcesses', permission: { module: 'onboarding', page: 'processes' } },
      { path: '/onboarding/templates', labelKey: 'nav.onboardingTemplates', permission: { module: 'onboarding', page: 'templates' } },
    ],
  },
  {
    id: 'performance',
    icon: BsGraphUp,
    labelKey: 'nav.performance',
    color: '#14b8a6',
    basePath: '/performance',
    moduleKey: 'performance',
    permissionModule: 'performance',
    items: [
      { path: '/performance', labelKey: 'nav.performanceReviews', permission: { module: 'performance', page: 'reviews' } },
      { path: '/performance/periods', labelKey: 'nav.performancePeriods', permission: { module: 'performance', page: 'periods' } },
      { path: '/performance/criteria', labelKey: 'nav.performanceCriteria', permission: { module: 'performance', page: 'criteria' } },
      { path: '/performance/custom-fields', labelKey: 'nav.performanceCustomFields', permission: { module: 'performance', page: 'custom_fields' } },
    ],
  },
  {
    id: 'training',
    icon: BsMortarboard,
    labelKey: 'nav.training',
    color: '#f97316',
    basePath: '/training',
    moduleKey: 'training',
    permissionModule: 'training',
    items: [
      { path: '/training', labelKey: 'nav.trainingList', permission: { module: 'training', page: 'list' } },
      { path: '/training/sessions', labelKey: 'nav.trainingSessions', permission: { module: 'training', page: 'sessions' } },
      { path: '/training/custom-fields', labelKey: 'nav.trainingCustomFields', permission: { module: 'training', page: 'custom_fields' } },
    ],
  },
  {
    id: 'assets',
    icon: BsLaptop,
    labelKey: 'nav.assets',
    color: '#64748b',
    basePath: '/assets',
    moduleKey: 'asset-management',
    permissionModule: 'assets',
    items: [
      { path: '/assets', labelKey: 'nav.assetsList', permission: { module: 'assets', page: 'list' } },
      { path: '/assets/categories', labelKey: 'nav.assetsCategories', permission: { module: 'assets', page: 'categories' } },
      { path: '/assets/custom-fields', labelKey: 'nav.assetsCustomFields', permission: { module: 'assets', page: 'custom_fields' } },
    ],
  },
  {
    id: 'surveys',
    icon: BsClipboardData,
    labelKey: 'nav.surveys',
    color: '#a855f7',
    basePath: '/surveys',
    moduleKey: 'surveys',
    permissionModule: 'surveys',
    items: [
      { path: '/surveys', labelKey: 'nav.surveysList', permission: { module: 'surveys', page: 'list' } },
    ],
  },
  {
    id: 'analytics',
    icon: BsBarChartLine,
    labelKey: 'nav.analytics',
    color: '#0ea5e9',
    basePath: '/analytics',
    moduleKey: 'hr-analytics',
    permissionModule: 'analytics',
    items: [
      { path: '/analytics', labelKey: 'nav.analyticsReports', permission: { module: 'analytics', page: 'reports' } },
    ],
  },
];

/** Altta pinlenen: Ayarlar (kişisel) + Yönetim (stüdyo) */
export const pinnedModuleGroups: ModuleGroup[] = [
  {
    id: 'account',
    icon: BsPersonGear,
    labelKey: 'nav.account',
    color: '#94a3b8',
    basePath: '/account',
    items: [
      { path: '/account/profile', labelKey: 'account.profile' },
      { path: '/account/security', labelKey: 'account.security' },
      { path: '/account/preferences', labelKey: 'account.preferences' },
    ],
  },
  {
    id: 'management',
    icon: BsGear,
    labelKey: 'nav.management',
    color: '#6366f1',
    permissionModule: 'management',
    items: [
      { path: '/settings', labelKey: 'studio.companySettings', permission: { module: 'management', page: 'settings' }, group: 'company' },
      { path: '/webhooks', labelKey: 'studio.webhooks', permission: { module: 'management', page: 'webhooks' }, group: 'company' },
      { path: '/users', labelKey: 'studio.users', permission: { module: 'management', page: 'users' }, group: 'users' },
      { path: '/roles', labelKey: 'studio.roles', permission: { module: 'management', page: 'roles' }, group: 'users' },
      { path: '/branches', labelKey: 'studio.branches', permission: { module: 'management', page: 'branches' }, group: 'users' },
      { path: '/audit-logs', labelKey: 'studio.auditLogs', permission: { module: 'management', page: 'audit_logs' }, group: 'users' },
      { path: '/lookups', labelKey: 'studio.lookups', permission: { module: 'management', page: 'lookups' }, group: 'customize' },
      { path: '/settings/custom-fields', labelKey: 'studio.customFields', permission: { module: 'management', page: 'custom_fields' }, group: 'customize' },
      { path: '/settings/forms/employee', labelKey: 'studio.formLayoutEmployee', permission: { module: 'management', page: 'forms' }, group: 'customize' },
      { path: '/settings/forms/leave_request', labelKey: 'studio.formLayoutLeave', permission: { module: 'management', page: 'forms' }, group: 'customize' },
      { path: '/settings/forms/job_application', labelKey: 'studio.formLayoutJobApplication', permission: { module: 'management', page: 'forms' }, group: 'customize' },
      { path: '/leaves/types', labelKey: 'studio.leaveTypes', permission: { module: 'leaves', page: 'types' }, group: 'modules' },
      { path: '/documents/categories', labelKey: 'studio.documentCategories', permission: { module: 'documents', page: 'categories' }, group: 'modules' },
      { path: '/assets/categories', labelKey: 'studio.assetCategories', permission: { module: 'assets', page: 'categories' }, group: 'modules' },
      { path: '/employees/custom-fields', labelKey: 'studio.cfEmployees', permission: { module: 'employees', page: 'custom_fields' }, group: 'modules' },
      { path: '/leaves/custom-fields', labelKey: 'studio.cfLeaves', permission: { module: 'leaves', page: 'custom_fields' }, group: 'modules' },
      { path: '/documents/custom-fields', labelKey: 'studio.cfDocuments', permission: { module: 'documents', page: 'custom_fields' }, group: 'modules' },
      { path: '/recruitment/custom-fields', labelKey: 'studio.cfRecruitment', permission: { module: 'recruitment', page: 'custom_fields' }, group: 'modules' },
      { path: '/performance/custom-fields', labelKey: 'studio.cfPerformance', permission: { module: 'performance', page: 'custom_fields' }, group: 'modules' },
      { path: '/training/custom-fields', labelKey: 'studio.cfTraining', permission: { module: 'training', page: 'custom_fields' }, group: 'modules' },
      { path: '/assets/custom-fields', labelKey: 'studio.cfAssets', permission: { module: 'assets', page: 'custom_fields' }, group: 'modules' },
    ],
  },
];

/** Tüm gruplar (path eşleme / geriye uyumluluk) */
export const moduleGroups: ModuleGroup[] = [
  ...operationalModuleGroups,
  ...pinnedModuleGroups,
];

export function getFilteredMenuItems(
  module: ModuleGroup,
  user: { type: string; permissions: string[] } | null
): MenuItem[] {
  if (!user) return [];

  if (user.type === 'company_admin' || user.type === 'super_admin') {
    return module.items;
  }

  return module.items.filter((item) => {
    if (!item.permission) return true;

    const { module: permModule, page } = item.permission;
    const permissions = user.permissions || [];

    if (permissions.includes('*')) return true;
    if (permissions.includes(`${permModule}.*`)) return true;
    if (permissions.includes(`${permModule}.${page}.*`)) return true;

    return permissions.some((p) => p.startsWith(`${permModule}.${page}.`));
  });
}

/** Gruplu menü öğelerini görünür gruplara ayırır (boş gruplar elenir) */
export function getVisibleGroups(
  items: MenuItem[]
): Array<{ group: StudioGroup; items: MenuItem[] }> {
  const hasAnyGroup = items.some((i) => i.group);
  if (!hasAnyGroup) {
    return [];
  }

  return STUDIO_GROUP_ORDER
    .map((group) => ({
      group,
      items: items.filter((i) => i.group === group),
    }))
    .filter((g) => g.items.length > 0);
}

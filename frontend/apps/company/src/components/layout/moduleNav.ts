import React from 'react';
import {
  BsSpeedometer2,
  BsBriefcase,
  BsCalendarCheck,
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
  BsDiagram3,
  BsPersonCheck,
  BsChatDots,
} from 'react-icons/bs';
import { HIDDEN_MODULE_SLOTS } from '../../navigation/pageRegistry';

/** Menü öğesi için yetki bilgisi — permission key rename YOK */
export interface MenuItemPermission {
  module: string;
  page: string;
}

export type StudioGroup = 'company' | 'users' | 'customize' | 'modules';

export interface MenuItem {
  path: string;
  labelKey: string;
  badge?: number;
  permission?: MenuItemPermission;
  group?: StudioGroup;
  /** Öğe-seviyesi modül lisansı */
  moduleKey?: string;
  pageKey?: string;
}

export interface ModuleGroup {
  id: string;
  icon: React.ElementType;
  labelKey: string;
  /** Dar rail için kısa etiket (yoksa labelKey) */
  shortLabelKey?: string;
  color?: string;
  basePath?: string;
  /** Çok köklü modüller (ör. varlık+doküman) için aktif eşleme */
  matchPrefixes?: string[];
  items: MenuItem[];
  moduleKey?: string;
  permissionModule?: string;
  /** true → menüde hiç render edilmez (İSG slotu) */
  hidden?: boolean;
}

const STUDIO_GROUP_ORDER: StudioGroup[] = ['company', 'users', 'customize', 'modules'];

/** E0 — 14 operasyonel + gizli İSG slotu (hidden) */
export const operationalModuleGroups: ModuleGroup[] = [
  {
    id: 'dashboard',
    icon: BsSpeedometer2,
    labelKey: 'nav.dashboard',
    color: '#10b981',
    basePath: '/dashboard',
    items: [
      { path: '/dashboard', labelKey: 'nav.dashboardOverview', pageKey: 'dashboard.overview' },
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
      { path: '/employees', labelKey: 'nav.employeesList', permission: { module: 'employees', page: 'list' }, pageKey: 'employees.list' },
      { path: '/employees/custom-fields', labelKey: 'nav.employeesCustomFields', permission: { module: 'employees', page: 'custom_fields' }, pageKey: 'employees.custom_fields' },
      { path: '/employees/reports', labelKey: 'nav.employeesReports', permission: { module: 'employees', page: 'reports' }, pageKey: 'employees.reports' },
    ],
  },
  {
    id: 'organization',
    icon: BsDiagram3,
    labelKey: 'nav.organization',
    color: '#0d9488',
    basePath: '/organization',
    matchPrefixes: ['/organization'],
    items: [
      { path: '/organization/branches', labelKey: 'studio.branches', permission: { module: 'management', page: 'branches' }, pageKey: 'organization.branches' },
      { path: '/organization/departments', labelKey: 'nav.employeesDepartments', permission: { module: 'employees', page: 'departments' }, pageKey: 'organization.departments' },
      { path: '/organization/positions', labelKey: 'nav.employeesPositions', permission: { module: 'employees', page: 'positions' }, pageKey: 'organization.positions' },
      { path: '/organization/chart', labelKey: 'nav.employeesOrganization', permission: { module: 'employees', page: 'organization' }, pageKey: 'organization.chart' },
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
      { path: '/recruitment/positions', labelKey: 'nav.recruitmentPositions', permission: { module: 'recruitment', page: 'positions' }, pageKey: 'recruitment.positions' },
      { path: '/recruitment/applications', labelKey: 'nav.recruitmentApplications', permission: { module: 'recruitment', page: 'applications' }, pageKey: 'recruitment.applications' },
      { path: '/recruitment/cv-pool', labelKey: 'nav.recruitmentCvPool', permission: { module: 'recruitment', page: 'cv_pool' }, pageKey: 'recruitment.cv_pool' },
      { path: '/recruitment/interviews', labelKey: 'nav.recruitmentInterviews', permission: { module: 'recruitment', page: 'applications' }, pageKey: 'recruitment.interviews' },
      { path: '/recruitment/reports', labelKey: 'nav.recruitmentReports', permission: { module: 'recruitment', page: 'applications' }, pageKey: 'recruitment.reports' },
      { path: '/recruitment/custom-fields', labelKey: 'nav.recruitmentCustomFields', permission: { module: 'recruitment', page: 'custom_fields' }, pageKey: 'recruitment.custom_fields' },
    ],
  },
  {
    id: 'onboarding',
    icon: BsPersonCheck,
    labelKey: 'nav.onboardingExit',
    shortLabelKey: 'nav.onboardingExitShort',
    color: '#84cc16',
    basePath: '/onboarding',
    moduleKey: 'onboarding',
    permissionModule: 'onboarding',
    items: [
      { path: '/onboarding', labelKey: 'nav.onboardingProcesses', permission: { module: 'onboarding', page: 'processes' }, pageKey: 'onboarding.processes' },
      { path: '/onboarding/templates', labelKey: 'nav.onboardingTemplates', permission: { module: 'onboarding', page: 'templates' }, pageKey: 'onboarding.templates' },
    ],
  },
  {
    id: 'pdks',
    icon: BsClockHistory,
    labelKey: 'nav.pdks',
    color: '#22c55e',
    basePath: '/pdks',
    moduleKey: 'timesheet',
    permissionModule: 'timesheet',
    items: [
      { path: '/pdks', labelKey: 'nav.timesheetAttendance', permission: { module: 'timesheet', page: 'attendance' }, pageKey: 'pdks.attendance.list' },
      { path: '/pdks/reports', labelKey: 'nav.timesheetReports', permission: { module: 'timesheet', page: 'attendance' }, pageKey: 'pdks.timesheet.list' },
      { path: '/pdks/shifts', labelKey: 'nav.timesheetShifts', permission: { module: 'timesheet', page: 'shifts' }, pageKey: 'pdks.shifts.list' },
      { path: '/pdks/shift-assignments', labelKey: 'nav.timesheetShiftAssign', permission: { module: 'timesheet', page: 'shifts' }, pageKey: 'pdks.shift_assignments.list' },
      // cihaz/QR gizli — kiosk menüde yok (route + redirect durur)
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
      { path: '/leaves', labelKey: 'nav.leavesRequests', permission: { module: 'leaves', page: 'requests' }, pageKey: 'leaves.requests.list' },
      { path: '/leaves/balances', labelKey: 'nav.leavesBalances', permission: { module: 'leaves', page: 'balances' }, pageKey: 'leaves.balances.list' },
      { path: '/leaves/calendar', labelKey: 'nav.leavesCalendar', permission: { module: 'leaves', page: 'calendar' }, pageKey: 'leaves.calendar' },
      { path: '/leaves/types', labelKey: 'nav.leavesTypes', permission: { module: 'leaves', page: 'types' }, pageKey: 'leaves.types.list' },
      { path: '/leaves/holidays', labelKey: 'nav.leavesHolidays', permission: { module: 'leaves', page: 'holidays' }, pageKey: 'leaves.holidays.list' },
      { path: '/leaves/policies', labelKey: 'nav.leavesPolicies', permission: { module: 'leaves', page: 'accrual_policies' }, pageKey: 'leaves.policies.list' },
      { path: '/leaves/reports', labelKey: 'nav.leavesReports', permission: { module: 'leaves', page: 'requests' }, pageKey: 'leaves.reports' },
      { path: '/leaves/custom-fields', labelKey: 'nav.leavesCustomFields', permission: { module: 'leaves', page: 'custom_fields' }, pageKey: 'leaves.custom_fields' },
    ],
  },
  {
    id: 'payroll',
    icon: BsReceipt,
    labelKey: 'nav.payroll',
    shortLabelKey: 'nav.payrollShort',
    color: '#ef4444',
    basePath: '/payroll',
    matchPrefixes: ['/payroll'],
    items: [
      { path: '/payroll/payslips', labelKey: 'nav.payslipsAdmin', permission: { module: 'payroll', page: 'payslips' }, pageKey: 'payroll.payslips.list' },
      { path: '/payroll/expenses', labelKey: 'nav.expensesQueue', permission: { module: 'expenses', page: 'claims' }, moduleKey: 'expense-management', pageKey: 'payroll.expenses.list' },
      { path: '/payroll/expenses/all', labelKey: 'nav.expensesAll', permission: { module: 'expenses', page: 'claims' }, moduleKey: 'expense-management', pageKey: 'payroll.expenses.all' },
      { path: '/payroll/expenses/categories', labelKey: 'nav.expensesCategories', permission: { module: 'expenses', page: 'categories' }, moduleKey: 'expense-management', pageKey: 'payroll.expenses.categories' },
      { path: '/payroll/salary-bands', labelKey: 'nav.employeesSalaryBands', permission: { module: 'employees', page: 'salary' }, pageKey: 'payroll.salary_bands' },
      { path: '/payroll/salary-reviews', labelKey: 'nav.employeesSalaryReviews', permission: { module: 'employees', page: 'salary' }, pageKey: 'payroll.salary_reviews' },
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
      { path: '/performance/periods', labelKey: 'nav.performancePeriods', permission: { module: 'performance', page: 'periods' }, pageKey: 'performance.periods.list' },
      { path: '/performance/criteria', labelKey: 'nav.performanceCriteria', permission: { module: 'performance', page: 'criteria' }, pageKey: 'performance.criteria.list' },
      { path: '/performance', labelKey: 'nav.performanceReviews', permission: { module: 'performance', page: 'reviews' }, pageKey: 'performance.reviews.list' },
      { path: '/performance/custom-fields', labelKey: 'nav.performanceCustomFields', permission: { module: 'performance', page: 'custom_fields' }, pageKey: 'performance.custom_fields' },
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
      { path: '/training', labelKey: 'nav.trainingList', permission: { module: 'training', page: 'list' }, pageKey: 'training.list' },
      { path: '/training/sessions', labelKey: 'nav.trainingSessions', permission: { module: 'training', page: 'sessions' }, pageKey: 'training.sessions.list' },
      { path: '/training/custom-fields', labelKey: 'nav.trainingCustomFields', permission: { module: 'training', page: 'custom_fields' }, pageKey: 'training.custom_fields' },
    ],
  },
  {
    id: 'ohs',
    icon: BsClipboardData,
    labelKey: 'nav.ohs',
    color: '#dc2626',
    hidden: true,
    items: [],
  },
  {
    id: 'assets-documents',
    icon: BsLaptop,
    labelKey: 'nav.assetsDocuments',
    shortLabelKey: 'nav.assetsDocumentsShort',
    color: '#64748b',
    matchPrefixes: ['/assets', '/documents'],
    items: [
      { path: '/assets', labelKey: 'nav.assetsList', permission: { module: 'assets', page: 'list' }, moduleKey: 'asset-management', pageKey: 'assets.list' },
      { path: '/assets/categories', labelKey: 'nav.assetsCategories', permission: { module: 'assets', page: 'categories' }, moduleKey: 'asset-management', pageKey: 'assets.categories' },
      { path: '/assets/custom-fields', labelKey: 'nav.assetsCustomFields', permission: { module: 'assets', page: 'custom_fields' }, moduleKey: 'asset-management', pageKey: 'assets.custom_fields' },
      { path: '/documents', labelKey: 'nav.documentsList', permission: { module: 'documents', page: 'list' }, moduleKey: 'document-management', pageKey: 'documents.list' },
      { path: '/documents/categories', labelKey: 'nav.documentsCategories', permission: { module: 'documents', page: 'categories' }, moduleKey: 'document-management', pageKey: 'documents.categories' },
      { path: '/documents/reports', labelKey: 'nav.documentsReports', permission: { module: 'documents', page: 'reports' }, moduleKey: 'document-management', pageKey: 'documents.reports' },
      { path: '/documents/custom-fields', labelKey: 'nav.documentsCustomFields', permission: { module: 'documents', page: 'custom_fields' }, moduleKey: 'document-management', pageKey: 'documents.custom_fields' },
    ],
  },
  {
    id: 'communication',
    icon: BsChatDots,
    labelKey: 'nav.communication',
    color: '#a855f7',
    basePath: '/communication',
    matchPrefixes: ['/communication'],
    items: [
      { path: '/communication/announcements', labelKey: 'nav.announcements', permission: { module: 'announcements', page: 'list' }, pageKey: 'communication.announcements' },
      { path: '/communication/surveys', labelKey: 'nav.surveysList', permission: { module: 'surveys', page: 'list' }, moduleKey: 'surveys', pageKey: 'communication.surveys' },
    ],
  },
  {
    id: 'analytics',
    icon: BsBarChartLine,
    labelKey: 'nav.analytics',
    color: '#0ea5e9',
    matchPrefixes: ['/analytics', '/reports', '/dashboards'],
    items: [
      { path: '/dashboards', labelKey: 'nav.dashboards', permission: { module: 'reports', page: 'dashboards' }, pageKey: 'analytics.dashboards.list' },
      // Tur7: "Raporlar" → rapor motoru listesi (/reports); /analytics sistem panoya yönlendiriyordu
      { path: '/reports', labelKey: 'nav.analyticsReports', permission: { module: 'reports', page: 'definitions' }, pageKey: 'analytics.reports.list' },
      { path: '/reports/measures', labelKey: 'nav.reportMeasures', permission: { module: 'reports', page: 'measures' }, pageKey: 'analytics.measures.list' },
      { path: '/reports/schedules', labelKey: 'nav.reportSchedules', permission: { module: 'reports', page: 'schedules' }, pageKey: 'analytics.schedules.list' },
      { path: '/analytics', labelKey: 'nav.analyticsOverview', permission: { module: 'analytics', page: 'reports' }, moduleKey: 'hr-analytics', pageKey: 'analytics.overview' },
    ],
  },
];

/** Alt pin: Ayarlar (kişisel) · Yönetim (Stüdyo + KVKK + lisans + log) */
export const pinnedModuleGroups: ModuleGroup[] = [
  {
    id: 'account',
    icon: BsPersonGear,
    labelKey: 'nav.account',
    color: '#94a3b8',
    basePath: '/account',
    items: [
      { path: '/account/profile', labelKey: 'account.profile', pageKey: 'account.profile' },
      { path: '/account/security', labelKey: 'account.security', pageKey: 'account.security' },
      { path: '/account/preferences', labelKey: 'account.preferences', pageKey: 'account.preferences' },
    ],
  },
  {
    id: 'management',
    icon: BsGear,
    labelKey: 'nav.management',
    color: '#6366f1',
    permissionModule: 'management',
    matchPrefixes: ['/settings', '/webhooks', '/users', '/roles', '/audit-logs', '/lookups', '/kvkk'],
    items: [
      { path: '/settings', labelKey: 'studio.companySettings', permission: { module: 'management', page: 'settings' }, group: 'company', pageKey: 'management.settings' },
      { path: '/settings/registry', labelKey: 'studio.settingsRegistry', permission: { module: 'settings', page: 'values' }, group: 'company', pageKey: 'settings.registry' },
      { path: '/settings/report-privacy', labelKey: 'reportPrivacy.title', permission: { module: 'management', page: 'settings' }, group: 'company', pageKey: 'settings.report_privacy' },
      { path: '/kvkk', labelKey: 'kvkk.title', permission: { module: 'management', page: 'kvkk' }, group: 'company', pageKey: 'management.kvkk' },
      { path: '/webhooks', labelKey: 'studio.webhooks', permission: { module: 'management', page: 'webhooks' }, group: 'company', pageKey: 'management.webhooks' },
      { path: '/users', labelKey: 'studio.users', permission: { module: 'management', page: 'users' }, group: 'users', pageKey: 'management.users' },
      { path: '/roles', labelKey: 'studio.roles', permission: { module: 'management', page: 'roles' }, group: 'users', pageKey: 'management.roles' },
      { path: '/audit-logs', labelKey: 'studio.auditLogs', permission: { module: 'management', page: 'audit_logs' }, group: 'users', pageKey: 'management.audit_logs' },
      { path: '/audit-logs/report-access', labelKey: 'reportAccessLogs.title', permission: { module: 'management', page: 'audit_logs' }, group: 'users' },
      { path: '/lookups', labelKey: 'studio.lookups', permission: { module: 'management', page: 'lookups' }, group: 'customize', pageKey: 'management.lookups' },
      { path: '/settings/custom-fields', labelKey: 'studio.customFields', permission: { module: 'management', page: 'custom_fields' }, group: 'customize', pageKey: 'management.custom_fields' },
      { path: '/settings/forms', labelKey: 'studio.formLayouts', permission: { module: 'management', page: 'forms' }, group: 'customize', pageKey: 'management.forms' },
      { path: '/settings/workflows', labelKey: 'studio.workflows', permission: { module: 'management', page: 'workflows' }, group: 'customize', pageKey: 'management.workflows' },
      { path: '/settings/notification-templates', labelKey: 'studio.notificationTemplates', permission: { module: 'management', page: 'notifications' }, group: 'customize', pageKey: 'management.notifications' },
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

/** Tüm gruplar (path eşleme) — gizli slotlar dahil değil (hidden filtre) */
export const moduleGroups: ModuleGroup[] = [
  ...operationalModuleGroups,
  ...pinnedModuleGroups,
];

export { HIDDEN_MODULE_SLOTS };

export function getFilteredMenuItems(
  module: ModuleGroup,
  user: { type: string; permissions: string[] } | null,
  activeModules: string[] = []
): MenuItem[] {
  if (!user) return [];
  if (module.hidden) return [];

  return module.items.filter((item) => {
    if (item.moduleKey && !activeModules.includes(item.moduleKey)) {
      return false;
    }

    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return true;
    }

    if (!item.permission) return true;

    const { module: permModule, page } = item.permission;
    const permissions = user.permissions || [];

    if (permissions.includes('*')) return true;
    if (permissions.includes(`${permModule}.*`)) return true;
    if (permissions.includes(`${permModule}.${page}.*`)) return true;

    return permissions.some((p) => p.startsWith(`${permModule}.${page}.`));
  });
}

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

/** Path → modül id (aktif rail) */
export function findModuleIdByPath(pathname: string): string {
  if (pathname.startsWith('/account')) {
    return 'account';
  }

  const management = pinnedModuleGroups.find((m) => m.id === 'management');
  if (management?.matchPrefixes?.some((p) => pathname === p || pathname.startsWith(`${p}/`))) {
    // /documents/categories Yönetim deep-link — ama operasyonel assets-documents de eşleşir;
    // management item exact match öncelikli değilse operasyonel kazanır. Deep-link için item check.
    const mgmtItem = management.items.some(
      (item) => pathname === item.path || pathname.startsWith(`${item.path}/`)
    );
    if (mgmtItem && management.matchPrefixes.some((p) => pathname.startsWith(p))) {
      // yalnız yönetim kökleri
      if (
        ['/settings', '/webhooks', '/users', '/roles', '/audit-logs', '/lookups', '/kvkk'].some(
          (p) => pathname === p || pathname.startsWith(`${p}/`)
        )
      ) {
        return 'management';
      }
    }
  }

  const scored = moduleGroups
    .filter((m) => !m.hidden)
    .map((module) => {
      let score = 0;
      if (module.basePath && pathname.startsWith(module.basePath)) {
        score = Math.max(score, module.basePath.length);
      }
      for (const prefix of module.matchPrefixes ?? []) {
        if (pathname === prefix || pathname.startsWith(`${prefix}/`) || pathname.startsWith(prefix)) {
          score = Math.max(score, prefix.length);
        }
      }
      for (const item of module.items) {
        if (pathname === item.path || pathname.startsWith(`${item.path}/`)) {
          score = Math.max(score, item.path.length);
        }
      }
      return { id: module.id, score };
    })
    .filter((x) => x.score > 0)
    .sort((a, b) => b.score - a.score);

  return scored[0]?.id ?? 'dashboard';
}

/**
 * E0 — Sayfa anahtarları kaydı (D4a bağlamsal ayar + D4b yardım omurgası).
 * Rota değişse bile page_key STABİL kalır. Yardım içeriği burada YAZILMAZ.
 */

export interface PageRegistryEntry {
  key: string;
  route: string;
  moduleId: string;
  titleKey: string;
  permission?: { module: string; page: string };
  icon?: string;
}

/** Gizli slotlar — menüde RENDER EDİLMEZ (Faz 6+). */
export const HIDDEN_MODULE_SLOTS = [
  { id: 'ohs', labelKey: 'nav.ohs', reason: 'Faz 6 — İSG' },
  { id: 'employees.discipline', labelKey: 'nav.employeesDiscipline', reason: 'kod yok' },
  { id: 'employees.proxy', labelKey: 'nav.employeesProxy', reason: 'kod yok' },
  { id: 'payroll.advances', labelKey: 'nav.payrollAdvances', reason: 'kod yok' },
  { id: 'payroll.per_diem', labelKey: 'nav.payrollPerDiem', reason: 'kod yok' },
  { id: 'performance.succession', labelKey: 'nav.performanceSuccession', reason: 'kod yok' },
  { id: 'training.lms', labelKey: 'nav.trainingLms', reason: 'kod yok' },
  { id: 'communication.suggestions', labelKey: 'nav.communicationSuggestions', reason: 'kod yok' },
  { id: 'pdks.devices', labelKey: 'nav.pdksDevices', reason: 'kod yok' },
] as const;

export const PAGE_REGISTRY: PageRegistryEntry[] = [
  // 1 Ana Sayfa
  { key: 'dashboard.overview', route: '/dashboard', moduleId: 'dashboard', titleKey: 'nav.dashboardOverview' },

  // 2 Personel
  { key: 'employees.list', route: '/employees', moduleId: 'employees', titleKey: 'nav.employeesList', permission: { module: 'employees', page: 'list' } },
  { key: 'employees.detail', route: '/employees/:id', moduleId: 'employees', titleKey: 'nav.employeesList', permission: { module: 'employees', page: 'list' } },
  { key: 'employees.create', route: '/employees/new', moduleId: 'employees', titleKey: 'nav.employeesList', permission: { module: 'employees', page: 'list' } },
  { key: 'employees.edit', route: '/employees/:id/edit', moduleId: 'employees', titleKey: 'nav.employeesList', permission: { module: 'employees', page: 'list' } },
  { key: 'employees.custom_fields', route: '/employees/custom-fields', moduleId: 'employees', titleKey: 'nav.employeesCustomFields', permission: { module: 'employees', page: 'custom_fields' } },
  { key: 'employees.reports', route: '/employees/reports', moduleId: 'employees', titleKey: 'nav.employeesReports', permission: { module: 'employees', page: 'reports' } },

  // 3 Organizasyon
  { key: 'organization.branches', route: '/organization/branches', moduleId: 'organization', titleKey: 'studio.branches', permission: { module: 'management', page: 'branches' } },
  { key: 'organization.branches.detail', route: '/organization/branches/:id', moduleId: 'organization', titleKey: 'studio.branches', permission: { module: 'management', page: 'branches' } },
  { key: 'organization.departments', route: '/organization/departments', moduleId: 'organization', titleKey: 'nav.employeesDepartments', permission: { module: 'employees', page: 'departments' } },
  { key: 'organization.positions', route: '/organization/positions', moduleId: 'organization', titleKey: 'nav.employeesPositions', permission: { module: 'employees', page: 'positions' } },
  { key: 'organization.chart', route: '/organization/chart', moduleId: 'organization', titleKey: 'nav.employeesOrganization', permission: { module: 'employees', page: 'organization' } },

  // 4 İşe Alım
  { key: 'recruitment.positions', route: '/recruitment/positions', moduleId: 'recruitment', titleKey: 'nav.recruitmentPositions', permission: { module: 'recruitment', page: 'positions' } },
  { key: 'recruitment.applications', route: '/recruitment/applications', moduleId: 'recruitment', titleKey: 'nav.recruitmentApplications', permission: { module: 'recruitment', page: 'applications' } },
  { key: 'recruitment.cv_pool', route: '/recruitment/cv-pool', moduleId: 'recruitment', titleKey: 'nav.recruitmentCvPool', permission: { module: 'recruitment', page: 'cv_pool' } },
  { key: 'recruitment.interviews', route: '/recruitment/interviews', moduleId: 'recruitment', titleKey: 'nav.recruitmentInterviews', permission: { module: 'recruitment', page: 'applications' } },
  { key: 'recruitment.reports', route: '/recruitment/reports', moduleId: 'recruitment', titleKey: 'nav.recruitmentReports', permission: { module: 'recruitment', page: 'applications' } },
  { key: 'recruitment.custom_fields', route: '/recruitment/custom-fields', moduleId: 'recruitment', titleKey: 'nav.recruitmentCustomFields', permission: { module: 'recruitment', page: 'custom_fields' } },

  // 5 Oryantasyon & Çıkış
  { key: 'onboarding.processes', route: '/onboarding', moduleId: 'onboarding', titleKey: 'nav.onboardingProcesses', permission: { module: 'onboarding', page: 'processes' } },
  { key: 'onboarding.templates', route: '/onboarding/templates', moduleId: 'onboarding', titleKey: 'nav.onboardingTemplates', permission: { module: 'onboarding', page: 'templates' } },
  { key: 'onboarding.process.detail', route: '/onboarding/processes/:id', moduleId: 'onboarding', titleKey: 'nav.onboardingProcesses', permission: { module: 'onboarding', page: 'processes' } },

  // 6 PDKS
  { key: 'pdks.attendance.list', route: '/pdks', moduleId: 'pdks', titleKey: 'nav.timesheetAttendance', permission: { module: 'timesheet', page: 'attendance' } },
  { key: 'pdks.timesheet.list', route: '/pdks/reports', moduleId: 'pdks', titleKey: 'nav.timesheetReports', permission: { module: 'timesheet', page: 'attendance' } },
  { key: 'pdks.shifts.list', route: '/pdks/shifts', moduleId: 'pdks', titleKey: 'nav.timesheetShifts', permission: { module: 'timesheet', page: 'shifts' } },
  { key: 'pdks.shift_assignments.list', route: '/pdks/shift-assignments', moduleId: 'pdks', titleKey: 'nav.timesheetShiftAssign', permission: { module: 'timesheet', page: 'shifts' } },
  { key: 'pdks.kiosk', route: '/pdks/kiosk', moduleId: 'pdks', titleKey: 'nav.timesheetKiosk', permission: { module: 'timesheet', page: 'kiosk' } },

  // 7 İzin
  { key: 'leaves.requests.list', route: '/leaves', moduleId: 'leaves', titleKey: 'nav.leavesRequests', permission: { module: 'leaves', page: 'requests' } },
  { key: 'leaves.balances.list', route: '/leaves/balances', moduleId: 'leaves', titleKey: 'nav.leavesBalances', permission: { module: 'leaves', page: 'balances' } },
  { key: 'leaves.calendar', route: '/leaves/calendar', moduleId: 'leaves', titleKey: 'nav.leavesCalendar', permission: { module: 'leaves', page: 'calendar' } },
  { key: 'leaves.types.list', route: '/leaves/types', moduleId: 'leaves', titleKey: 'nav.leavesTypes', permission: { module: 'leaves', page: 'types' } },
  { key: 'leaves.holidays.list', route: '/leaves/holidays', moduleId: 'leaves', titleKey: 'nav.leavesHolidays', permission: { module: 'leaves', page: 'holidays' } },
  { key: 'leaves.policies.list', route: '/leaves/policies', moduleId: 'leaves', titleKey: 'nav.leavesPolicies', permission: { module: 'leaves', page: 'accrual_policies' } },
  { key: 'leaves.reports', route: '/leaves/reports', moduleId: 'leaves', titleKey: 'nav.leavesReports', permission: { module: 'leaves', page: 'requests' } },
  { key: 'leaves.custom_fields', route: '/leaves/custom-fields', moduleId: 'leaves', titleKey: 'nav.leavesCustomFields', permission: { module: 'leaves', page: 'custom_fields' } },

  // 8 Ücret & Ödemeler
  { key: 'payroll.payslips.list', route: '/payroll/payslips', moduleId: 'payroll', titleKey: 'nav.payslipsAdmin', permission: { module: 'payroll', page: 'payslips' } },
  { key: 'payroll.expenses.list', route: '/payroll/expenses', moduleId: 'payroll', titleKey: 'nav.expensesQueue', permission: { module: 'expenses', page: 'claims' } },
  { key: 'payroll.expenses.all', route: '/payroll/expenses/all', moduleId: 'payroll', titleKey: 'nav.expensesAll', permission: { module: 'expenses', page: 'claims' } },
  { key: 'payroll.expenses.categories', route: '/payroll/expenses/categories', moduleId: 'payroll', titleKey: 'nav.expensesCategories', permission: { module: 'expenses', page: 'categories' } },
  { key: 'payroll.salary_bands', route: '/payroll/salary-bands', moduleId: 'payroll', titleKey: 'nav.employeesSalaryBands', permission: { module: 'employees', page: 'salary' } },
  { key: 'payroll.salary_reviews', route: '/payroll/salary-reviews', moduleId: 'payroll', titleKey: 'nav.employeesSalaryReviews', permission: { module: 'employees', page: 'salary' } },
  { key: 'payroll.salary_reviews.detail', route: '/payroll/salary-reviews/:id', moduleId: 'payroll', titleKey: 'nav.employeesSalaryReviews', permission: { module: 'employees', page: 'salary' } },

  // 9 Performans
  { key: 'performance.reviews.list', route: '/performance', moduleId: 'performance', titleKey: 'nav.performanceReviews', permission: { module: 'performance', page: 'reviews' } },
  { key: 'performance.periods.list', route: '/performance/periods', moduleId: 'performance', titleKey: 'nav.performancePeriods', permission: { module: 'performance', page: 'periods' } },
  { key: 'performance.criteria.list', route: '/performance/criteria', moduleId: 'performance', titleKey: 'nav.performanceCriteria', permission: { module: 'performance', page: 'criteria' } },
  { key: 'performance.reviews.detail', route: '/performance/reviews/:id', moduleId: 'performance', titleKey: 'nav.performanceReviews', permission: { module: 'performance', page: 'reviews' } },
  { key: 'performance.custom_fields', route: '/performance/custom-fields', moduleId: 'performance', titleKey: 'nav.performanceCustomFields', permission: { module: 'performance', page: 'custom_fields' } },

  // 10 Eğitim
  { key: 'training.list', route: '/training', moduleId: 'training', titleKey: 'nav.trainingList', permission: { module: 'training', page: 'list' } },
  { key: 'training.sessions.list', route: '/training/sessions', moduleId: 'training', titleKey: 'nav.trainingSessions', permission: { module: 'training', page: 'sessions' } },
  { key: 'training.custom_fields', route: '/training/custom-fields', moduleId: 'training', titleKey: 'nav.trainingCustomFields', permission: { module: 'training', page: 'custom_fields' } },

  // 12 Varlık & Doküman
  { key: 'assets.list', route: '/assets', moduleId: 'assets-documents', titleKey: 'nav.assetsList', permission: { module: 'assets', page: 'list' } },
  { key: 'assets.categories', route: '/assets/categories', moduleId: 'assets-documents', titleKey: 'nav.assetsCategories', permission: { module: 'assets', page: 'categories' } },
  { key: 'assets.detail', route: '/assets/:id', moduleId: 'assets-documents', titleKey: 'nav.assetsList', permission: { module: 'assets', page: 'list' } },
  { key: 'assets.custom_fields', route: '/assets/custom-fields', moduleId: 'assets-documents', titleKey: 'nav.assetsCustomFields', permission: { module: 'assets', page: 'custom_fields' } },
  { key: 'documents.list', route: '/documents', moduleId: 'assets-documents', titleKey: 'nav.documentsList', permission: { module: 'documents', page: 'list' } },
  { key: 'documents.categories', route: '/documents/categories', moduleId: 'assets-documents', titleKey: 'nav.documentsCategories', permission: { module: 'documents', page: 'categories' } },
  { key: 'documents.reports', route: '/documents/reports', moduleId: 'assets-documents', titleKey: 'nav.documentsReports', permission: { module: 'documents', page: 'reports' } },
  { key: 'documents.detail', route: '/documents/:id', moduleId: 'assets-documents', titleKey: 'nav.documentsList', permission: { module: 'documents', page: 'list' } },
  { key: 'documents.custom_fields', route: '/documents/custom-fields', moduleId: 'assets-documents', titleKey: 'nav.documentsCustomFields', permission: { module: 'documents', page: 'custom_fields' } },

  // 13 İletişim
  { key: 'communication.announcements', route: '/communication/announcements', moduleId: 'communication', titleKey: 'nav.announcements', permission: { module: 'announcements', page: 'list' } },
  { key: 'communication.surveys', route: '/communication/surveys', moduleId: 'communication', titleKey: 'nav.surveysList', permission: { module: 'surveys', page: 'list' } },

  // 14 Analitik
  { key: 'analytics.dashboards.list', route: '/dashboards', moduleId: 'analytics', titleKey: 'nav.dashboards', permission: { module: 'reports', page: 'dashboards' } },
  { key: 'analytics.dashboards.view', route: '/dashboards/:id', moduleId: 'analytics', titleKey: 'nav.dashboards', permission: { module: 'reports', page: 'dashboards' } },
  { key: 'analytics.reports.list', route: '/reports', moduleId: 'analytics', titleKey: 'nav.reportEngine', permission: { module: 'reports', page: 'definitions' } },
  { key: 'analytics.reports.builder', route: '/reports/:id', moduleId: 'analytics', titleKey: 'nav.reportEngine', permission: { module: 'reports', page: 'definitions' } },
  { key: 'analytics.measures.list', route: '/reports/measures', moduleId: 'analytics', titleKey: 'nav.reportMeasures', permission: { module: 'reports', page: 'measures' } },
  { key: 'analytics.schedules.list', route: '/reports/schedules', moduleId: 'analytics', titleKey: 'nav.reportSchedules', permission: { module: 'reports', page: 'schedules' } },
  { key: 'analytics.overview', route: '/analytics', moduleId: 'analytics', titleKey: 'nav.analyticsReports', permission: { module: 'analytics', page: 'reports' } },

  // Pin: Ayarlar
  { key: 'account.profile', route: '/account/profile', moduleId: 'account', titleKey: 'account.profile' },
  { key: 'account.security', route: '/account/security', moduleId: 'account', titleKey: 'account.security' },
  { key: 'account.preferences', route: '/account/preferences', moduleId: 'account', titleKey: 'account.preferences' },

  // Pin: Yönetim
  { key: 'management.settings', route: '/settings', moduleId: 'management', titleKey: 'studio.companySettings', permission: { module: 'management', page: 'settings' } },
  { key: 'settings.registry', route: '/settings/registry', moduleId: 'management', titleKey: 'studio.settingsRegistry', permission: { module: 'settings', page: 'values' } },
  { key: 'settings.report_privacy', route: '/settings/report-privacy', moduleId: 'management', titleKey: 'reportPrivacy.title', permission: { module: 'management', page: 'settings' } },
  { key: 'management.kvkk', route: '/kvkk', moduleId: 'management', titleKey: 'kvkk.title', permission: { module: 'management', page: 'kvkk' } },
  { key: 'kvkk.requests', route: '/kvkk', moduleId: 'management', titleKey: 'kvkk.title', permission: { module: 'management', page: 'kvkk' } },
  { key: 'kvkk.destruction', route: '/kvkk', moduleId: 'management', titleKey: 'kvkk.title', permission: { module: 'management', page: 'kvkk' } },
  { key: 'management.webhooks', route: '/webhooks', moduleId: 'management', titleKey: 'studio.webhooks', permission: { module: 'management', page: 'webhooks' } },
  { key: 'management.users', route: '/users', moduleId: 'management', titleKey: 'studio.users', permission: { module: 'management', page: 'users' } },
  { key: 'management.roles', route: '/roles', moduleId: 'management', titleKey: 'studio.roles', permission: { module: 'management', page: 'roles' } },
  { key: 'management.audit_logs', route: '/audit-logs', moduleId: 'management', titleKey: 'studio.auditLogs', permission: { module: 'management', page: 'audit_logs' } },
  { key: 'management.lookups', route: '/lookups', moduleId: 'management', titleKey: 'studio.lookups', permission: { module: 'management', page: 'lookups' } },
  { key: 'management.custom_fields', route: '/settings/custom-fields', moduleId: 'management', titleKey: 'studio.customFields', permission: { module: 'management', page: 'custom_fields' } },
  { key: 'management.forms', route: '/settings/forms', moduleId: 'management', titleKey: 'studio.formLayouts', permission: { module: 'management', page: 'forms' } },
  { key: 'management.workflows', route: '/settings/workflows', moduleId: 'management', titleKey: 'studio.workflows', permission: { module: 'management', page: 'workflows' } },
  { key: 'management.notifications', route: '/settings/notification-templates', moduleId: 'management', titleKey: 'studio.notificationTemplates', permission: { module: 'management', page: 'notifications' } },
];

const byKey = new Map(PAGE_REGISTRY.map((e) => [e.key, e]));

export function getPageByKey(key: string): PageRegistryEntry | undefined {
  return byKey.get(key);
}

export function getPageKeyForPath(pathname: string): string | undefined {
  const exact = PAGE_REGISTRY.find((e) => e.route === pathname);
  if (exact) return exact.key;

  const parametric = PAGE_REGISTRY.filter((e) => e.route.includes(':'))
    .map((e) => {
      const pattern = e.route.replace(/:[^/]+/g, '[^/]+');
      const re = new RegExp(`^${pattern}$`);
      return re.test(pathname) ? e : null;
    })
    .filter((e): e is PageRegistryEntry => e !== null)
    .sort((a, b) => b.route.length - a.route.length);

  return parametric[0]?.key;
}

export function assertPageRegistryIntegrity(): { ok: boolean; duplicateKeys: string[]; emptyRoutes: string[] } {
  const seen = new Set<string>();
  const duplicateKeys: string[] = [];
  const emptyRoutes: string[] = [];

  for (const e of PAGE_REGISTRY) {
    if (seen.has(e.key)) duplicateKeys.push(e.key);
    seen.add(e.key);
    if (!e.route) emptyRoutes.push(e.key);
  }

  return { ok: duplicateKeys.length === 0 && emptyRoutes.length === 0, duplicateKeys, emptyRoutes };
}

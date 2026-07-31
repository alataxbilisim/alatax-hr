/**
 * Portal korumalı rota kaydı — App.tsx ve smoke guard aynı kaynağı kullanır.
 * Yeni sayfa eklenince buraya + App.tsx'e eklenmeli; smoke CI kırılır.
 */
export type PortalProtectedRoute = {
  path: string;
  /** pages/ altındaki dosya adı (uzantısız) */
  pageModule: string;
};

export const PORTAL_PROTECTED_ROUTES: PortalProtectedRoute[] = [
  { path: '/dashboard', pageModule: 'DashboardPage' },
  { path: '/profile', pageModule: 'ProfilePage' },
  { path: '/my-data', pageModule: 'MyDataPage' },
  { path: '/leaves', pageModule: 'LeavesPage' },
  { path: '/leaves/form-engine', pageModule: 'LeaveFormEnginePage' },
  { path: '/documents', pageModule: 'DocumentsPage' },
  { path: '/payslips', pageModule: 'PayslipsPage' },
  { path: '/salary', pageModule: 'SalaryPage' },
  { path: '/training', pageModule: 'TrainingPage' },
  { path: '/performance', pageModule: 'PerformancePage' },
  { path: '/surveys', pageModule: 'SurveysPage' },
  { path: '/timesheet', pageModule: 'TimesheetPage' },
  { path: '/timesheet/qr', pageModule: 'PortalQrScanPage' },
  { path: '/expenses', pageModule: 'ExpensesPage' },
  { path: '/expenses/form-engine', pageModule: 'ExpenseFormEnginePage' },
  { path: '/announcements', pageModule: 'AnnouncementsPage' },
  { path: '/requests', pageModule: 'RequestsPage' },
];

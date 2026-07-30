/**
 * E0 — Eski URL → yeni URL kalıcı yönlendirmeleri.
 * Parametreli path'ler :param ile; App.tsx'te <Navigate replace> ile uygulanır.
 */
export interface LegacyRedirect {
  from: string;
  to: string;
  /** true ise :id gibi dinamik segment taşınır */
  dynamic?: boolean;
}

export const LEGACY_REDIRECTS: LegacyRedirect[] = [
  // PDKS
  { from: '/attendance', to: '/pdks' },
  { from: '/attendance/shifts', to: '/pdks/shifts' },
  { from: '/attendance/shift-assignments', to: '/pdks/shift-assignments' },
  { from: '/attendance/reports', to: '/pdks/reports' },
  { from: '/attendance/kiosk', to: '/pdks/kiosk' },

  // Organizasyon
  { from: '/employees/departments', to: '/organization/departments' },
  { from: '/employees/positions', to: '/organization/positions' },
  { from: '/employees/organization', to: '/organization/chart' },
  { from: '/branches', to: '/organization/branches' },
  { from: '/branches/:id', to: '/organization/branches/:id', dynamic: true },

  // Ücret & Ödemeler
  { from: '/payslips', to: '/payroll/payslips' },
  { from: '/expenses', to: '/payroll/expenses' },
  { from: '/expenses/all', to: '/payroll/expenses/all' },
  { from: '/expenses/categories', to: '/payroll/expenses/categories' },
  { from: '/employees/salary-bands', to: '/payroll/salary-bands' },
  { from: '/employees/salary-reviews', to: '/payroll/salary-reviews' },
  { from: '/employees/salary-reviews/:id', to: '/payroll/salary-reviews/:id', dynamic: true },

  // İletişim
  { from: '/announcements', to: '/communication/announcements' },
  { from: '/surveys', to: '/communication/surveys' },
];

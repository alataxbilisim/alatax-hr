/**
 * backend/database/seeders/E2eSeeder.php ile aynı sabitler — birini değiştiren ikisini de günceller.
 * Şifre ve TOTP anahtarı yalnız izole `dobedan_hr_e2e` veritabanına özgüdür.
 */
export const PASSWORD = 'E2e-Parola-2026!';
export const TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
export const ANNUAL_LEAVE_DAYS = 14;

export const URLS = {
  api: process.env.E2E_API_URL ?? 'http://localhost:8100/api/v1',
  superadmin: 'http://localhost:4101',
  company: 'http://localhost:4102',
  portal: 'http://localhost:4103',
} as const;

export const USERS = {
  superAdmin: { email: 'superadmin@e2e.test', name: 'E2E Süper Yönetici' },
  adminA: { email: 'admin@a.e2e.test', name: 'A Yöneticisi' },
  employeeA: { email: 'personel@a.e2e.test', name: 'A Personeli' },
  twoFactorA: { email: '2fa@a.e2e.test', name: 'A İki Adımlı' },
  adminB: { email: 'admin@b.e2e.test', name: 'B Yöneticisi' },
} as const;

export const COMPANIES = {
  a: { slug: 'e2e-sirket-a', name: 'E2E Şirket A' },
  b: { slug: 'e2e-sirket-b', name: 'E2E Şirket B' },
} as const;

/** B şirketinde, A kullanıcılarının asla görmemesi gereken personel. */
export const HIDDEN_EMPLOYEE_B = { code: 'E2E-B-002', name: 'B Gizli Personel' };

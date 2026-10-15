import { expect, test, type Page } from '@playwright/test';
import { login, logout } from '../fixtures/auth';
import { ANNUAL_LEAVE_DAYS, URLS, USERS } from '../fixtures/data';

const ANNUAL = 'Yıllık İzin';

/** Bakiye yılı içinde kalan, bugünden sonraki iki günlük aralık (portal start_date >= today ister). */
function leaveRange(): { start: string; end: string; days: number } {
  const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const today = new Date();
  const start = new Date(today);
  start.setDate(today.getDate() + 1);
  const end = new Date(start);
  end.setDate(start.getDate() + 1);
  if (end.getFullYear() !== today.getFullYear()) {
    return { start: iso(today), end: iso(today), days: 1 };
  }
  return { start: iso(start), end: iso(end), days: 2 };
}

function annualBalance(page: Page) {
  return page.locator('.balance-card', { hasText: ANNUAL }).locator('.balance-value');
}

test('izin akışı: portal talep → company onay → bakiye düşer → portal onaylandı', async ({ page }) => {
  const range = leaveRange();
  const reason = `E2E izin ${Date.now()}`;

  await login(page, 'portal', USERS.employeeA.email);
  await page.goto(`${URLS.portal}/leaves`);
  await expect(annualBalance(page)).toHaveText(new RegExp(`^${ANNUAL_LEAVE_DAYS}(\\.0+)?$`));

  await page.locator('.page-header-actions').getByRole('button', { name: 'Yeni İzin Talebi' }).click();
  await page.getByRole('combobox', { name: 'İzin türü' }).click();
  await page.getByRole('option', { name: ANNUAL, exact: true }).click();
  const dates = page.locator('.modal-mobile input[type="date"]');
  await dates.nth(0).fill(range.start);
  await dates.nth(1).fill(range.end);
  await page.getByPlaceholder('İzin sebebinizi yazın (opsiyonel)').fill(reason);
  await page.getByRole('button', { name: 'Talep Oluştur' }).click();

  const portalRow = page.locator('.desktop-only tbody tr').first();
  await expect(portalRow.locator('td').nth(3)).toHaveText(new RegExp(`^${range.days}(\\.00)? gün$`));
  await expect(portalRow).toContainText('Beklemede');
  await logout(page, 'portal');

  await login(page, 'company', USERS.adminA.email);
  await page.goto(`${URLS.company}/leaves`);
  const pendingRow = page.locator('tr', { hasText: USERS.employeeA.name }).filter({ has: page.getByTitle('Onayla') });
  await expect(pendingRow).toHaveCount(1);
  await pendingRow.getByTitle('Onayla').click();
  await expect(page.getByText('İzin talebi onaylandı')).toBeVisible();
  await expect(page.locator('tr', { hasText: USERS.employeeA.name }).getByTitle('Onayla')).toHaveCount(0);
  await logout(page, 'company');

  await login(page, 'portal', USERS.employeeA.email);
  await page.goto(`${URLS.portal}/leaves`);
  await expect(page.locator('.desktop-only tbody tr').first()).toContainText('Onaylandı');
  await expect(annualBalance(page)).toHaveText(new RegExp(`^${ANNUAL_LEAVE_DAYS - range.days}(\\.0+)?$`));
});

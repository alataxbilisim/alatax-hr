import { expect, test, type APIRequestContext } from '@playwright/test';
import { apiToken, login } from '../fixtures/auth';
import { COMPANIES, URLS, USERS } from '../fixtures/data';

const MODULE_SLUG = 'training';
const MENU_LABEL = 'Eğitim';

type Envelope<T> = { data: T };

async function adminGet<T>(request: APIRequestContext, token: string, path: string, params?: Record<string, string>): Promise<T> {
  const response = await request.get(`${URLS.api}${path}`, {
    params,
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
  });
  expect(response.ok(), `${path} ${response.status()}`).toBeTruthy();
  return ((await response.json()) as Envelope<T>).data;
}

/**
 * Superadmin panelinde firma bazlı modül aç/kapa ekranı yok (yalnız global modül listesi);
 * aç/kapa, panelin kullandığı superadmin API'si (POST /admin/companies/{id}/modules) ile yapılır.
 */
async function setCompanyModule(request: APIRequestContext, token: string, companyId: number, moduleId: number, active: boolean) {
  const response = await request.post(`${URLS.api}/admin/companies/${companyId}/modules`, {
    data: { modules: [{ id: moduleId, is_active: active }] },
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
  });
  expect(response.ok(), `modules sync ${response.status()}`).toBeTruthy();
}

test('superadmin: firma listesi ve modül kapatınca company menüsü değişir', async ({ page, browser }) => {
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await login(adminPage, 'superadmin', USERS.superAdmin.email);
  await adminPage.goto(`${URLS.superadmin}/companies`);
  await expect(adminPage.getByText(COMPANIES.a.name, { exact: true })).toBeVisible();
  await expect(adminPage.getByText(COMPANIES.b.name, { exact: true })).toBeVisible();

  const token = await apiToken(adminPage, USERS.superAdmin.email);
  const companies = await adminGet<Array<{ id: number; slug: string }>>(adminPage.request, token, '/admin/companies', { search: COMPANIES.a.name });
  const company = companies.find((c) => c.slug === COMPANIES.a.slug);
  expect(company).toBeDefined();
  const modules = await adminGet<Array<{ id: number; slug: string }>>(adminPage.request, token, '/admin/modules');
  const module = modules.find((m) => m.slug === MODULE_SLUG);
  expect(module).toBeDefined();
  const companyId = (company as { id: number }).id;
  const moduleId = (module as { id: number }).id;

  await login(page, 'company', USERS.adminA.email);
  const menuItem = page.locator('.module-rail').getByTitle(MENU_LABEL, { exact: true });
  await expect(menuItem).toBeVisible();

  try {
    await setCompanyModule(adminPage.request, token, companyId, moduleId, false);
    await page.reload();
    await expect(page.locator('.module-rail .rail-item').first()).toBeVisible();
    await expect(menuItem).toHaveCount(0);
  } finally {
    await setCompanyModule(adminPage.request, token, companyId, moduleId, true);
  }

  await page.reload();
  await expect(menuItem).toBeVisible();
  await adminContext.close();
});

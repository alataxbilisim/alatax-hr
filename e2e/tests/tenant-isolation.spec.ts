import { expect, test, type APIRequestContext } from '@playwright/test';
import { apiToken, login } from '../fixtures/auth';
import { HIDDEN_EMPLOYEE_B, URLS, USERS } from '../fixtures/data';

async function hiddenEmployeeId(request: APIRequestContext, token: string): Promise<number> {
  const response = await request.get(`${URLS.api}/employees`, {
    params: { search: HIDDEN_EMPLOYEE_B.code },
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
  });
  expect(response.ok()).toBeTruthy();
  const body = (await response.json()) as { data: Array<{ id: number; employee_code: string }> };
  const match = body.data.find((e) => e.employee_code === HIDDEN_EMPLOYEE_B.code);
  expect(match, 'B şirketi kendi personelini görmeli').toBeDefined();
  return (match as { id: number }).id;
}

test('A şirketi kullanıcısı B şirketinin personel URL\'sini açamaz', async ({ page }) => {
  const tokenB = await apiToken(page, USERS.adminB.email);
  const id = await hiddenEmployeeId(page.request, tokenB);

  const tokenA = await apiToken(page, USERS.adminA.email);
  const direct = await page.request.get(`${URLS.api}/employees/${id}`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${tokenA}` },
  });
  expect([403, 404]).toContain(direct.status());

  await login(page, 'company', USERS.adminA.email);

  for (const path of [`/employees/${id}`, `/employees/${id}/edit`]) {
    const blocked = page.waitForResponse(
      (r) => new URL(r.url()).pathname.endsWith(`/api/v1/employees/${id}`) && r.request().method() === 'GET'
    );
    await page.goto(`${URLS.company}${path}`);
    expect([403, 404]).toContain((await blocked).status());
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(HIDDEN_EMPLOYEE_B.name)).toHaveCount(0);
    await expect(page.locator('input[value="' + HIDDEN_EMPLOYEE_B.code + '"]')).toHaveCount(0);
  }
});

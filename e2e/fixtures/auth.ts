import { expect, type Page } from '@playwright/test';
import { PASSWORD, TOTP_SECRET, URLS } from './data';
import { totp } from './totp';

export type AppName = 'superadmin' | 'company' | 'portal';

export async function submitLogin(page: Page, app: AppName, email: string, password = PASSWORD): Promise<void> {
  await page.goto(`${URLS[app]}/login`);
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button[type="submit"]').click();
}

export async function login(page: Page, app: AppName, email: string, password = PASSWORD): Promise<void> {
  await submitLogin(page, app, email, password);
  await expect(page).toHaveURL(new RegExp(`^${URLS[app]}/dashboard`));
}

/** Şifre adımından sonra TOTP doğrulaması (2FA'lı kullanıcı). */
export async function completeTwoFactor(page: Page): Promise<void> {
  await expect(page.getByRole('heading', { name: 'İki Adımlı Doğrulama' })).toBeVisible();
  await page.locator('#totp-code').fill(totp(TOTP_SECRET));
  await page.getByRole('button', { name: 'Doğrula' }).click();
}

export async function logout(page: Page, app: AppName): Promise<void> {
  if (app === 'company') {
    await page.locator('.user-btn').click();
    await page.locator('.dropdown-item.danger').click();
  } else if (app === 'portal') {
    await page.getByRole('button', { name: /çıkış/i }).first().click();
  } else {
    await page.getByTitle('Çıkış Yap').click();
  }
  await expect(page).toHaveURL(new RegExp(`^${URLS[app]}/login`));
  expect(await page.evaluate(() => localStorage.getItem('token'))).toBeNull();
}

/** API'den Bearer token (UI dışı doğrulamalar için). */
export async function apiToken(page: Page, email: string, portal = false): Promise<string> {
  const response = await page.request.post(`${URLS.api}/auth/login`, {
    data: { email, password: PASSWORD, ...(portal ? { portal_login: true } : {}) },
    headers: { Accept: 'application/json' },
  });
  expect(response.ok()).toBeTruthy();
  const body = await response.json();
  return body.data.token as string;
}

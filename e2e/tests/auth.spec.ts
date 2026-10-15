import { expect, test } from '@playwright/test';
import { completeTwoFactor, login, logout, submitLogin, type AppName } from '../fixtures/auth';
import { URLS, USERS } from '../fixtures/data';

const ACCOUNTS: Record<AppName, { email: string; name: string }> = {
  superadmin: USERS.superAdmin,
  company: USERS.adminA,
  portal: USERS.employeeA,
};

for (const app of Object.keys(ACCOUNTS) as AppName[]) {
  test.describe(`${app} kimlik doğrulama`, () => {
    test('giriş ve çıkış', async ({ page }) => {
      await login(page, app, ACCOUNTS[app].email);
      await expect(page.getByText(ACCOUNTS[app].name.split(' ')[0]).first()).toBeVisible();

      await logout(page, app);

      await page.goto(`${URLS[app]}/dashboard`);
      await expect(page).toHaveURL(new RegExp(`^${URLS[app]}/login`));
    });

    test('yanlış şifre reddedilir', async ({ page }) => {
      await submitLogin(page, app, ACCOUNTS[app].email, 'yanlis-sifre-123');

      await expect(page.getByText('E-posta veya şifre hatalı')).toBeVisible();
      await expect(page).toHaveURL(new RegExp(`^${URLS[app]}/login`));
      expect(await page.evaluate(() => localStorage.getItem('token'))).toBeNull();
    });
  });
}

test('2FA aktif kullanıcı şifre + TOTP ile company paneline girer', async ({ page }) => {
  await submitLogin(page, 'company', USERS.twoFactorA.email);

  await completeTwoFactor(page);

  await expect(page).toHaveURL(new RegExp(`^${URLS.company}/dashboard`));
  await logout(page, 'company');
});

test('2FA: yanlış kod oturum açmaz', async ({ page }) => {
  await submitLogin(page, 'company', USERS.twoFactorA.email);
  await expect(page.getByRole('heading', { name: 'İki Adımlı Doğrulama' })).toBeVisible();

  await page.locator('#totp-code').fill('000000');
  await page.getByRole('button', { name: 'Doğrula' }).click();

  await expect(page.getByText('Geçersiz doğrulama kodu').first()).toBeVisible();
  await expect(page).toHaveURL(new RegExp(`^${URLS.company}/login`));
});

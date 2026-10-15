import { expect, test } from '@playwright/test';
import { login } from '../fixtures/auth';
import { URLS, USERS } from '../fixtures/data';

test('company: personel oluştur → listede gör → düzenle', async ({ page }) => {
  const suffix = Date.now().toString().slice(-6);
  const code = `E2E-N-${suffix}`;
  const name = `Yeni Personel ${suffix}`;
  const renamed = `Düzenlenmiş Personel ${suffix}`;

  await login(page, 'company', USERS.adminA.email);

  await page.goto(`${URLS.company}/employees/new`);
  await expect(page.getByRole('heading', { name: 'Yeni Personel' })).toBeVisible();
  await page.getByPlaceholder('PRS001').fill(code);
  await page.getByPlaceholder('Ahmet Yılmaz').fill(name);
  await page.locator('form button[type="submit"]').click();

  await expect(page.getByText('Personel başarıyla oluşturuldu')).toBeVisible();
  await expect(page).toHaveURL(`${URLS.company}/employees`);

  await page.getByPlaceholder('Ara (ad, sicil, pozisyon...)').fill(code);
  const row = page.locator('tr', { hasText: code });
  await expect(row).toContainText(name);

  await row.getByRole('button', { name: 'Düzenle', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Personel Düzenle' })).toBeVisible();
  const nameInput = page.getByPlaceholder('Ahmet Yılmaz');
  await expect(nameInput).toHaveValue(name);
  await nameInput.fill(renamed);
  await page.locator('form button[type="submit"]').click();

  await expect(page.getByText('Personel başarıyla güncellendi')).toBeVisible();
  await page.getByPlaceholder('Ara (ad, sicil, pozisyon...)').fill(code);
  await expect(page.locator('tr', { hasText: code })).toContainText(renamed);
});

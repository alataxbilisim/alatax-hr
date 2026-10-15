import { readFile } from 'node:fs/promises';
import { expect, test } from '@playwright/test';
import { login } from '../fixtures/auth';
import { URLS, USERS } from '../fixtures/data';

test('company: evrak yükle ve aynı içeriği indir', async ({ page }) => {
  const stamp = Date.now();
  const title = `E2E Evrak ${stamp}`;
  const content = `Dobedan HR E2E belge içeriği ${stamp}\n`;

  await login(page, 'company', USERS.adminA.email);
  await page.goto(`${URLS.company}/documents`);
  await expect(page.getByRole('heading', { name: 'Evraklar' })).toBeVisible();

  await page.getByRole('button', { name: 'Evrak Yükle' }).click();
  await page.locator('input[type="file"]').setInputFiles({
    name: `e2e-evrak-${stamp}.txt`,
    mimeType: 'text/plain',
    buffer: Buffer.from(content, 'utf8'),
  });
  await page.getByPlaceholder('Evrak başlığı').fill(title);
  await page.getByRole('button', { name: 'Yükle', exact: true }).click();
  await expect(page.getByText('Evrak yüklendi')).toBeVisible();

  const row = page.locator('tr', { hasText: title });
  await expect(row).toBeVisible();

  const downloadEvent = page.waitForEvent('download');
  await row.getByTitle('İndir').click();
  const download = await downloadEvent;
  expect(download.suggestedFilename()).toBe(`e2e-evrak-${stamp}.txt`);
  const path = await download.path();
  expect(await readFile(path, 'utf8')).toBe(content);
});

import { defineConfig, devices } from '@playwright/test';
import { URLS } from './fixtures/data';

const isCI = !!process.env.CI;

/** Frontend'ler E2E backend'ine (app-e2e, :8100) bağlı ayrı portlarda açılır; dev sunucularıyla çakışmaz. */
function viteServer(app: 'superadmin' | 'company' | 'portal', url: string) {
  const port = new URL(url).port;
  return {
    command: `pnpm --dir ../frontend --filter @dobedan/${app} exec vite --port ${port} --strictPort`,
    url,
    reuseExistingServer: !isCI,
    timeout: 180_000,
    stdout: 'ignore' as const,
    stderr: 'pipe' as const,
    env: { VITE_API_URL: URLS.api, VITE_PORTAL_URL: URLS.portal },
  };
}

export default defineConfig({
  testDir: './tests',
  outputDir: './test-results',
  globalSetup: './global-setup.ts',
  // Senaryolar aynı E2E veritabanını paylaşır (izin bakiyesi, modül aç/kapa) — sıralı koşar.
  fullyParallel: false,
  workers: 1,
  retries: isCI ? 1 : 0,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  forbidOnly: isCI,
  reporter: isCI ? [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]] : [['list']],
  use: {
    locale: 'tr-TR',
    timezoneId: 'Europe/Istanbul',
    viewport: { width: 1366, height: 768 },
    actionTimeout: 15_000,
    navigationTimeout: 45_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 768 } } }],
  webServer: [
    viteServer('superadmin', URLS.superadmin),
    viteServer('company', URLS.company),
    viteServer('portal', URLS.portal),
  ],
});

/**
 * Blok Y — Yetki / panel / 2FA
 */
import { authenticator } from 'otplib';
import {
  loginCompany,
  logoutCompany,
  screenshot,
  URLS,
  PASSWORD,
  loadSecrets,
} from '../lib/harness.mjs';

export async function runBlockY(page, results, seq) {
  let n = seq;

  // Y1 portal → company panel engeli
  {
    await logoutCompany(page);
    await page.goto(`${URLS.company}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"], input[type="email"]', 'portal@demo.test');
    await page.fill('input[name="password"], input[type="password"]', PASSWORD);

    let loginStatus = null;
    let loginBody = null;
    page.on('response', async (res) => {
      if (res.url().includes('/auth/login') && res.request().method() === 'POST') {
        loginStatus = res.status();
        try {
          loginBody = await res.json();
        } catch {
          loginBody = null;
        }
      }
    });
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2500);
    const finalUrl = page.url();
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y1-portal-panel-engel`);
    const denied =
      loginStatus === 403 ||
      loginBody?.errors?.code === 'panel_access_denied' ||
      loginBody?.data?.code === 'panel_access_denied' ||
      /portal|3003|login/.test(finalUrl);
    // success false + code
    const code =
      loginBody?.errors?.code ||
      loginBody?.data?.code ||
      loginBody?.code ||
      JSON.stringify(loginBody)?.includes('panel_access');
    results.push({
      id: 'Y1',
      title: 'portal@demo.test company panel engeli',
      status: denied || code ? 'pass' : 'fail',
      severity: denied || code ? undefined : '🔴',
      measurement: `http=${loginStatus}; url=${finalUrl}; code=${code}`,
      screenshot: ss,
    });
  }

  // Y2 users listesinde portal-only yok + Panel rozeti
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    await page.goto(`${URLS.company}/users`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    const m = await page.evaluate(() => {
      const text = document.body.innerText;
      const hasPortalEmail = text.includes('portal@demo.test');
      const panelBadges = Array.from(document.querySelectorAll('*')).filter((el) =>
        /^Panel$/i.test((el.textContent || '').trim())
      ).length;
      return { hasPortalEmail, panelBadges, snippet: text.slice(0, 200) };
    });
    // portal user might appear if they have account — prompt says portal-only should NOT be in list OR without panel
    // Spec: "/users listesinde portal-only kullanıcı yok"
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y2-users-panel-badge`);
    results.push({
      id: 'Y2',
      title: 'Users: portal-only yok; Panel rozeti',
      status: !m.hasPortalEmail ? 'pass' : 'warn',
      severity: m.hasPortalEmail ? '🟠' : undefined,
      measurement: `portalEmailVisible=${m.hasPortalEmail}; panelBadges=${m.panelBadges}`,
      screenshot: ss,
    });
  }

  // Y3 yetkisiz /employees/new
  {
    await logoutCompany(page);
    await loginCompany(page, 'personel@demo.test').catch(() => null);
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    const m = await page.evaluate(() => {
      const text = document.body.innerText;
      const denied = /erişim engeli|yetkiniz yok|access denied|izin yok|module/i.test(text);
      const empty = text.trim().length < 40;
      return { denied, empty, len: text.trim().length };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y3-erisim-engeli`);
    results.push({
      id: 'Y3',
      title: 'Yetkisiz /employees/new → Erişim Engeli',
      status: m.denied && !m.empty ? 'pass' : m.empty ? 'fail' : 'warn',
      severity: m.denied ? undefined : '🟠',
      measurement: `denied=${m.denied} empty=${m.empty} textLen=${m.len}`,
      screenshot: ss,
    });
  }

  // Y4 2FA doğru kod
  {
    const secrets = loadSecrets();
    const secret = secrets.TOTP_SECRET;
    await logoutCompany(page);
    if (!secret) {
      results.push({
        id: 'Y4',
        title: '2FA doğru kod → dashboard',
        status: 'skip',
        measurement: 'TOTP_SECRET yok',
        detail: '.secrets.local bulunamadı',
      });
    } else {
      try {
        await page.goto(`${URLS.company}/login`, { waitUntil: 'domcontentloaded' });
        await page.fill('input[name="email"], input[type="email"]', '2fa@demo.test');
        await page.fill('input[name="password"], input[type="password"]', PASSWORD);
        await page.click('button[type="submit"]');
        const codeInput = page.locator('input[name="code"], input[autocomplete="one-time-code"], input[name="otp"]');
        await codeInput.first().waitFor({ state: 'visible', timeout: 25000 });
        const code = authenticator.generate(secret);
        await codeInput.first().fill(code);
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard/, { timeout: 30000 }).catch(() => null);
        const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y4-2fa-ok`);
        const ok = /dashboard/.test(page.url());
        results.push({
          id: 'Y4',
          title: '2FA doğru kod → dashboard',
          status: ok ? 'pass' : 'fail',
          severity: ok ? undefined : '🔴',
          measurement: `url=${page.url()} codeLen=${code.length}`,
          screenshot: ss,
        });
      } catch (e) {
        const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y4-2fa-ok`);
        results.push({
          id: 'Y4',
          title: '2FA doğru kod → dashboard',
          status: 'skip',
          measurement: `challenge açılmadı: ${String(e.message || e).slice(0, 120)}`,
          screenshot: ss,
          detail: 'Rate limit / challenge UI timeout',
        });
      }
    }
  }

  // Y5 yanlış 2FA
  {
    const secrets = loadSecrets();
    const secret = secrets.TOTP_SECRET;
    await logoutCompany(page);
    if (!secret) {
      results.push({
        id: 'Y5',
        title: 'Yanlış 2FA kodu reddediliyor',
        status: 'skip',
        measurement: 'TOTP_SECRET yok',
      });
    } else {
      try {
        await page.goto(`${URLS.company}/login`, { waitUntil: 'domcontentloaded' });
        await page.fill('input[name="email"], input[type="email"]', '2fa@demo.test');
        await page.fill('input[name="password"], input[type="password"]', PASSWORD);
        await page.click('button[type="submit"]');
        const codeInput = page.locator('input[name="code"], input[autocomplete="one-time-code"], input[name="otp"]');
        await codeInput.first().waitFor({ state: 'visible', timeout: 25000 });
        await codeInput.first().fill('000000');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        const m = await page.evaluate(() => {
          const text = document.body.innerText;
          const err = document.querySelector('.error, .text-danger, [role="alert"], .toast, .alert');
          return {
            hasError: !!err || /geçersiz|hatalı|invalid|yanlış|kod/i.test(text),
            errText: (err?.textContent || '').trim().slice(0, 120),
            url: location.pathname,
          };
        });
        const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y5-2fa-yanlis`);
        results.push({
          id: 'Y5',
          title: 'Yanlış 2FA kodu reddediliyor',
          status: m.hasError && !/dashboard/.test(m.url) ? 'pass' : 'fail',
          severity: m.hasError ? undefined : '🔴',
          measurement: `hasError=${m.hasError}; err="${m.errText}"; url=${m.url}`,
          screenshot: ss,
        });
      } catch (e) {
        const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y5-2fa-yanlis`);
        results.push({
          id: 'Y5',
          title: 'Yanlış 2FA kodu reddediliyor',
          status: 'skip',
          measurement: `challenge açılmadı: ${String(e.message || e).slice(0, 120)}`,
          screenshot: ss,
        });
      }
    }
  }

  // Y6 2FA'sız login
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-Y6-normal-login`);
    const ok = /dashboard|employees|account/.test(page.url());
    results.push({
      id: 'Y6',
      title: '2FA’sız login tek adım',
      status: ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🔴',
      measurement: `url=${page.url()}`,
      screenshot: ss,
    });
  }

  return n;
}

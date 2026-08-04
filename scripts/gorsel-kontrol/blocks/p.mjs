/**
 * Blok P — Portal (dokunulmadı kanıtı)
 */
import { loginPortal, logoutPortal, screenshot, URLS, PASSWORD } from '../lib/harness.mjs';

export async function runBlockP(page, results, seq, context) {
  let n = seq;
  await logoutPortal(page);
  await loginPortal(page, 'portal@demo.test');
  await page.waitForTimeout(1000);

  // P1
  {
    const count = await page.locator('#header-company-selector').count();
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-P1-portal-secici-yok`);
    results.push({
      id: 'P1',
      title: 'Portal → şirket seçici YOK',
      status: count === 0 ? 'pass' : 'fail',
      severity: count === 0 ? undefined : '🔴',
      measurement: `selector eşleşme=${count}`,
      screenshot: ss,
    });
  }

  // P2
  {
    const theme = await page.evaluate(() => {
      const html = document.documentElement;
      const body = document.body;
      return (
        html.getAttribute('data-theme') ||
        html.getAttribute('data-mode') ||
        body.getAttribute('data-theme') ||
        (html.classList.contains('dark') ? 'dark' : null) ||
        localStorage.getItem('theme') ||
        'light-default'
      );
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-P2-portal-tema`);
    const ok = String(theme).toLowerCase().includes('light') || theme === 'light-default';
    results.push({
      id: 'P2',
      title: 'Portal açık tema varsayılan',
      status: ok ? 'pass' : 'warn',
      severity: ok ? undefined : '🟡',
      measurement: `tema attribute/değer="${theme}"`,
      screenshot: ss,
    });
  }

  // P3 — ana sayfalar scroll
  {
    const paths = ['/profile', '/leaves', '/expenses', '/requests', '/dashboard', '/'];
    const notes = [];
    let allOk = true;
    for (const p of paths) {
      const res = await page.goto(`${URLS.portal}${p}`, { waitUntil: 'domcontentloaded' }).catch(() => null);
      if (!res || res.status() >= 400) {
        notes.push(`${p}: skip/status`);
        continue;
      }
      await page.waitForTimeout(500);
      const m = await page.evaluate(() => ({
        scrollHeight: document.documentElement.scrollHeight,
        clientHeight: document.documentElement.clientHeight,
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
      }));
      const hOverflow = m.scrollWidth > m.clientWidth + 2;
      if (hOverflow) allOk = false;
      notes.push(`${p}: sh=${m.scrollHeight}/ch=${m.clientHeight} sw=${m.scrollWidth} hOverflow=${hOverflow}`);
    }
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-P3-portal-scroll`);
    results.push({
      id: 'P3',
      title: 'Portal sayfalar scroll + yatay taşma yok',
      status: allOk ? 'pass' : 'fail',
      severity: allOk ? undefined : '🟠',
      measurement: notes.join('; '),
      screenshot: ss,
    });
  }

  // P4 — X-Company-Id yok sayılıyor
  {
    await page.goto(`${URLS.portal}/leaves`, { waitUntil: 'networkidle' }).catch(() =>
      page.goto(`${URLS.portal}/leaves`)
    );
    const token = await page.evaluate(() => localStorage.getItem('token') || localStorage.getItem('auth_token'));
    let bodyA = null;
    let bodyB = null;
    try {
      const r1 = await page.request.get(`${URLS.api}/api/v1/portal/leaves`, {
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      });
      bodyA = await r1.text();
      const r2 = await page.request.get(`${URLS.api}/api/v1/portal/leaves`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
          'X-Company-Id': '999999',
        },
      });
      bodyB = await r2.text();
    } catch (e) {
      // portal endpoint farklı olabilir — alternatif
      try {
        const r1 = await page.request.get(`${URLS.api}/api/v1/me`, {
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        });
        bodyA = await r1.text();
        const r2 = await page.request.get(`${URLS.api}/api/v1/me`, {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'X-Company-Id': '999999',
          },
        });
        bodyB = await r2.text();
      } catch (err) {
        bodyA = String(e);
        bodyB = String(err);
      }
    }
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-P4-portal-header-ignore`);
    const same = bodyA === bodyB && bodyA != null;
    results.push({
      id: 'P4',
      title: 'Portal X-Company-Id yok sayılıyor',
      status: same ? 'pass' : 'warn',
      severity: same ? undefined : '🟠',
      measurement: `sameBody=${same}; lenA=${bodyA?.length}; lenB=${bodyB?.length}`,
      screenshot: ss,
    });
  }

  return n;
}

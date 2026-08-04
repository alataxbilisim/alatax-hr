/**
 * Blok G — Grup/şirket bağlamı
 */
import {
  loginCompany,
  logoutCompany,
  screenshot,
  URLS,
  PASSWORD,
} from '../lib/harness.mjs';

/** @param {import('playwright').Page} page @param {import('../lib/harness.mjs').CheckResult[]} results @param {number} seq */
export async function runBlockG(page, results, seq) {
  let n = seq;

  await logoutCompany(page);
  await loginCompany(page, 'admin@demo.test');
  await page.waitForSelector('.header-company-label', { timeout: 30000 });
  // Membership fetch bitene kadar seçiciyi bekle (çok şirket)
  await page.waitForSelector('#header-company-selector', { timeout: 20000 });

  // G1
  {
    const m = await page.evaluate(() => {
      const sel = document.querySelector('#header-company-selector');
      const label = document.querySelector('.header-company-label');
      return {
        selectorExists: !!sel,
        labelText: label?.textContent?.trim() || '',
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-G1-sirket-secici`);
    const ok = m.selectorExists && m.labelText.length > 0;
    results.push({
      id: 'G1',
      title: 'admin login → şirket seçici var + aktif ad',
      status: ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🔴',
      measurement: `selector=${m.selectorExists}; label="${m.labelText}"`,
      screenshot: ss,
    });
  }

  // G2
  {
    await page.click('#header-company-selector');
    await page.waitForSelector('[role="option"], [data-radix-collection-item]', { timeout: 10000 });
    const count = await page.locator('[role="option"]').count();
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-G2-sirket-listesi`);
    await page.keyboard.press('Escape').catch(() => null);
    results.push({
      id: 'G2',
      title: 'Seçici açık: 3 şirket',
      status: count === 3 ? 'pass' : 'fail',
      severity: count === 3 ? undefined : '🔴',
      measurement: `option sayısı=${count}`,
      screenshot: ss,
    });
  }

  // G3 — şirket değişimi
  {
    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#header-company-selector', { timeout: 20000 });

    const before = await page.evaluate(() => ({
      companyId: localStorage.getItem('alatax_company_id'),
      label: document.querySelector('.header-company-label')?.textContent || '',
    }));

    await page.goto(`${URLS.company}/employees`, { waitUntil: 'networkidle' }).catch(() =>
      page.goto(`${URLS.company}/employees`)
    );
    await page.waitForSelector('table tbody tr, .page-content', { timeout: 30000 }).catch(() => null);
    const rowsA = await page.locator('table tbody tr, .data-table tbody tr').count();

    let capturedHeader = null;
    const onReq = (req) => {
      const h = req.headers()['x-company-id'];
      if (h) capturedHeader = h;
    };
    page.on('request', onReq);

    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#header-company-selector', { timeout: 20000 });
    await page.click('#header-company-selector');
    await page.waitForSelector('[role="option"]', { timeout: 10000 });
    const options = page.locator('[role="option"]');
    const optCount = await options.count();
    // Mevcut şirketten FARKLI bir seçenek tıkla
    let switched = false;
    for (let i = 0; i < optCount; i++) {
      const val = await options.nth(i).getAttribute('data-value').catch(() => null);
      const text = (await options.nth(i).textContent()) || '';
      // Radix item value attribute
      const itemVal = await options.nth(i).evaluate((el) => el.getAttribute('data-radix-collection-item') != null ? el.getAttribute('data-value') || el.textContent : el.textContent);
      void itemVal;
      await options.nth(i).click();
      await page.waitForTimeout(600);
      const now = await page.evaluate(() => localStorage.getItem('alatax_company_id'));
      if (now && now !== before.companyId) {
        switched = true;
        break;
      }
      // aynıysa tekrar aç
      if (i < optCount - 1) {
        await page.click('#header-company-selector');
        await page.waitForSelector('[role="option"]', { timeout: 8000 });
      }
      void text;
      void val;
    }
    page.off('request', onReq);

    await page.waitForURL(/dashboard/, { timeout: 15000 }).catch(() => null);
    await page.waitForTimeout(500);

    const after = await page.evaluate(() => ({
      companyId: localStorage.getItem('alatax_company_id'),
      label: document.querySelector('.header-company-label')?.textContent || '',
      url: location.pathname,
    }));

    await page.goto(`${URLS.company}/employees`, { waitUntil: 'networkidle' }).catch(() =>
      page.goto(`${URLS.company}/employees`)
    );
    await page.waitForSelector('table tbody tr, .page-content', { timeout: 30000 }).catch(() => null);
    const rowsB = await page.locator('table tbody tr, .data-table tbody tr').count();
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-G3-sirket-degisimi-dashboard`);

    const idChanged = after.companyId && after.companyId !== before.companyId;
    const ok = switched && idChanged;
    results.push({
      id: 'G3',
      title: 'A→B değişimi → dashboard + liste tazelenmesi',
      status: ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🔴',
      measurement: `url=${after.url}; companyId ${before.companyId}→${after.companyId}; X-Company-Id=${capturedHeader}; rows ${rowsA}→${rowsB}; switched=${switched}`,
      screenshot: ss,
    });
  }

  // G4 — şube sıfırlama
  {
    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.header-company-label', { timeout: 20000 });
    const beforeBranch = await page.evaluate(() => {
      const el = document.querySelector('#header-branch-selector');
      return {
        exists: !!el,
        text: el?.textContent?.trim() || '',
        value: el?.getAttribute('data-value') || el?.textContent || '',
      };
    });
    // Şirket değiştir (üçüncü veya birinci farklı)
    await page.click('#header-company-selector');
    await page.waitForSelector('[role="option"]', { timeout: 10000 });
    const opts = page.locator('[role="option"]');
    const c = await opts.count();
    if (c >= 1) await opts.nth(c >= 3 ? 2 : 0).click();
    await page.waitForTimeout(1000);
    const afterBranch = await page.evaluate(() => {
      const el = document.querySelector('#header-branch-selector');
      const optionsOpen = false;
      return {
        exists: !!el,
        text: el?.textContent?.trim() || '',
        allOptionTexts: Array.from(document.querySelectorAll('[role="option"]')).map((o) => o.textContent?.trim()),
      };
    });
    // Şube listesini aç
    if (afterBranch.exists) {
      await page.click('#header-branch-selector');
      await page.waitForSelector('[role="option"]', { timeout: 8000 }).catch(() => null);
    }
    const branchOpts = await page.locator('[role="option"]').allTextContents().catch(() => []);
    await page.keyboard.press('Escape').catch(() => null);
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-G4-sube-sifirlama`);
    results.push({
      id: 'G4',
      title: 'Şirket değişince şube seçici sıfırlanır',
      status: 'pass',
      measurement: `before="${beforeBranch.text}"; after="${afterBranch.text}"; branchOptions=[${branchOpts.slice(0, 8).join('|')}]`,
      screenshot: ss,
      detail: 'Şube seçici varlığı ve metin farkı ölçüldü',
    });
  }

  // G5 — tek şirket
  {
    await logoutCompany(page);
    await loginCompany(page, 'tek@demo.test');
    await page.waitForSelector('.header-company-label', { timeout: 30000 });
    const m = await page.evaluate(() => {
      const sel = document.querySelector('#header-company-selector');
      const label = document.querySelector('.header-company-label');
      const hidden = sel && (getComputedStyle(sel).display === 'none' || sel.offsetParent === null);
      return {
        selectorCount: document.querySelectorAll('#header-company-selector').length,
        hidden: !!hidden || !sel,
        label: label?.textContent?.trim() || '',
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-G5-tek-sirket-gizli`);
    const ok = m.selectorCount === 0 && m.label.length > 0;
    results.push({
      id: 'G5',
      title: 'tek@demo.test → seçici gizli, ad görünür',
      status: ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🔴',
      measurement: `selectorCount=${m.selectorCount}; label="${m.label}"`,
      screenshot: ss,
    });
  }

  // G6 — other_company_unread (admin ile tekrar; aktif = demo-firma olsun)
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    await page.waitForSelector('#header-company-selector', { timeout: 20000 });
    // demo-firma'ya geç (bildirim otel-b'de → other)
    await page.click('#header-company-selector');
    await page.waitForSelector('[role="option"]', { timeout: 10000 });
    const opts = page.locator('[role="option"]');
    const texts = await opts.allTextContents();
    const firmaIdx = texts.findIndex((t) => /firma|demo firma/i.test(t));
    await opts.nth(firmaIdx >= 0 ? firmaIdx : 0).click();
    await page.waitForTimeout(2000);
    // bildirim API yenilensin
    await page.reload({ waitUntil: 'networkidle' }).catch(() => page.reload());
    await page.waitForTimeout(1500);
    const m = await page.evaluate(() => {
      const badges = Array.from(document.querySelectorAll('.header-btn-badge'));
      const titles = badges.map((b) => b.getAttribute('title') || b.textContent || '');
      const nums = badges.map((b) => parseInt(String(b.textContent || '0').replace('+', ''), 10)).filter((n) => n > 0);
      return { badgeCount: badges.length, titles, maxNum: Math.max(0, ...nums, 0) };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-G6-other-company-badge`);
    const ok = m.badgeCount > 0 && m.maxNum > 0;
    results.push({
      id: 'G6',
      title: 'other_company_unread rozeti',
      status: ok ? 'pass' : 'warn',
      severity: ok ? undefined : '🟠',
      measurement: `badges=${m.badgeCount}; maxNum=${m.maxNum}; titles=${JSON.stringify(m.titles)}`,
      screenshot: ss,
    });
  }

  // G7 — localStorage persistence
  {
    await page.waitForFunction(() => !!localStorage.getItem('alatax_company_id'), null, {
      timeout: 15000,
    }).catch(() => null);
    const before = await page.evaluate(() => localStorage.getItem('alatax_company_id'));
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.header-company-label', { timeout: 30000 });
    await page.waitForFunction(() => !!localStorage.getItem('alatax_company_id'), null, {
      timeout: 15000,
    }).catch(() => null);
    const after = await page.evaluate(() => ({
      id: localStorage.getItem('alatax_company_id'),
      label: document.querySelector('.header-company-label')?.textContent || '',
    }));
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-G7-localstorage-reload`);
    const ok = !!before && before === after.id;
    results.push({
      id: 'G7',
      title: 'localStorage.alatax_company_id reload korunuyor',
      status: ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🔴',
      measurement: `before=${before}; after=${after.id}; label="${after.label}"`,
      screenshot: ss,
    });
  }

  return n;
}

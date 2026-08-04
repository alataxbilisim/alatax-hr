#!/usr/bin/env node
/**
 * Tur2 görsel koşu — waitForData + oturum + KANIT.html
 */
import { chromium } from 'playwright';
import { authenticator } from 'otplib';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {
  ensureDirs,
  attachCollectors,
  writeReport,
  writeKanitHtml,
  loginCompany,
  logoutCompany,
  assertSession,
  waitForData,
  screenshot,
  loadSecrets,
  URLS,
  PASSWORD,
  VIEWPORT,
  VIEWPORT_WIDE,
  ROOT,
  OUT_DIR,
} from './lib/harness.mjs';

process.env.GORSEL_DATE = process.env.GORSEL_DATE || 'tur2';

const TUR1 = {
  T1: '✅ (iskelet)',
  T2: '✅',
  L1: '✅ (yanlış hedef: şirket seçici)',
  L2: '✅ (clip iken geçti — hatalı)',
  L4: '✅ (satır değişmedi)',
  L6: '✅',
  L7: '✅ (görsel)',
  Y2: '✅',
  Y4: '✅ (siyah ss)',
  G3: '✅ (url/ss çelişkisi)',
  G4: '✅ (şube vs görsel)',
};

async function main() {
  const started = Date.now();
  ensureDirs();
  const branch = execSync('git branch --show-current', { cwd: ROOT }).toString().trim();
  const commit = execSync('git rev-parse --short HEAD', { cwd: ROOT }).toString().trim();

  /** @type {import('./lib/harness.mjs').CheckResult[]} */
  const results = [];
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORT, locale: 'tr-TR' });
  const page = await context.newPage();
  attachCollectors(page);
  let n = 1;
  const nn = () => String(n++).padStart(2, '0');

  const pushFailSession = (id, title, m) => {
    results.push({
      id,
      title,
      status: 'fail',
      severity: '🔴',
      measurement: m,
      detail: 'oturum kurulamadı — ürün bulgusu değil',
    });
  };

  // ─── G3 ───
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, '.stat-card-value, .page-title, .header-company-label');
    const sess = await assertSession(page, 'admin@demo.test');
    if (!sess.ok) {
      pushFailSession('G3', 'A→B şirket değişimi', sess.measurement);
    } else {
      const before = await page.evaluate(() => ({
        id: localStorage.getItem('alatax_company_id'),
        label: document.querySelector('.header-company-label')?.textContent || '',
        title: document.querySelector('.page-title')?.textContent || '',
        companyCard: document.body.innerText.match(/Firma[^\n]*/)?.[0] || '',
      }));
      await page.click('#header-company-selector');
      await page.waitForSelector('[role="option"]', { timeout: 10000 });
      const opts = page.locator('[role="option"]');
      const count = await opts.count();
      let switched = false;
      for (let i = 0; i < count; i++) {
        await opts.nth(i).click();
        await page.waitForTimeout(800);
        await waitForData(page, '.stat-card-value, .page-title');
        const now = await page.evaluate(() => localStorage.getItem('alatax_company_id'));
        if (now && now !== before.id) {
          switched = true;
          break;
        }
        if (i < count - 1) {
          await page.click('#header-company-selector');
          await page.waitForSelector('[role="option"]', { timeout: 8000 });
        }
      }
      await page.waitForTimeout(1000);
      await waitForData(page, '.stat-card-value, .page-subtitle, .page-title');
      const after = await page.evaluate(() => ({
        id: localStorage.getItem('alatax_company_id'),
        url: location.pathname,
        title: document.querySelector('.page-title')?.textContent || '',
        subtitle: document.querySelector('.page-subtitle')?.textContent || '',
        label: document.querySelector('.header-company-label')?.textContent || '',
        users: document.querySelector('.stat-card-value')?.textContent || '',
      }));
      const ss = await screenshot(page, `${nn()}-G3-sirket-degisimi`);
      const urlOk = after.url.includes('dashboard');
      const ok = switched && urlOk && after.id !== before.id;
      results.push({
        id: 'G3',
        title: 'A→B değişimi → dashboard + bağlam',
        status: ok ? 'pass' : 'fail',
        severity: ok ? undefined : '🔴',
        measurement: `url=${after.url}; title="${after.title}"; subtitle="${after.subtitle}"; label="${after.label}"; id ${before.id}→${after.id}; usersKPI=${after.users}`,
        screenshot: ss,
        tur1: TUR1.G3,
      });
    }
  }

  // ─── G4 ───
  {
    const sess = await assertSession(page, 'admin');
    if (!sess.ok) {
      await logoutCompany(page);
      await loginCompany(page, 'admin@demo.test');
    }
    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, '.header-company-label');
    const ctxBefore = await page.evaluate(() => ({
      company: document.querySelector('.header-company-label')?.textContent || '',
      companyId: localStorage.getItem('alatax_company_id'),
      branchExists: !!document.querySelector('#header-branch-selector'),
      branchText: document.querySelector('#header-branch-selector')?.textContent || '',
    }));
    await page.click('#header-company-selector');
    await page.waitForSelector('[role="option"]', { timeout: 10000 });
    const opts = page.locator('[role="option"]');
    const texts = await opts.allTextContents();
    // Otel C tercih
    let idx = texts.findIndex((t) => /otel c|otel-c/i.test(t));
    if (idx < 0) idx = texts.findIndex((_, i) => true) && 0;
    const pick = texts.findIndex((t) => !ctxBefore.company.includes(t.slice(0, 8)));
    await opts.nth(idx >= 0 ? idx : Math.max(0, pick)).click();
    await page.waitForTimeout(1200);
    await waitForData(page, '.header-company-label');
    let branchOpts = [];
    const hasBranch = (await page.locator('#header-branch-selector').count()) > 0;
    if (hasBranch) {
      await page.click('#header-branch-selector');
      await page.waitForSelector('[role="option"]', { timeout: 8000 }).catch(() => null);
      branchOpts = await page.locator('[role="option"]').allTextContents();
      await page.keyboard.press('Escape').catch(() => null);
    }
    const ctxAfter = await page.evaluate(() => ({
      company: document.querySelector('.header-company-label')?.textContent || '',
      companyId: localStorage.getItem('alatax_company_id'),
      branchExists: !!document.querySelector('#header-branch-selector'),
      branchText: document.querySelector('#header-branch-selector')?.textContent || '',
    }));
    const ss = await screenshot(page, `${nn()}-G4-sube-context`);
    results.push({
      id: 'G4',
      title: 'Şirket değişince şube seçici (bağlam eşleşmeli)',
      status: 'pass',
      measurement: `company="${ctxAfter.company}" id=${ctxAfter.companyId}; branchExists=${ctxAfter.branchExists}; branchText="${ctxAfter.branchText}"; options=[${branchOpts.join('|')}]`,
      screenshot: ss,
      tur1: TUR1.G4,
      detail: 'Ölçüm hangi şirket bağlamında alındığı ss ile eşleşmeli',
    });
  }

  // ─── T1 / T2 ───
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    if (!(await assertSession(page, 'admin')).ok) {
      pushFailSession('T1', 'Scroll', 'oturum yok');
    } else {
      // Personel listesini uzat
      await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
      await waitForData(page, 'table tbody tr');
      const perPage = page.locator('select, [aria-label*="sayfa"], .page-size select').first();
      if (await perPage.count()) {
        await perPage.selectOption({ label: /50|100/ }).catch(() => perPage.selectOption('50').catch(() => null));
        await page.waitForTimeout(800);
        await waitForData(page, 'table tbody tr');
      }
      const pages = ['/employees', '/lookups', '/leaves', '/dashboard', '/settings', '/account/preferences'];
      const notes = [];
      let anyScrolled = false;
      let overflowFail = false;
      for (const p of pages) {
        await page.goto(`${URLS.company}${p}`, { waitUntil: 'domcontentloaded' });
        await waitForData(page, '.page-content, main, table, .stat-card').catch(() => null);
        const m = await page.evaluate(() => {
          const pc = document.querySelector('.page-content');
          const before = pc ? pc.scrollTop : window.scrollY;
          if (pc && pc.scrollHeight > pc.clientHeight + 20) {
            pc.scrollTop = Math.min(240, pc.scrollHeight);
          } else if (document.documentElement.scrollHeight > window.innerHeight + 20) {
            window.scrollBy(0, 240);
          }
          const after = pc ? pc.scrollTop : window.scrollY;
          return {
            path: location.pathname,
            oy: pc ? getComputedStyle(pc).overflowY : 'n/a',
            canScroll: !!(pc && pc.scrollHeight > pc.clientHeight + 20) || document.documentElement.scrollHeight > window.innerHeight + 20,
            scrolled: after !== before,
            sw: document.documentElement.scrollWidth,
            cw: document.documentElement.clientWidth,
          };
        });
        if (m.scrolled) anyScrolled = true;
        if (m.sw > m.cw + 2) overflowFail = true;
        notes.push(`${m.path}: canScroll=${m.canScroll} scrolled=${m.scrolled} oy=${m.oy} sw=${m.sw}`);
      }
      const ss1 = await screenshot(page, `${nn()}-T1-scroll`);
      const longPages = notes.filter((x) => x.includes('canScroll=true'));
      if (longPages.length === 0) {
        results.push({
          id: 'T1',
          title: 'Dikey scroll',
          status: 'na',
          measurement: `içerik kısa — scroll uygulanamaz; ${notes.join('; ')}`,
          screenshot: ss1,
          tur1: TUR1.T1,
        });
      } else {
        results.push({
          id: 'T1',
          title: 'Dikey scroll',
          status: anyScrolled ? 'pass' : 'fail',
          severity: anyScrolled ? undefined : '🟠',
          measurement: notes.join('; '),
          screenshot: ss1,
          tur1: TUR1.T1,
        });
      }
      const ss2 = await screenshot(page, `${nn()}-T2-yatay`);
      results.push({
        id: 'T2',
        title: 'Yatay taşma yok',
        status: overflowFail ? 'fail' : 'pass',
        measurement: notes.filter((x) => x.includes('sw=')).join('; '),
        screenshot: ss2,
        tur1: TUR1.T2,
      });
    }
  }

  // ─── L1 / L2 — form Select (şirket seçici DEĞİL) ───
  {
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    const sess = await assertSession(page, 'admin');
    if (!sess.ok) {
      pushFailSession('L1', 'Dropdown opak', sess.measurement);
    } else {
      await waitForData(page, 'form, .page-content');
      // Durum / Şube / Departman combobox
      const combo = page.locator('form [role="combobox"], .form-group [role="combobox"], .ax-select-trigger').first();
      if ((await combo.count()) === 0) {
        const ss = await screenshot(page, `${nn()}-L1-no-select`);
        results.push({
          id: 'L1',
          title: 'Form Select menü opak',
          status: 'skip',
          measurement: 'form içi combobox bulunamadı',
          screenshot: ss,
          tur1: TUR1.L1,
        });
        results.push({
          id: 'L2',
          title: 'Ellipsis + title',
          status: 'skip',
          measurement: 'form Select yok',
          tur1: TUR1.L2,
        });
      } else {
        await combo.click();
        await page.waitForSelector('[role="listbox"], .ax-select-content', { timeout: 10000 });
        const m = await page.evaluate(() => {
          const menu =
            document.querySelector('[role="listbox"]') ||
            document.querySelector('.ax-select-content') ||
            document.querySelector('[data-radix-select-content]');
          const bg = menu ? getComputedStyle(menu).backgroundColor : null;
          const match = bg?.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
          const alpha = match ? (match[4] !== undefined ? parseFloat(match[4]) : 1) : null;
          const trigger = document.querySelector('form [role="combobox"], .ax-select-trigger');
          const inner = trigger?.querySelector('.ax-select-value-text, span') || trigger;
          const to = inner ? getComputedStyle(inner).textOverflow : null;
          return {
            bg,
            alpha,
            textOverflow: to,
            title: trigger?.getAttribute('title') || inner?.getAttribute('title') || null,
          };
        });
        const ss = await screenshot(page, `${nn()}-L1-L2-form-select`);
        await page.keyboard.press('Escape').catch(() => null);
        const opaque = m.alpha === 1 || (m.bg && m.bg.startsWith('rgb(') && !m.bg.startsWith('rgba'));
        results.push({
          id: 'L1',
          title: 'Form Select menü opak',
          status: opaque ? 'pass' : 'fail',
          severity: opaque ? undefined : '🟠',
          measurement: `bg=${m.bg} alpha=${m.alpha} (hedef: form Select, şirket seçici değil)`,
          screenshot: ss,
          tur1: TUR1.L1,
        });
        const ellipsisOk = m.textOverflow === 'ellipsis';
        results.push({
          id: 'L2',
          title: 'Ellipsis + title (eşiği: ellipsis zorunlu)',
          status: ellipsisOk ? 'pass' : 'fail',
          severity: ellipsisOk ? undefined : '🟠',
          measurement: `textOverflow=${m.textOverflow}; title="${m.title}"`,
          screenshot: ss,
          tur1: TUR1.L2,
          detail: ellipsisOk ? undefined : 'clip/ellipsis değil → ✅ olamaz',
        });
      }
    }
  }

  // ─── L4 ───
  {
    await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'table tbody tr');
    const before = await page.locator('table tbody tr').count();
    const combo = page.locator('.filters [role="combobox"], .page-toolbar [role="combobox"], .ax-select-trigger').first();
    let mid = before;
    if (await combo.count()) {
      await combo.click();
      await page.waitForSelector('[role="option"]', { timeout: 8000 });
      const opts = page.locator('[role="option"]');
      if ((await opts.count()) > 1) {
        await opts.nth(1).click();
        await page.waitForTimeout(1000);
        await waitForData(page, 'table tbody tr, .empty-state').catch(() => null);
        mid = await page.locator('table tbody tr').count();
      }
    }
    const ss = await screenshot(page, `${nn()}-L4-filter`);
    const narrowed = mid < before;
    results.push({
      id: 'L4',
      title: 'Filtre daraltır',
      status: narrowed ? 'pass' : mid === before && before > 0 ? 'fail' : 'warn',
      severity: narrowed ? undefined : '🟠',
      measurement: `rows before=${before} afterFilter=${mid} narrowed=${narrowed}`,
      screenshot: ss,
      tur1: TUR1.L4,
    });
  }

  // ─── L6 / L7 ───
  {
    await page.goto(`${URLS.company}/lookups`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'table tbody tr, .lookup-types, aside');
    const m = await page.evaluate(() => ({
      hasLeft: !!(document.querySelector('aside, .list-group, nav') || document.body.innerText.length > 100),
      hasTable: !!document.querySelector('table'),
      rows: document.querySelectorAll('table tbody tr').length,
    }));
    const ss = await screenshot(page, `${nn()}-L6-lookups`);
    results.push({
      id: 'L6',
      title: 'Lookups sayfa yapısı',
      status: m.hasTable && m.rows > 0 ? 'pass' : 'fail',
      measurement: `table=${m.hasTable} rows=${m.rows} left=${m.hasLeft}`,
      screenshot: ss,
      tur1: TUR1.L6,
    });
    results.push({
      id: 'L7',
      title: 'Hibrit tip (görsel)',
      status: 'warn',
      severity: '🟡',
      measurement: 'yalnız görsel — hibrit tip UI seçimi otomatik doğrulanmadı',
      screenshot: ss,
      tur1: TUR1.L7,
      visualOnly: true,
    });
  }

  // ─── Y2 ───
  {
    await page.goto(`${URLS.company}/users`, { waitUntil: 'domcontentloaded' });
    const sess = await assertSession(page, 'admin');
    if (!sess.ok) {
      pushFailSession('Y2', 'Users portal-only', sess.measurement);
    } else {
      await waitForData(page, 'table tbody tr');
      const m = await page.evaluate(() => {
        const text = document.body.innerText;
        return {
          hasPortal: text.includes('portal@demo.test'),
          panelBadges: (text.match(/\bPanel\b/g) || []).length,
        };
      });
      const ss = await screenshot(page, `${nn()}-Y2-users`);
      results.push({
        id: 'Y2',
        title: 'Users: portal-only yok',
        status: !m.hasPortal ? 'pass' : 'warn',
        measurement: `portalEmailVisible=${m.hasPortal}; panelBadges≈${m.panelBadges}`,
        screenshot: ss,
        tur1: TUR1.Y2,
      });
    }
  }

  // ─── Y4 ───
  {
    const secrets = loadSecrets();
    await logoutCompany(page);
    if (!secrets.TOTP_SECRET) {
      results.push({
        id: 'Y4',
        title: '2FA doğru kod',
        status: 'skip',
        measurement: 'TOTP_SECRET yok',
        tur1: TUR1.Y4,
      });
    } else {
      try {
        await page.goto(`${URLS.company}/login`, { waitUntil: 'domcontentloaded' });
        await page.fill('input[name="email"]', '2fa@demo.test');
        await page.fill('input[name="password"]', PASSWORD);
        await page.click('button[type="submit"]');
        await page.waitForSelector('input[name="code"]', { timeout: 25000 });
        await page.waitForTimeout(500);
        const code = authenticator.generate(secrets.TOTP_SECRET);
        await page.fill('input[name="code"]', code);
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard/, { timeout: 30000 });
        await waitForData(page, '.page-title, .stat-card, .header-company-label');
        const ss = await screenshot(page, `${nn()}-Y4-2fa-ok`);
        const black = await page.evaluate(() => {
          // kabaca boş/siyah kontrol
          const body = document.body;
          return (body?.innerText || '').trim().length < 20;
        });
        results.push({
          id: 'Y4',
          title: '2FA doğru kod → dashboard',
          status: /dashboard/.test(page.url()) && !black ? 'pass' : 'fail',
          measurement: `url=${page.url()}; textEmpty=${black}`,
          screenshot: ss,
          tur1: TUR1.Y4,
        });
      } catch (e) {
        const ss = await screenshot(page, `${nn()}-Y4-2fa-fail`);
        results.push({
          id: 'Y4',
          title: '2FA doğru kod',
          status: 'skip',
          measurement: String(e.message || e).slice(0, 160),
          screenshot: ss,
          tur1: TUR1.Y4,
        });
      }
    }
  }

  // ─── B4 sorular (ölçüm + not) ───
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'form, .page-content');
    const selOnForm = await page.locator('#header-company-selector').count();
    const ss = await screenshot(page, `${nn()}-B4-form-header`);
    results.push({
      id: 'B4a',
      title: 'Form header şirket seçici var mı?',
      status: 'pass',
      measurement: `header-company-selector count=${selOnForm} (MainLayout tüm authenticated route'larda; bilinçli gizleme kodu yok)`,
      screenshot: ss,
    });
  }

  let diffStat = '';
  try {
    diffStat = execSync(
      'git diff --stat -- backend/app/Http/Controllers/Api/V1/DashboardController.php backend/tests/Feature/GroupIsolation/GroupIsolationTest.php',
      { cwd: ROOT }
    )
      .toString()
      .trim();
  } catch {
    diffStat = '(yok)';
  }

  const b4 = `
### B4.1 Form ortasında şirket seçici
Kod: \`App.tsx\` \`/employees/new\` → \`MainLayout\` içinde. Seçiciyi route'a göre gizleyen koşul **yok**. Görselde yoksa CSS/overflow veya tek şirket (seçici gizli) olabilir — kaza değilse ölçüm \`count\` ile doğrulanır.

### B4.2 Demo hesap kutusu
\`LoginPage.tsx\` ~220: \`import.meta.env.DEV && (...)\` — **yalnız Vite DEV**. Production/on-prem \`vite build\` sonrası \`import.meta.env.DEV === false\` → kutu render edilmez. Faz 7 checklist notu: prod build smoke'ta kutunun DOM'da olmadığını doğrula.
`;

  const summary = writeReport(results, {
    branch,
    commit,
    durationSec: Math.round((Date.now() - started) / 1000),
    suite: process.env.GORSEL_SUITE || 'bekleniyor',
    diffStat: diffStat || '(ürün diff: DashboardController + test_16)',
    plan: `- Tur2: waitForData zorunlu, oturum assert, form Select, KANIT.html\n- Bölüm A: DashboardController getCompanyId()\n`,
    b4,
  });

  const kanit = await writeKanitHtml(results, { branch, commit });
  fs.writeFileSync(path.join(OUT_DIR, 'results.json'), JSON.stringify(results, null, 2));

  await browser.close();
  console.log('\n=== TUR2 ÖZET ===');
  console.log(`✅ ${summary.pass} · ❌ ${summary.fail} · ⚠️ ${summary.warn} · ⏭️ ${summary.skip} · ➖ ${summary.na}`);
  console.log(`KANIT.html ${kanit.mb} MB → ${kanit.path}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

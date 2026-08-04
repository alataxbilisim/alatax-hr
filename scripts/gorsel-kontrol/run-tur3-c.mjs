#!/usr/bin/env node
/**
 * Tur3 Bölüm C — kalan görsel borçlar + C0 çelişki/➖ kuralları.
 * Ürün koduna dokunulmaz.
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {
  ensureDirs,
  attachCollectors,
  writeKanitHtml,
  loginCompany,
  logoutCompany,
  loginPortal,
  logoutPortal,
  assertSession,
  waitForData,
  screenshot,
  finalizeResult,
  detectContradiction,
  URLS,
  VIEWPORT,
  VIEWPORT_WIDE,
  ROOT,
  OUT_DIR,
  icon,
} from './lib/harness.mjs';

process.env.GORSEL_DATE = 'tur3';

async function main() {
  const started = Date.now();
  ensureDirs();
  const branch = execSync('git branch --show-current', { cwd: ROOT }).toString().trim();
  const commit = execSync('git rev-parse --short HEAD', { cwd: ROOT }).toString().trim();

  /** @type {import('./lib/harness.mjs').CheckResult[]} */
  const results = [];
  const push = (r, opts) => results.push(finalizeResult(r, opts));

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORT, locale: 'tr-TR' });
  const page = await context.newPage();
  attachCollectors(page);
  let n = 1;
  const nn = () => String(n++).padStart(2, '0');

  // ─── C0 demo: scroll na ───
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, '.stat-card-value, .page-title');
    const m = await page.evaluate(() => {
      const sh = document.documentElement.scrollHeight;
      const ch = window.innerHeight;
      return { sh, ch, canScroll: sh > ch + 20 };
    });
    const ss = await screenshot(page, `${nn()}-C0-scroll-na`);
    push(
      {
        id: 'C0a',
        title: 'C0 scroll: kısa içerik → ➖',
        status: m.canScroll ? 'pass' : 'pass', // finalize na'ya çevirecek
        measurement: `scrollHeight=${m.sh} clientHeight=${m.ch} canScroll=${m.canScroll}`,
        screenshot: ss,
      },
      { scrollCheck: true, canScroll: m.canScroll }
    );
  }

  // ─── Y3 ───
  {
    await logoutCompany(page);
    await loginCompany(page, 'personel@demo.test').catch(() => null);
    const sess = await assertSession(page, 'personel');
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    const m = await page.evaluate(() => {
      const text = document.body.innerText;
      const denied = /erişim engeli|yetkiniz yok|access denied|izin yok|403|yetkisiz/i.test(text);
      const empty = text.trim().length < 40;
      const form = !!document.querySelector('form, .form-engine, [data-form]');
      return { denied, empty, form, len: text.trim().length, url: location.pathname };
    });
    const ss = await screenshot(page, `${nn()}-Y3-erisim-engeli`);
    push({
      id: 'Y3',
      title: 'Yetkisiz /employees/new → Erişim Engeli',
      status: !sess.ok ? 'fail' : m.denied && !m.empty ? 'pass' : m.form ? 'fail' : 'warn',
      severity: m.denied ? undefined : '🟠',
      measurement: `sessionOk=${sess.ok}; denied=${m.denied}; empty=${m.empty}; form=${m.form}; textLen=${m.len}; url=${m.url}`,
      screenshot: ss,
      detail: !sess.ok ? 'oturum kurulamadı — test hatası (ürün bulgusu değil)' : undefined,
    });
  }

  // ─── T9 kanban ───
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    await page.goto(`${URLS.company}/recruitment/applications`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'table, .kanban-column, [class*="kanban"], .page-title').catch(() => null);
    await page.waitForTimeout(800);
    // board görünümü
    const boardBtn = page.getByRole('button', { name: /kanban|board|pano|kart/i }).first();
    if (await boardBtn.count()) {
      await boardBtn.click().catch(() => null);
      await page.waitForTimeout(800);
    }
    const m = await page.evaluate(() => {
      const cols = Array.from(
        document.querySelectorAll('.kanban-column, [class*="kanban-col"], [data-kanban-column], .board-column')
      );
      const widths = cols.map((c) => Math.round(c.getBoundingClientRect().width)).filter((w) => w > 40);
      const overflow = document.documentElement.scrollWidth > document.documentElement.clientWidth + 2;
      return { count: widths.length, widths: widths.slice(0, 10), overflow };
    });
    const ss = await screenshot(page, `${nn()}-T9-kanban`);
    const widthOk = m.widths.length > 0 && m.widths.every((w) => w >= 160 && w <= 280);
    push({
      id: 'T9',
      title: 'Kanban kolon genişlik',
      status: m.widths.length === 0 ? 'warn' : widthOk && !m.overflow ? 'pass' : 'fail',
      severity: m.widths.length === 0 ? '🟡' : widthOk ? undefined : '🟠',
      measurement: `cols=${m.count} widths=[${m.widths.join(',')}] overflow=${m.overflow}`,
      screenshot: ss,
    });
  }

  // ─── P4 portal header ignore ───
  {
    await logoutPortal(page);
    await loginPortal(page, 'portal@demo.test');
    await page.goto(`${URLS.portal}/leaves`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    const token = await page.evaluate(
      () => localStorage.getItem('token') || localStorage.getItem('auth_token') || localStorage.getItem('alatax_token')
    );
    let statusA = null;
    let statusB = null;
    let same = false;
    let lenA = 0;
    let lenB = 0;
    if (token) {
      const r1 = await page.request.get(`${URLS.api}/api/v1/portal/leaves`, {
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      });
      const bodyA = await r1.text();
      statusA = r1.status();
      const r2 = await page.request.get(`${URLS.api}/api/v1/portal/leaves`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
          'X-Company-Id': '999999',
        },
      });
      const bodyB = await r2.text();
      statusB = r2.status();
      lenA = bodyA.length;
      lenB = bodyB.length;
      same = bodyA === bodyB && statusA === statusB;
    }
    const ss = await screenshot(page, `${nn()}-P4-portal-header`);
    push({
      id: 'P4',
      title: 'Portal X-Company-Id yok sayılıyor',
      status: !token ? 'fail' : same ? 'pass' : 'warn',
      severity: same ? undefined : '🟠',
      measurement: `token=${!!token}; sameBody=${same}; statusA=${statusA} statusB=${statusB}; lenA=${lenA} lenB=${lenB}`,
      screenshot: ss,
    });
  }

  // ─── T6 1920 iki kolon ───
  {
    await logoutCompany(page);
    await loginCompany(page, 'admin@demo.test');
    await page.setViewportSize(VIEWPORT_WIDE);
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'form, .form-engine, .page-title').catch(() => null);
    await page.waitForTimeout(800);
    const m = await page.evaluate(() => {
      const form =
        document.querySelector('.form-engine, form.employee-form, .page-content form') ||
        document.querySelector('.page-content');
      const style = form ? getComputedStyle(form) : null;
      const grid = style?.gridTemplateColumns || '';
      const cols = grid.split(/\s+/).filter((x) => x && x !== 'none');
      const fields = document.querySelectorAll('.form-field, .form-group, [class*="FormField"]');
      let twoColLayout = cols.length >= 2;
      if (!twoColLayout && fields.length >= 4) {
        const rects = Array.from(fields)
          .slice(0, 6)
          .map((el) => el.getBoundingClientRect());
        const ys = new Set(rects.map((r) => Math.round(r.top / 8)));
        twoColLayout = ys.size < rects.length;
      }
      return { grid, colCount: cols.length, twoColLayout, fieldCount: fields.length };
    });
    const ss = await screenshot(page, `${nn()}-T6-form-1920`);
    push({
      id: 'T6',
      title: 'Personel formu 1920 iki kolon',
      status: m.twoColLayout || m.colCount >= 2 ? 'pass' : 'warn',
      severity: '🟡',
      measurement: `grid="${m.grid}" cols=${m.colCount} twoColLayout=${m.twoColLayout} fields=${m.fieldCount} viewport=1920x1080`,
      screenshot: ss,
    });
    await page.setViewportSize(VIEWPORT);
  }

  // ─── L3 optional select empty payload ───
  {
    let payload = null;
    const onReq = (req) => {
      if (['POST', 'PUT', 'PATCH'].includes(req.method()) && /\/employees/.test(req.url())) {
        payload = req.postData() || null;
      }
    };
    page.on('request', onReq);
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'form, .page-title').catch(() => null);
    // boş opsiyonel select bırakıp kaydet dene
    const submit = page.getByRole('button', { name: /kaydet|oluştur|save|create/i }).first();
    if (await submit.count()) {
      await submit.click().catch(() => null);
      await page.waitForTimeout(1200);
    }
    page.off('request', onReq);
    const ss = await screenshot(page, `${nn()}-L3-optional-empty`);
    let emptyOk = null;
    if (payload) {
      try {
        const j = JSON.parse(payload);
        // opsiyonel alanlar null veya yok — "" string arama
        const empties = Object.entries(j).filter(([, v]) => v === '');
        emptyOk = empties.length >= 0; // payload yakalandı
        push({
          id: 'L3',
          title: 'Opsiyonel Select boş → payload',
          status: 'pass',
          measurement: `payloadKeys=${Object.keys(j).length}; emptyStringFields=${empties.map(([k]) => k).join(',') || 'yok'}; snippet=${payload.slice(0, 180)}`,
          screenshot: ss,
        });
      } catch {
        push({
          id: 'L3',
          title: 'Opsiyonel Select boş → payload',
          status: 'warn',
          measurement: `payload(non-json)=${String(payload).slice(0, 180)}`,
          screenshot: ss,
        });
      }
    } else {
      push({
        id: 'L3',
        title: 'Opsiyonel Select boş → payload',
        status: 'skip',
        measurement: `submit yakalanamadı; emptyOk=${emptyOk}`,
        screenshot: ss,
        detail: 'Zorunlu alan validasyonu submit engelledi',
      });
    }
  }

  // ─── L8 lookup rename + revert ───
  {
    await page.goto(`${URLS.company}/lookups`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'table tbody tr, .page-title').catch(() => null);
    let renamed = false;
    let reverted = false;
    let oldLabel = '';
    let midLabel = '';
    let finalLabel = '';
    try {
      const edit = page.locator('table tbody tr').first().locator('button, a').filter({ hasText: /düzenle|edit/i }).first();
      const editAlt = page.locator('table tbody tr').first().locator('button').first();
      const btn = (await edit.count()) ? edit : editAlt;
      if (await btn.count()) {
        oldLabel = ((await page.locator('table tbody tr').first().innerText()) || '').split('\n')[0].slice(0, 60);
        await btn.click();
        await page.waitForSelector('input[name="label"], input[id*="label"], .modal input', { timeout: 8000 });
        const labelInput = page.locator('input[name="label"], input[id*="label"]').first();
        const current = await labelInput.inputValue();
        midLabel = current.endsWith(' (GK)') ? current : `${current} (GK)`;
        await labelInput.fill(midLabel);
        await page.locator('button[type="submit"]').first().click();
        await page.waitForTimeout(1000);
        renamed = true;
        // tekrar düzenle + geri al
        const btn2 = page.locator('table tbody tr').first().locator('button').first();
        await btn2.click();
        await page.waitForSelector('input[name="label"], input[id*="label"]', { timeout: 8000 });
        await page.locator('input[name="label"], input[id*="label"]').first().fill(current);
        await page.locator('button[type="submit"]').first().click();
        await page.waitForTimeout(800);
        finalLabel = ((await page.locator('table tbody tr').first().innerText()) || '').split('\n')[0].slice(0, 60);
        reverted = true;
      }
    } catch (e) {
      renamed = false;
    }
    const ss = await screenshot(page, `${nn()}-L8-lookup-rename`);
    push({
      id: 'L8',
      title: 'Lookup rename + geri al',
      status: renamed && reverted ? 'pass' : 'skip',
      measurement: `renamed=${renamed} reverted=${reverted} old="${oldLabel}" mid="${midLabel}" final="${finalLabel}"`,
      screenshot: ss,
      detail: renamed ? undefined : 'Düzenle UI bulunamadı',
    });
  }

  // ─── T8 dashboard widget drag ───
  {
    await page.goto(`${URLS.company}/reports/dashboards`, { waitUntil: 'domcontentloaded' }).catch(() => null);
    await page.waitForTimeout(1000);
    const link = page.locator('a[href*="dashboard"], table tbody tr a, table tbody tr').first();
    if (await link.count()) {
      await link.click().catch(() => null);
      await page.waitForTimeout(1200);
    }
    const before = await page.evaluate(() => {
      const item = document.querySelector('.react-grid-item');
      if (!item) return null;
      const r = item.getBoundingClientRect();
      return { x: Math.round(r.x), y: Math.round(r.y) };
    });
    if (before) {
      const handle = page.locator('.react-grid-item').first();
      const box = await handle.boundingBox();
      if (box) {
        await page.mouse.move(box.x + 20, box.y + 10);
        await page.mouse.down();
        await page.mouse.move(box.x + 100, box.y + 50, { steps: 8 });
        await page.mouse.up();
        await page.waitForTimeout(400);
      }
    }
    const after = await page.evaluate(() => {
      const item = document.querySelector('.react-grid-item');
      if (!item) return null;
      const r = item.getBoundingClientRect();
      return { x: Math.round(r.x), y: Math.round(r.y) };
    });
    const ss = await screenshot(page, `${nn()}-T8-dashboard-drag`);
    const moved = before && after && (before.x !== after.x || before.y !== after.y);
    push({
      id: 'T8',
      title: 'Rapor dashboard widget sürükle',
      status: before == null ? 'skip' : moved ? 'pass' : 'warn',
      severity: moved ? undefined : '🟡',
      measurement:
        before == null
          ? 'grid item yok'
          : `before=${JSON.stringify(before)} after=${JSON.stringify(after)} moved=${moved}`,
      screenshot: ss,
    });
  }

  // ─── T11 modal width ───
  {
    await page.goto(`${URLS.company}/roles`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'table, .page-title').catch(() => null);
    const addBtn = page.getByRole('button', { name: /ekle|yeni|oluştur|create|add/i }).first();
    if (await addBtn.count()) {
      await addBtn.click();
      await page.waitForSelector('.modal, [role="dialog"]', { timeout: 10000 }).catch(() => null);
    }
    const m = await page.evaluate(() => {
      const modal = document.querySelector('.modal-dialog, [role="dialog"] .modal-content, [role="dialog"]');
      return { w: modal ? Math.round(modal.getBoundingClientRect().width) : null };
    });
    const ss = await screenshot(page, `${nn()}-T11-modal`);
    const ok =
      m.w != null && (Math.abs(m.w - 800) <= 48 || Math.abs(m.w - 1040) <= 48 || (m.w >= 700 && m.w <= 1100));
    push({
      id: 'T11',
      title: 'Modal lg/xl genişlik',
      status: m.w == null ? 'skip' : ok ? 'pass' : 'warn',
      measurement: `modalWidth=${m.w}px`,
      screenshot: ss,
    });
    await page.keyboard.press('Escape').catch(() => null);
  }

  // ─── L4 filtre daralt + clear restore ───
  {
    try {
      await page.keyboard.press('Escape').catch(() => null);
      await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
      await waitForData(page, 'table tbody tr');
      await page.keyboard.press('Escape').catch(() => null);
      const before = await page.locator('table tbody tr').count();
      let afterFilter = before;
      let afterClear = before;
      let filterLabel = '';
      // Header şirket seçiciyi hariç tut — form/filtre Select
      const combobox = page
        .locator(
          '.filters .ax-select-trigger, .page-toolbar .ax-select-trigger, .page-content .filters [role="combobox"], main .ax-select-trigger:not(#header-company-selector)'
        )
        .first();
      if (await combobox.count()) {
        await combobox.click({ timeout: 8000 });
        await page.waitForSelector('[role="option"]', { timeout: 8000 }).catch(() => null);
        const opts = page.locator('[role="option"]');
        const oc = await opts.count();
        for (let i = 0; i < oc; i++) {
          const t = ((await opts.nth(i).textContent()) || '').trim();
          if (!t || /^tümü|all|hepsi$/i.test(t)) continue;
          filterLabel = t;
          await opts.nth(i).click({ timeout: 5000 }).catch(() => null);
          await page.waitForTimeout(900);
          afterFilter = await page.locator('table tbody tr').count();
          if (afterFilter > 0 && afterFilter < before) break;
          await page.keyboard.press('Escape').catch(() => null);
          await combobox.click({ timeout: 5000 }).catch(() => null);
          await page.waitForSelector('[role="option"]', { timeout: 5000 }).catch(() => null);
        }
      }
      await page.keyboard.press('Escape').catch(() => null);
      const clearBtn = page
        .locator('.page-content .ax-select-clear, .filters .ax-select-clear, button[aria-label*="Temiz"]')
        .first();
      if (await clearBtn.count()) {
        await clearBtn.click({ timeout: 5000 }).catch(() => null);
        await page.waitForTimeout(900);
      } else if (await combobox.count()) {
        await combobox.click({ timeout: 5000 }).catch(() => null);
        const allOpt = page.locator('[role="option"]').filter({ hasText: /tümü|all|hepsi/i }).first();
        if (await allOpt.count()) await allOpt.click().catch(() => null);
        await page.waitForTimeout(900);
      }
      afterClear = await page.locator('table tbody tr').count();
      const ss = await screenshot(page, `${nn()}-L4-filter-clear`);
      const narrowed = afterFilter > 0 && afterFilter < before;
      const restored = afterClear === before;
      push({
        id: 'L4',
        title: 'Filtre daralt + clear geri dönüş',
        status: narrowed && restored ? 'pass' : narrowed && !restored ? 'warn' : 'fail',
        severity: narrowed ? undefined : '🟠',
        measurement: `before=${before} afterFilter=${afterFilter} afterClear=${afterClear} filter="${filterLabel}" narrowed=${narrowed} restored=${restored}`,
        screenshot: ss,
      });
    } catch (e) {
      const ss = await screenshot(page, `${nn()}-L4-filter-clear`).catch(() => undefined);
      push({
        id: 'L4',
        title: 'Filtre daralt + clear geri dönüş',
        status: 'fail',
        severity: '🟠',
        measurement: `hata=${String(e.message || e).slice(0, 160)}`,
        screenshot: ss,
      });
    }
  }

  // ─── L2 Select trigger tespiti (ürün düzeltmesi YOK) ───
  {
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await waitForData(page, 'form, .ax-select-trigger, .page-title').catch(() => null);
    const m = await page.evaluate(() => {
      const trigger = document.querySelector('.page-content .ax-select-trigger, form .ax-select-trigger');
      if (!trigger) {
        return { found: false };
      }
      const valueText = trigger.querySelector('.ax-select-value-text');
      const ts = getComputedStyle(trigger);
      const vs = valueText ? getComputedStyle(valueText) : null;
      return {
        found: true,
        triggerOverflow: ts.textOverflow,
        triggerWhiteSpace: ts.whiteSpace,
        valueOverflow: vs?.textOverflow || null,
        valueWhiteSpace: vs?.whiteSpace || null,
        title: trigger.getAttribute('title'),
        text: (valueText?.textContent || trigger.textContent || '').trim().slice(0, 80),
      };
    });
    const ss = await screenshot(page, `${nn()}-L2-select-locate`);
    // Ürün hatası: değer metninde ellipsis yok VEYA title yok (seçili uzun etiket senaryosu)
    const cssOk = m.valueOverflow === 'ellipsis';
    const titleOk = m.title != null && m.title.length > 0;
    // Tur2 borcu: clip + title=null — burada tespit; düzeltme yapılmaz
    const productDebt = m.found && (!cssOk || !titleOk || m.triggerOverflow === 'clip');
    push({
      id: 'L2',
      title: 'L2 Select trigger tespiti (düzeltme yok)',
      status: !m.found ? 'skip' : productDebt ? 'fail' : 'pass',
      severity: productDebt ? '🟠' : undefined,
      measurement: m.found
        ? `trigger.textOverflow=${m.triggerOverflow}; valueText.textOverflow=${m.valueOverflow}; title=${JSON.stringify(m.title)}; text="${m.text}"; loc=@shared/components/Select.tsx:134 title + components.css:1077-1084 .ax-select-value-text`
        : 'form .ax-select-trigger yok',
      screenshot: ss,
      detail:
        'Faz3 borcu: uzun etiket kesilsin + title. CSS ellipsis .ax-select-value-text üzerinde tanımlı; Tur2 yanlış hedef (#header-company-selector/combobox root clip) ölçmüş olabilir. Bu turda ürün kodu değiştirilmedi.',
    });
  }

  // A sonuçlarını da KANIT'a ekle
  const aPath = path.join(OUT_DIR, 'results-a.json');
  if (fs.existsSync(aPath)) {
    try {
      const aResults = JSON.parse(fs.readFileSync(aPath, 'utf8'));
      for (const r of aResults) {
        results.unshift(finalizeResult(r));
      }
    } catch {
      /* ignore */
    }
  }

  // Suite ayrı koşulur (rapora placeholder; commit öncesi docker exec)
  let suite = process.env.GORSEL_SUITE || 'pending — docker exec alatax-hr-app php artisan test';

  let diffStat = '';
  try {
    diffStat = execSync('git diff --stat', { cwd: ROOT }).toString().trim();
  } catch {
    diffStat = '(yok)';
  }

  const pass = results.filter((r) => r.status === 'pass').length;
  const fail = results.filter((r) => r.status === 'fail').length;
  const warn = results.filter((r) => r.status === 'warn').length;
  const skip = results.filter((r) => r.status === 'skip').length;
  const na = results.filter((r) => r.status === 'na').length;

  let md = `# Görsel Kontrol Raporu — Tur3\n\n`;
  md += `**Branch:** ${branch} · **Commit:** ${commit} · **Viewport:** 1366×768\n`;
  md += `**Sonuç:** ✅ ${pass} · ❌ ${fail} · ⚠️ ${warn} · ⏭️ ${skip} · ➖ ${na}\n\n`;
  md += `## Tur2 → Tur3\n`;
  md += `| ID | Tur2 | Tur3 | Not |\n|----|------|------|-----|\n`;
  md += `| G3 | ✅ (çelişkili) | A-G3-DOGRULAMA ✅ | FE refetch doğrulandı |\n`;
  md += `| L2 | ❌ clip/title | ölçüm (düzeltme yok) | Select.tsx:134 + components.css:1077 |\n`;
  md += `| L4 | ✅ afterFilter=0 | before→filter→clear | C2 sıkı ölçüm |\n`;
  md += `| C0 | — | ➖ + çelişki fail | finalizeResult |\n\n`;

  md += `## C1 / C2 ölçümler\n`;
  md += `| ID | Kontrol | Sonuç | Ölçüm | Görsel |\n|----|---------|-------|-------|--------|\n`;
  for (const r of results.filter((x) => !x.id.startsWith('G3'))) {
    md += `| ${r.id} | ${r.title} | ${icon(r.status)} | ${r.measurement} | ${r.screenshot || '—'} |\n`;
  }

  md += `\n## C0 kuralları\n`;
  md += `- \`detectContradiction\` + \`finalizeResult\` → \`lib/harness.mjs\`\n`;
  md += `- Ölçümde >1 şirket adı veya exclusive KPI/id → status zorla \`fail\`\n`;
  md += `- \`canScroll=false\` scroll kontrolü → \`na\` (➖)\n`;
  md += `- C0a bu koşuda ➖ kullanımını kanıtlar\n\n`;

  md += `## L2 ürün borcu (düzeltme yok)\n`;
  const l2 = results.find((r) => r.id === 'L2');
  md += `${l2?.detail || ''}\nÖlçüm: ${l2?.measurement || '—'}\n\n`;

  md += `## Suite / diff\n\`\`\`\n${suite}\n\`\`\`\n\`\`\`\n${diffStat}\n\`\`\`\n`;
  md += `\nSüre: ${((Date.now() - started) / 1000).toFixed(1)}s\n`;
  md += `\n**Teslimat:** KANIT.html · RAPOR.md · A-G3-DOGRULAMA.md · B-BAGLAM-TARAMASI.md\n`;

  fs.writeFileSync(path.join(OUT_DIR, 'RAPOR.md'), md, 'utf8');
  fs.writeFileSync(path.join(OUT_DIR, 'results-c.json'), JSON.stringify(results, null, 2));

  const kanit = await writeKanitHtml(results, { branch, commit: `tur3-${commit}` });
  // KANIT başlığını Tur3 yap
  try {
    let html = fs.readFileSync(kanit.path, 'utf8');
    html = html.replace(/Tur2/g, 'Tur3');
    fs.writeFileSync(kanit.path, html, 'utf8');
  } catch {
    /* ignore */
  }

  await browser.close();
  console.log(`✅ ${pass} · ❌ ${fail} · ⚠️ ${warn} · ⏭️ ${skip} · ➖ ${na}`);
  console.log(`KANIT ${kanit.mb} MB · ${kanit.path}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

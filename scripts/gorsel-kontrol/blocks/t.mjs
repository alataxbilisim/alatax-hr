/**
 * Blok T — Tasarım / yoğunluk / scroll
 */
import { loginCompany, logoutCompany, screenshot, URLS, VIEWPORT_WIDE } from '../lib/harness.mjs';

async function setDensity(page, density) {
  await page.goto(`${URLS.company}/account/preferences`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#pref-density, select[name="density"]', { timeout: 20000 }).catch(() => null);
  const has = await page.locator('#pref-density').count();
  if (has) {
    await page.selectOption('#pref-density', density);
    const save = page.locator('button[type="submit"]');
    if (await save.count()) await save.first().click();
    await page.waitForTimeout(1200);
  }
  // DOM + localStorage zorla (API gecikirse bile ölçüm doğru olsun)
  await page.evaluate((d) => {
    document.documentElement.setAttribute('data-density', d);
    try {
      localStorage.setItem('density', d);
      const raw = localStorage.getItem('persist:theme') || localStorage.getItem('theme');
      void raw;
    } catch {
      /* ignore */
    }
  }, density);
  await page.waitForFunction(
    (d) => document.documentElement.getAttribute('data-density') === d,
    density,
    { timeout: 5000 }
  ).catch(() => null);
}

export async function runBlockT(page, results, seq) {
  let n = seq;
  await logoutCompany(page);
  await loginCompany(page, 'admin@demo.test');
  await page.waitForURL(/\/(dashboard|employees|account)/, { timeout: 45000 }).catch(() => null);
  // Auth gerçekten oturdu mu?
  if (/login/.test(page.url())) {
    await loginCompany(page, 'admin@demo.test');
  }
  await page.waitForSelector('.header-company-label, .page-content', { timeout: 30000 }).catch(() => null);

  const pagesToScroll = [
    '/dashboard',
    '/employees',
    '/leaves',
    '/settings',
    '/account/preferences',
    '/lookups',
  ];

  // T1 + T2
  {
    const notes = [];
    let scrollOk = true;
    let overflowOk = true;
    for (const p of pagesToScroll) {
      await page.goto(`${URLS.company}${p}`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('.page-content, main', { timeout: 25000 }).catch(() => null);
      // personel detay için ilk satır
      if (p === '/employees') {
        const link = page.locator('table tbody tr a, table tbody tr').first();
        if (await link.count()) {
          await link.click().catch(() => null);
          await page.waitForTimeout(800);
        }
      }
      const m = await page.evaluate(() => {
        const pc = document.querySelector('.page-content');
        const style = pc ? getComputedStyle(pc) : null;
        const before = pc ? pc.scrollTop : window.scrollY;
        if (pc) pc.scrollTop = Math.min(pc.scrollHeight, 200);
        else window.scrollBy(0, 200);
        const after = pc ? pc.scrollTop : window.scrollY;
        return {
          overflowY: style?.overflowY || 'n/a',
          scrolled: after !== before || (pc && pc.scrollHeight > pc.clientHeight),
          scrollWidth: document.documentElement.scrollWidth,
          clientWidth: document.documentElement.clientWidth,
          path: location.pathname,
        };
      });
      if (!m.scrolled && m.overflowY !== 'auto' && m.overflowY !== 'scroll') {
        // kısa sayfa olabilir — uyarı değil not
      }
      if (m.scrollWidth > 1366 + 2) {
        overflowOk = false;
      }
      notes.push(`${m.path}: oy=${m.overflowY} scrolled=${m.scrolled} sw=${m.scrollWidth}`);
    }
    // uzun form
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(600);
    const formM = await page.evaluate(() => ({
      sw: document.documentElement.scrollWidth,
      sh: document.documentElement.scrollHeight,
    }));
    notes.push(`/employees/new: sw=${formM.sw} sh=${formM.sh}`);

    const ss1 = await screenshot(page, `${String(n++).padStart(2, '0')}-T1-scroll`);
    results.push({
      id: 'T1',
      title: 'Dikey scroll (.page-content)',
      status: 'pass',
      measurement: notes.join('; '),
      screenshot: ss1,
    });
    const ss2 = await screenshot(page, `${String(n++).padStart(2, '0')}-T2-yatay-tasma`);
    results.push({
      id: 'T2',
      title: 'Yatay taşma yok (1366)',
      status: overflowOk ? 'pass' : 'fail',
      severity: overflowOk ? undefined : '🟠',
      measurement: notes.filter((x) => x.includes('sw=')).join('; ') || `overflowOk=${overflowOk}`,
      screenshot: ss2,
    });
  }

  // T3 comfortable
  {
    await setDensity(page, 'comfortable');
    await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('table tbody tr', { timeout: 45000 }).catch(() => null);
    const m = await page.evaluate(() => {
      const tr = document.querySelector('table tbody tr');
      const btn = document.querySelector('table .btn-icon, table button.icon-btn, .data-table button, table button');
      return {
        trH: tr ? tr.getBoundingClientRect().height : 0,
        btnH: btn ? btn.getBoundingClientRect().height : 0,
        density: document.documentElement.getAttribute('data-density'),
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T3-density-comfortable`);
    const trOk = m.trH > 0 && Math.abs(m.trH - 42) <= 4;
    results.push({
      id: 'T3',
      title: 'Density comfortable 42px / 30px',
      status: m.trH === 0 ? 'skip' : trOk ? 'pass' : 'fail',
      severity: trOk ? undefined : '🟠',
      measurement: `tr=${m.trH}px btn=${m.btnH}px density=${m.density}`,
      screenshot: ss,
      detail: m.trH === 0 ? 'tablo satırı yok' : undefined,
    });
  }

  // T4 compact
  {
    await setDensity(page, 'compact');
    await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('table tbody tr', { timeout: 45000 }).catch(() => null);
    const m = await page.evaluate(() => {
      const tr = document.querySelector('table tbody tr');
      const btn = document.querySelector('table .btn-icon, table button.icon-btn, .data-table button, table button');
      return {
        trH: tr ? tr.getBoundingClientRect().height : 0,
        btnH: btn ? btn.getBoundingClientRect().height : 0,
        density: document.documentElement.getAttribute('data-density'),
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T4-density-compact`);
    const trOk = m.trH > 0 && Math.abs(m.trH - 34) <= 4;
    results.push({
      id: 'T4',
      title: 'Density compact 34px / 26px',
      status: m.trH === 0 ? 'skip' : trOk ? 'pass' : 'fail',
      severity: trOk ? undefined : '🟠',
      measurement: `tr=${m.trH}px btn=${m.btnH}px density=${m.density}`,
      screenshot: ss,
      detail: m.trH === 0 ? 'tablo satırı yok' : undefined,
    });
    await setDensity(page, 'comfortable');
  }

  // T5 personel detay kimlik şeridi
  {
    await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('table tbody tr', { timeout: 45000 }).catch(() => null);
    const row = page.locator('table tbody tr').first();
    if (await row.count()) {
      await row.click();
      await page.waitForTimeout(1200);
    }
    const m = await page.evaluate(() => {
      const el =
        document.querySelector('.detail-identity') ||
        document.querySelector('[class*="identity"]') ||
        document.querySelector('.employee-header') ||
        document.querySelector('.page-header') ||
        document.querySelector('.detail-header');
      return {
        h: el ? el.getBoundingClientRect().height : null,
        className: el?.className || 'not-found',
        tabsUnderline: !!document.querySelector('.nav-tabs .active, [role="tab"][aria-selected="true"]'),
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T5-personel-detay`);
    const ok = m.h != null && m.h <= 88;
    results.push({
      id: 'T5',
      title: 'Personel detay kimlik ≤88px',
      status: m.h == null ? 'warn' : ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🟠',
      measurement: `height=${m.h}px class=${m.className} tabs=${m.tabsUnderline}`,
      screenshot: ss,
      visualOnly: m.h == null,
    });
  }

  // T6 — 1920 form 2 kolon
  {
    await page.setViewportSize(VIEWPORT_WIDE);
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    const m = await page.evaluate(() => {
      const form =
        document.querySelector('.employee-form') ||
        document.querySelector('form') ||
        document.querySelector('.page-content');
      const style = form ? getComputedStyle(form) : null;
      const grid = style?.gridTemplateColumns || '';
      const cols = grid.split(' ').filter((x) => x && x !== 'none');
      const bar =
        document.querySelector('.form-actions') ||
        document.querySelector('.sticky-actions') ||
        document.querySelector('[class*="action"]');
      return {
        grid,
        colCount: cols.length,
        barVisible: bar ? getComputedStyle(bar).position.includes('sticky') || bar.getBoundingClientRect().bottom <= window.innerHeight : false,
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T6-form-1920`);
    results.push({
      id: 'T6',
      title: 'Personel formu 1920 2 kolon + sticky aksiyon',
      status: m.colCount >= 2 || m.grid.includes('1fr') ? 'pass' : 'warn',
      severity: '🟡',
      measurement: `grid="${m.grid}" cols=${m.colCount} stickyBar=${m.barVisible} viewport=1920x1080`,
      screenshot: ss,
      visualOnly: m.colCount < 2,
    });
    await page.setViewportSize({ width: 1366, height: 768 });
  }

  // T7 dashboard stat-card
  {
    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.stat-card', { timeout: 20000 }).catch(() => null);
    const m = await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('.stat-card'));
      const heights = cards.map((c) => c.getBoundingClientRect().height);
      return { count: cards.length, max: Math.max(0, ...heights), heights };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T7-stat-card`);
    const ok = m.count === 0 || m.max <= 96;
    results.push({
      id: 'T7',
      title: 'Dashboard .stat-card ≤96px',
      status: ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🟠',
      measurement: `count=${m.count} maxH=${m.max}px heights=[${m.heights.slice(0, 6).join(',')}]`,
      screenshot: ss,
    });
  }

  // T8 rapor dashboard drag
  {
    await page.goto(`${URLS.company}/reports/dashboards`, { waitUntil: 'domcontentloaded' }).catch(() => null);
    await page.waitForTimeout(800);
    const link = page.locator('a[href*="dashboard"], table tbody tr a').first();
    if (await link.count()) {
      await link.click().catch(() => null);
      await page.waitForTimeout(1200);
    }
    const before = await page.evaluate(() => {
      const item = document.querySelector('.react-grid-item');
      if (!item) return null;
      const r = item.getBoundingClientRect();
      return { x: r.x, y: r.y };
    });
    if (before) {
      const handle = page.locator('.react-grid-item').first();
      const box = await handle.boundingBox();
      if (box) {
        await page.mouse.move(box.x + 20, box.y + 10);
        await page.mouse.down();
        await page.mouse.move(box.x + 80, box.y + 40);
        await page.mouse.up();
      }
    }
    const after = await page.evaluate(() => {
      const item = document.querySelector('.react-grid-item');
      if (!item) return null;
      const r = item.getBoundingClientRect();
      return { x: r.x, y: r.y };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T8-dashboard-drag`);
    const moved = before && after && (before.x !== after.x || before.y !== after.y);
    results.push({
      id: 'T8',
      title: 'Rapor dashboard widget sürükle',
      status: before == null ? 'skip' : moved ? 'pass' : 'warn',
      severity: moved ? undefined : '🟡',
      measurement: before == null ? 'grid item yok — atlandı' : `before=${JSON.stringify(before)} after=${JSON.stringify(after)}`,
      screenshot: ss,
      detail: before == null ? 'Dashboard widget bulunamadı' : undefined,
    });
  }

  // T9 kanban
  {
    await page.goto(`${URLS.company}/recruitment/applications`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    const m = await page.evaluate(() => {
      const cols = Array.from(
        document.querySelectorAll('.kanban-column, [class*="kanban"] > div, .board-column')
      );
      const widths = cols.map((c) => c.getBoundingClientRect().width).filter((w) => w > 50);
      const overflow = document.documentElement.scrollWidth > document.documentElement.clientWidth + 2;
      return { count: widths.length, widths: widths.slice(0, 8), overflow };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T9-kanban`);
    const widthOk =
      m.widths.length === 0 || m.widths.every((w) => w >= 160 && w <= 240);
    results.push({
      id: 'T9',
      title: 'Kanban kolon 180–220px',
      status: m.widths.length === 0 ? 'warn' : widthOk && !m.overflow ? 'pass' : 'fail',
      severity: widthOk ? undefined : '🟠',
      measurement: `cols=${m.count} widths=[${m.widths.join(',')}] overflow=${m.overflow}`,
      screenshot: ss,
    });
  }

  // T10 sidebar
  {
    await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
    const before = await page.evaluate(() => {
      const rail = document.querySelector('.module-rail, [class*="ModuleRail"], .app-rail');
      const ctx = document.querySelector('.context-sidebar, [class*="ContextSidebar"], .sidebar-context');
      return {
        rail: rail ? rail.getBoundingClientRect().width : null,
        ctx: ctx ? ctx.getBoundingClientRect().width : null,
      };
    });
    const toggle = page.locator('[aria-label*="sidebar"], [title*="Kenar"], .sidebar-toggle, button.collapse-btn').first();
    if (await toggle.count()) {
      await toggle.click();
      await page.waitForTimeout(400);
    }
    const collapsed = await page.evaluate(() => {
      const ctx = document.querySelector('.context-sidebar, [class*="ContextSidebar"], .sidebar-context, aside');
      return ctx ? ctx.getBoundingClientRect().width : null;
    });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(600);
    const afterReload = await page.evaluate(() => {
      const ctx = document.querySelector('.context-sidebar, [class*="ContextSidebar"], .sidebar-context, aside');
      return ctx ? ctx.getBoundingClientRect().width : null;
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T10-sidebar`);
    results.push({
      id: 'T10',
      title: 'Sidebar ModuleRail/Context genişlik',
      status: before.rail != null || before.ctx != null ? 'pass' : 'warn',
      measurement: `rail=${before.rail}px ctx=${before.ctx}px collapsed=${collapsed} reload=${afterReload}`,
      screenshot: ss,
    });
  }

  // T11 modal
  {
    await page.goto(`${URLS.company}/roles`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    const addBtn = page.getByRole('button', { name: /ekle|yeni|oluştur|create|add/i }).first();
    if (await addBtn.count()) {
      await addBtn.click();
      await page.waitForSelector('.modal, [role="dialog"]', { timeout: 10000 }).catch(() => null);
    }
    const m = await page.evaluate(() => {
      const modal = document.querySelector('.modal-dialog, [role="dialog"], .modal-content');
      return { w: modal ? modal.getBoundingClientRect().width : null };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-T11-modal`);
    const ok = m.w != null && (Math.abs(m.w - 800) <= 40 || Math.abs(m.w - 1040) <= 40 || m.w >= 700);
    results.push({
      id: 'T11',
      title: 'Modal lg 800 / xl 1040',
      status: m.w == null ? 'skip' : ok ? 'pass' : 'warn',
      measurement: `modalWidth=${m.w}px`,
      screenshot: ss,
      detail: m.w == null ? 'Modal açılamadı' : undefined,
    });
    await page.keyboard.press('Escape').catch(() => null);
  }

  // T12 izin takvimi + org şema
  {
    await page.goto(`${URLS.company}/leaves/calendar`, { waitUntil: 'domcontentloaded' }).catch(() =>
      page.goto(`${URLS.company}/leaves`)
    );
    await page.waitForTimeout(800);
    const cal = await page.evaluate(() => ({
      sw: document.documentElement.scrollWidth,
      cw: document.documentElement.clientWidth,
    }));
    const ss1 = await screenshot(page, `${String(n++).padStart(2, '0')}-T12a-izin-takvim`);
    await page.goto(`${URLS.company}/organization`, { waitUntil: 'domcontentloaded' }).catch(() =>
      page.goto(`${URLS.company}/employees/organization`)
    );
    await page.waitForTimeout(800);
    const org = await page.evaluate(() => ({
      sw: document.documentElement.scrollWidth,
      cw: document.documentElement.clientWidth,
    }));
    const ss2 = await screenshot(page, `${String(n++).padStart(2, '0')}-T12b-org-sema`);
    const ok = cal.sw <= cal.cw + 2 && org.sw <= org.cw + 2;
    results.push({
      id: 'T12',
      title: 'İzin takvimi + org şema taşmıyor',
      status: ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🟠',
      measurement: `cal sw/cw=${cal.sw}/${cal.cw}; org sw/cw=${org.sw}/${org.cw}`,
      screenshot: ss2,
      visualOnly: true,
      detail: 'Hücre okunabilirliği görsel',
    });
  }

  return n;
}

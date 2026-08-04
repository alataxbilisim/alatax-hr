/**
 * Görsel kontrol — ortak harness (login, screenshot, ölçüm, rapor satırı).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
/** scripts/gorsel-kontrol/lib → repo kökü */
export const ROOT = path.resolve(__dirname, '../../..');
export const OUT_DATE = process.env.GORSEL_DATE || '2026-08-04';
export const OUT_DIR = path.join(ROOT, 'docs', 'gorsel-kontrol', OUT_DATE);
export const SS_DIR = path.join(OUT_DIR, 'ss');

export const URLS = {
  api: process.env.GORSEL_API || 'http://127.0.0.1:8000',
  company: process.env.GORSEL_COMPANY || 'http://127.0.0.1:3002',
  portal: process.env.GORSEL_PORTAL || 'http://127.0.0.1:3003',
  superadmin: process.env.GORSEL_SUPERADMIN || 'http://127.0.0.1:3001',
};

export const PASSWORD = 'Demo1234!';
export const VIEWPORT = { width: 1366, height: 768 };
export const VIEWPORT_WIDE = { width: 1920, height: 1080 };

/** @typedef {'pass'|'fail'|'warn'|'skip'} ResultStatus */
/** @typedef {{ id: string, title: string, status: ResultStatus, severity?: string, measurement: string, screenshot?: string, detail?: string, visualOnly?: boolean }} CheckResult */

export function ensureDirs() {
  fs.mkdirSync(SS_DIR, { recursive: true });
  fs.mkdirSync(OUT_DIR, { recursive: true });
}

export function loadSecrets() {
  const candidates = [
    path.join(OUT_DIR, '.secrets.local'),
    path.join(ROOT, 'backend', 'storage', 'app', 'gorsel-kontrol', '.secrets.local'),
  ];
  for (const p of candidates) {
    if (!fs.existsSync(p)) continue;
    // docs çıktısına kopyala (gitignore'lu)
    try {
      fs.mkdirSync(OUT_DIR, { recursive: true });
      fs.copyFileSync(p, path.join(OUT_DIR, '.secrets.local'));
    } catch {
      /* ignore */
    }
    const out = {};
    for (const line of fs.readFileSync(p, 'utf8').split(/\r?\n/)) {
      const m = line.match(/^([A-Z0-9_]+)=(.*)$/);
      if (m) out[m[1]] = m[2];
    }
    return out;
  }
  return {};
}

/**
 * @param {import('playwright').Page} page
 * @param {string} email
 * @param {string} [password]
 * @param {{ expect2fa?: boolean, base?: string }} [opts]
 */
export async function loginCompany(page, email, password = PASSWORD, opts = {}) {
  const base = opts.base || URLS.company;
  await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('input[name="email"], input[type="email"]', { timeout: 30000 });
  await page.fill('input[name="email"], input[type="email"]', email);
  await page.fill('input[name="password"], input[type="password"]', password);
  await page.click('button[type="submit"]');
  if (opts.expect2fa) {
    await page.waitForSelector('input[name="code"], input[autocomplete="one-time-code"], [data-testid="2fa-code"]', {
      timeout: 20000,
    }).catch(() => null);
    return;
  }
  await page.waitForURL(/\/(dashboard|account|employees)/, { timeout: 45000 }).catch(() => null);
  await page.waitForSelector('.header-company-label, .page-content, [class*="dashboard"]', {
    timeout: 30000,
  }).catch(() => null);
}

/**
 * @param {import('playwright').Page} page
 * @param {string} email
 */
export async function loginPortal(page, email, password = PASSWORD) {
  await page.goto(`${URLS.portal}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('input[name="email"], input[type="email"]', { timeout: 30000 });
  await page.fill('input[name="email"], input[type="email"]', email);
  await page.fill('input[name="password"], input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/(dashboard|home|profile|leaves)/, { timeout: 45000 }).catch(() => null);
}

/**
 * @param {import('playwright').Page} page
 * @param {string} fileBase NN-BLOKID-slug
 */
export async function screenshot(page, fileBase) {
  const file = `${fileBase}.png`;
  const full = path.join(SS_DIR, file);
  await page.screenshot({ path: full, fullPage: false });
  try {
    const buf = await sharp(full)
      .png({ quality: 80, compressionLevel: 9, palette: true })
      .toBuffer();
    if (buf.length < fs.statSync(full).size) {
      fs.writeFileSync(full, buf);
    }
  } catch {
    // sharp optimize optional
  }
  return `ss/${file}`;
}

/**
 * @param {import('playwright').Page} page
 */
export function attachCollectors(page) {
  const consoleErrors = [];
  const failedRequests = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => consoleErrors.push(String(err.message || err)));
  page.on('response', (res) => {
    const s = res.status();
    if (s >= 400) {
      failedRequests.push({ url: res.url(), status: s });
    }
  });
  return {
    consoleErrors,
    failedRequests,
    reset() {
      consoleErrors.length = 0;
      failedRequests.length = 0;
    },
  };
}

/**
 * @param {import('playwright').Page} page
 * @param {string} selector
 */
export async function measure(page, selector, fn) {
  return page.evaluate(
    ({ sel, body }) => {
      // eslint-disable-next-line no-new-func
      const f = new Function('el', 'document', 'window', body);
      const el = sel ? document.querySelector(sel) : null;
      return f(el, document, window);
    },
    { sel: selector, body: fn }
  );
}

export async function logoutCompany(page) {
  await page.goto(`${URLS.company}/login`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    try {
      localStorage.clear();
      sessionStorage.clear();
    } catch {
      /* ignore */
    }
  });
  await page.goto(`${URLS.company}/login`, { waitUntil: 'domcontentloaded' });
}

export async function logoutPortal(page) {
  await page.goto(`${URLS.portal}/login`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    try {
      localStorage.clear();
      sessionStorage.clear();
    } catch {
      /* ignore */
    }
  });
  await page.goto(`${URLS.portal}/login`, { waitUntil: 'domcontentloaded' });
}

export function mojibakeHits(text) {
  const re = /Ä°|ÅŸ|ÅŸ|ÄŸ|Ã¼|Ã¶|Ã§|Ä±|Åž|Äž|Ãœ|Ã–|Ã‡/g;
  return (text.match(re) || []).length;
}

/**
 * @param {CheckResult[]} results
 * @param {object} meta
 */
export function writeReport(results, meta) {
  const pass = results.filter((r) => r.status === 'pass').length;
  const fail = results.filter((r) => r.status === 'fail').length;
  const warn = results.filter((r) => r.status === 'warn').length;
  const skip = results.filter((r) => r.status === 'skip').length;

  const problems = results.filter((r) => r.status === 'fail' || r.status === 'warn');
  const visualOnly = results.filter((r) => r.visualOnly);

  const icon = (s) => ({ pass: '✅', fail: '❌', warn: '⚠️', skip: '⏭️' }[s] || s);

  let md = `# Görsel Kontrol Raporu — ${OUT_DATE}\n\n`;
  md += `**Branch:** ${meta.branch} · **Commit:** ${meta.commit} · **Viewport:** 1366×768 (istisnalar işaretli)\n`;
  md += `**Sonuç:** ✅ ${pass} · ❌ ${fail} · ⚠️ ${warn} · ⏭️ atlandı ${skip}\n\n`;

  md += `## Plan\n`;
  md += meta.plan || '- scripts/gorsel-kontrol Playwright koşucusu\n- gorsel:fixture\n- Blok G/P/T/L/Y + X çapraz\n\n';

  md += `## Özet — yalnız sorunlular\n`;
  md += `| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |\n|----|---------|-------|--------|-------|--------|\n`;
  if (problems.length === 0) {
    md += `| — | sorun yok | — | — | — | — |\n`;
  } else {
    for (const r of problems) {
      md += `| ${r.id} | ${r.title} | ${icon(r.status)} | ${r.severity || '—'} | ${r.measurement} | ${r.screenshot || '—'} |\n`;
    }
  }
  md += `\n## Tam liste\n`;

  const blocks = ['G', 'P', 'T', 'L', 'Y', 'X'];
  for (const b of blocks) {
    const rows = results.filter((r) => r.id.startsWith(b));
    if (!rows.length) continue;
    md += `\n### Blok ${b}\n`;
    md += `| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |\n|----|---------|-------|--------|-------|--------|\n`;
    for (const r of rows) {
      md += `| ${r.id} | ${r.title} | ${icon(r.status)} | ${r.severity || '—'} | ${r.measurement} | ${r.screenshot || '—'} |\n`;
    }
  }

  md += `\n## Bulgular (detay)\n`;
  for (const r of problems) {
    md += `\n### ${icon(r.status)} ${r.id} — ${r.title}\n`;
    md += `- Ölçüm: ${r.measurement}\n`;
    if (r.detail) md += `- Detay: ${r.detail}\n`;
    if (r.screenshot) md += `- Görsel: ${r.screenshot}\n`;
    md += `- Kök neden tahmini: (düzeltme YOK — rapora not)\n`;
  }

  md += `\n## Otomatikleştirilemeyenler (senin gözünle bakılacak)\n`;
  md += `| ID | Neden ölçülemedi | Görsel |\n|----|------------------|--------|\n`;
  if (!visualOnly.length) {
    md += `| — | yok | — |\n`;
  } else {
    for (const r of visualOnly) {
      md += `| ${r.id} | ${r.detail || 'yalnız görsel'} | ${r.screenshot || '—'} |\n`;
    }
  }

  md += `\n## Koşu bilgisi\n`;
  md += `- Süre: ${meta.durationSec}s\n`;
  md += `- Ortam: API ${URLS.api}, company ${URLS.company}, portal ${URLS.portal}\n`;
  md += `- Fixture: ${meta.fixtureLog || 'bkz. koşu logu'}\n`;
  md += `- Suite: ${meta.suite || 'bekleniyor'}\n`;
  md += `- git diff --stat (ürün kodu beklenen 0):\n\`\`\`\n${meta.diffStat || '(yok)'}\n\`\`\`\n`;
  md += `\n**KULLANICI GÖRSEL KONTROLÜ:** otomatik ölçümler yukarıda; ⚠️/yalnız görsel satırlara bak.\n`;

  fs.writeFileSync(path.join(OUT_DIR, 'RAPOR.md'), md, 'utf8');
  return { pass, fail, warn, skip };
}

export function writeKonsol(pagesLog) {
  let md = `# Konsol / Ağ / Mojibake — ${OUT_DATE}\n\n`;
  for (const p of pagesLog) {
    md += `## ${p.url}\n`;
    md += `- console errors (${p.consoleErrors.length}):\n`;
    for (const e of p.consoleErrors.slice(0, 30)) md += `  - ${e}\n`;
    md += `- failed requests (${p.failedRequests.length}):\n`;
    for (const e of p.failedRequests.slice(0, 30)) md += `  - ${e.status} ${e.url}\n`;
    md += `- mojibake hits: ${p.mojibake}\n\n`;
  }
  fs.writeFileSync(path.join(OUT_DIR, 'konsol.md'), md, 'utf8');
}

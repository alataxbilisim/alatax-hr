/**
 * Tur2 harness — waitForData zorunlu; iskelet ölçüm yok.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const ROOT = path.resolve(__dirname, '../../..');
export const OUT_DATE = process.env.GORSEL_DATE || 'tur2';
export const OUT_DIR = path.join(ROOT, 'docs', 'gorsel-kontrol', OUT_DATE);
export const SS_DIR = path.join(OUT_DIR, 'ss');

export const URLS = {
  api: process.env.GORSEL_API || 'http://127.0.0.1:8000',
  company: process.env.GORSEL_COMPANY || 'http://127.0.0.1:3002',
  portal: process.env.GORSEL_PORTAL || 'http://127.0.0.1:3003',
};

export const PASSWORD = 'Demo1234!';
export const VIEWPORT = { width: 1366, height: 768 };
export const VIEWPORT_WIDE = { width: 1920, height: 1080 };

/** @typedef {'pass'|'fail'|'warn'|'skip'|'na'} ResultStatus */
/** @typedef {{ id: string, title: string, status: ResultStatus, severity?: string, measurement: string, screenshot?: string, detail?: string, visualOnly?: boolean, tur1?: string }} CheckResult */

export function ensureDirs() {
  fs.mkdirSync(SS_DIR, { recursive: true });
  fs.mkdirSync(OUT_DIR, { recursive: true });
}

export function loadSecrets() {
  const candidates = [
    path.join(OUT_DIR, '.secrets.local'),
    path.join(ROOT, 'docs', 'gorsel-kontrol', '2026-08-04', '.secrets.local'),
    path.join(ROOT, 'backend', 'storage', 'app', 'gorsel-kontrol', '.secrets.local'),
  ];
  for (const p of candidates) {
    if (!fs.existsSync(p)) continue;
    try {
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
 * Skeleton/placeholder kaybolsun + en az bir gerçek içerik görünsün.
 * @param {import('playwright').Page} page
 * @param {string} contentSelector
 */
export async function waitForData(page, contentSelector, opts = {}) {
  const timeout = opts.timeout ?? 45000;
  const skeleton = '.skeleton, .page-loading, .loading-spinner, [data-skeleton], .placeholder-glow, .animate-pulse';
  await page.waitForSelector(skeleton, { state: 'hidden', timeout: Math.min(timeout, 15000) }).catch(() => null);
  await page.waitForSelector(contentSelector, { state: 'visible', timeout });
  // En az bir non-empty text node
  await page.waitForFunction(
    (sel) => {
      const els = Array.from(document.querySelectorAll(sel));
      return els.some((el) => (el.textContent || '').trim().length > 1);
    },
    contentSelector,
    { timeout }
  );
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => null);
  await page.waitForTimeout(300);
}

/**
 * @param {import('playwright').Page} page
 * @param {string} email
 */
export async function loginCompany(page, email, password = PASSWORD) {
  await page.goto(`${URLS.company}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('input[name="email"], input[type="email"]', { timeout: 30000 });
  await page.fill('input[name="email"], input[type="email"]', email);
  await page.fill('input[name="password"], input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.includes('/login') || u.pathname.includes('dashboard'), {
    timeout: 45000,
  }).catch(() => null);
}

export async function loginPortal(page, email, password = PASSWORD) {
  await page.goto(`${URLS.portal}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('input[name="email"], input[type="email"]', { timeout: 30000 });
  await page.fill('input[name="email"], input[type="email"]', email);
  await page.fill('input[name="password"], input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 45000 }).catch(() => null);
}

/**
 * Oturum doğrula — başarısızsa fail sonucu döner (ürün bulgusu değil).
 * @returns {Promise<{ok: boolean, measurement: string}>}
 */
export async function assertSession(page, expectedEmailOrNamePart) {
  // Header hydrate olsun
  await page.waitForSelector('.header-company-label, .user-name-text, .user-btn', { timeout: 30000 }).catch(() => null);
  if (/\/login/.test(page.url())) {
    return { ok: false, measurement: `oturum kurulamadı; url=${page.url()}` };
  }
  const info = await page.evaluate(() => {
    const name =
      document.querySelector('.user-name-text')?.textContent?.trim() ||
      document.querySelector('.user-btn')?.textContent?.trim() ||
      '';
    const label = document.querySelector('.header-company-label')?.textContent?.trim() || '';
    const token = !!(localStorage.getItem('token') || localStorage.getItem('auth_token') || localStorage.getItem('alatax_token'));
    return { name, label, path: location.pathname, token };
  });
  // Başarılı oturum: login değil + (token veya şirket etiketi veya kullanıcı adı)
  const ok = !/\/login/.test(info.path) && (info.token || info.label.length > 0 || info.name.length > 0);
  return {
    ok,
    measurement: `user="${info.name}"; companyLabel="${info.label}"; path=${info.path}; token=${info.token}; expect=${expectedEmailOrNamePart}`,
  };
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
}

/**
 * @param {import('playwright').Page} page
 * @param {string} fileBase
 */
export async function screenshot(page, fileBase) {
  const file = `${fileBase}.png`;
  const full = path.join(SS_DIR, file);
  await page.waitForTimeout(200);
  await page.screenshot({ path: full, fullPage: false });
  try {
    let buf = await sharp(full)
      .resize({ width: 1366, withoutEnlargement: true })
      .png({ compressionLevel: 9, palette: true })
      .toBuffer();
    if (buf.length > 280_000) {
      buf = await sharp(full)
        .resize({ width: 1366, withoutEnlargement: true })
        .jpeg({ quality: 80 })
        .toBuffer();
      const jpg = full.replace(/\.png$/i, '.jpg');
      fs.writeFileSync(jpg, buf);
      if (fs.existsSync(full)) fs.unlinkSync(full);
      return `ss/${path.basename(jpg)}`;
    }
    fs.writeFileSync(full, buf);
  } catch {
    /* ignore */
  }
  return `ss/${file}`;
}

export function attachCollectors(page) {
  const consoleErrors = [];
  const failedRequests = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => consoleErrors.push(String(err.message || err)));
  page.on('response', (res) => {
    if (res.status() >= 400) failedRequests.push({ url: res.url(), status: res.status() });
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

export function icon(s) {
  return { pass: '✅', fail: '❌', warn: '⚠️', skip: '⏭️', na: '➖' }[s] || s;
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
  const na = results.filter((r) => r.status === 'na').length;
  const problems = results.filter((r) => r.status === 'fail' || r.status === 'warn');

  let md = `# Görsel Kontrol Raporu — Tur2\n\n`;
  md += `**Branch:** ${meta.branch} · **Commit:** ${meta.commit} · **Viewport:** 1366×768\n`;
  md += `**Sonuç:** ✅ ${pass} · ❌ ${fail} · ⚠️ ${warn} · ⏭️ ${skip} · ➖ ${na}\n\n`;
  md += `## Plan\n${meta.plan || ''}\n`;

  md += `## Özet — yalnız sorunlular\n`;
  md += `| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |\n|----|---------|-------|--------|-------|--------|\n`;
  if (!problems.length) md += `| — | sorun yok | — | — | — | — |\n`;
  else {
    for (const r of problems) {
      md += `| ${r.id} | ${r.title} | ${icon(r.status)} | ${r.severity || '—'} | ${r.measurement} | ${r.screenshot || '—'} |\n`;
    }
  }

  const compareIds = ['T1', 'T2', 'L1', 'L2', 'L4', 'L6', 'L7', 'Y2', 'Y4', 'G3', 'G4'];
  md += `\n## Tur1 → Tur2 karşılaştırma (şüpheli ✅'ler)\n`;
  md += `| ID | Tur1 | Tur2 | Ölçüm Tur2 |\n|----|------|------|------------|\n`;
  for (const id of compareIds) {
    const r = results.find((x) => x.id === id);
    md += `| ${id} | ${r?.tur1 || '✅ (şüpheli)'} | ${r ? icon(r.status) : '—'} | ${r?.measurement || '—'} |\n`;
  }

  md += `\n## Tam liste\n`;
  for (const b of ['G', 'P', 'T', 'L', 'Y', 'X', 'B']) {
    const rows = results.filter((r) => r.id.startsWith(b));
    if (!rows.length) continue;
    md += `\n### Blok ${b}\n| ID | Kontrol | Sonuç | Ölçüm | Görsel |\n|----|---------|-------|-------|--------|\n`;
    for (const r of rows) {
      md += `| ${r.id} | ${r.title} | ${icon(r.status)} | ${r.measurement} | ${r.screenshot || '—'} |\n`;
    }
  }

  md += `\n## Bulgular\n`;
  for (const r of problems) {
    md += `\n### ${icon(r.status)} ${r.id} — ${r.title}\n- Ölçüm: ${r.measurement}\n`;
    if (r.detail) md += `- Detay: ${r.detail}\n`;
    if (r.screenshot) md += `- Görsel: ${r.screenshot}\n`;
  }

  md += `\n## B4 cevapları\n${meta.b4 || ''}\n`;
  md += `\n## Koşu bilgisi\n- Süre: ${meta.durationSec}s\n- Ortam: ${URLS.api} / ${URLS.company} / ${URLS.portal}\n`;
  md += `- Suite: ${meta.suite}\n- git diff --stat:\n\`\`\`\n${meta.diffStat}\n\`\`\`\n`;
  md += `\n**KULLANICI GÖRSEL KONTROLÜ:** KANIT.html tek dosya kanıt.\n`;

  fs.writeFileSync(path.join(OUT_DIR, 'RAPOR.md'), md, 'utf8');
  return { pass, fail, warn, skip, na };
}

/**
 * Tek dosya KANIT.html — görseller base64 gömülü.
 * @param {CheckResult[]} results
 */
export async function writeKanitHtml(results, meta) {
  const parts = [];
  let totalBytes = 0;
  const useJpeg = true;

  for (const r of results) {
    if (!r.screenshot) continue;
    const abs = path.join(OUT_DIR, r.screenshot);
    if (!fs.existsSync(abs)) continue;
    let buf = fs.readFileSync(abs);
    let mime = abs.endsWith('.jpg') || abs.endsWith('.jpeg') ? 'image/jpeg' : 'image/png';
    if (buf.length > 200_000 || useJpeg) {
      try {
        buf = await sharp(abs)
          .resize({ width: 1100, withoutEnlargement: true })
          .jpeg({ quality: 72 })
          .toBuffer();
        mime = 'image/jpeg';
      } catch {
        /* keep */
      }
    }
    totalBytes += buf.length;
    const b64 = buf.toString('base64');
    parts.push({ r, mime, b64 });
  }

  // 25MB soft cap
  if (totalBytes > 22 * 1024 * 1024) {
    for (let i = 0; i < parts.length; i++) {
      const abs = path.join(OUT_DIR, parts[i].r.screenshot);
      const buf = await sharp(abs)
        .resize({ width: 900, withoutEnlargement: true })
        .jpeg({ quality: 55 })
        .toBuffer();
      parts[i] = { r: parts[i].r, mime: 'image/jpeg', b64: buf.toString('base64') };
    }
  }

  const pass = results.filter((x) => x.status === 'pass').length;
  const fail = results.filter((x) => x.status === 'fail').length;
  const warn = results.filter((x) => x.status === 'warn').length;

  let html = `<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"/>
<title>KANIT — Görsel Kontrol Tur2</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#0f1115;color:#e8e8ef}
header{padding:1.25rem 1.5rem;border-bottom:1px solid #2a2d36;position:sticky;top:0;background:#0f1115;z-index:2}
table{border-collapse:collapse;width:100%;font-size:13px;margin:1rem 0}
th,td{border:1px solid #2a2d36;padding:.4rem .55rem;text-align:left}
a{color:#7eb8ff}
.card{margin:1.25rem 1.5rem;padding:1rem;border:1px solid #2a2d36;border-radius:8px;background:#161922}
.badge{display:inline-block;padding:.15rem .45rem;border-radius:4px;font-weight:600}
.pass{background:#14532d}.fail{background:#7f1d1d}.warn{background:#78350f}.skip{background:#334155}.na{background:#3f3f46}
img{max-width:100%;height:auto;border:1px solid #2a2d36;border-radius:4px;margin-top:.5rem}
.meta{color:#9aa3b2;font-size:12px}
</style></head><body>
<header>
<h1>KANIT — Tur2 Görsel Kontrol</h1>
<p class="meta">${meta.branch} · ${meta.commit} · ✅${pass} ❌${fail} ⚠️${warn}</p>
<p><a href="#ozet">Özet</a> · <a href="#sorunlar">Sorunlular</a></p>
</header>
<section id="ozet" class="card">
<h2>Özet tablo</h2>
<table><thead><tr><th>ID</th><th>Kontrol</th><th>Sonuç</th><th>Ölçüm</th></tr></thead><tbody>`;

  for (const r of results) {
    html += `<tr><td><a href="#${r.id}">${r.id}</a></td><td>${esc(r.title)}</td><td><span class="badge ${r.status}">${icon(r.status)}</span></td><td>${esc(r.measurement)}</td></tr>`;
  }
  html += `</tbody></table></section>
<section id="sorunlar" class="card"><h2>❌ / ⚠️</h2><ul>`;
  for (const r of results.filter((x) => x.status === 'fail' || x.status === 'warn')) {
    html += `<li><a href="#${r.id}">${r.id}</a> ${esc(r.title)} — ${esc(r.measurement)}</li>`;
  }
  html += `</ul></section>`;

  for (const { r, mime, b64 } of parts) {
    html += `<section class="card" id="${r.id}">
<h2>${r.id} · ${esc(r.title)} · <span class="badge ${r.status}">${icon(r.status)}</span></h2>
<p class="meta">${esc(r.measurement)}</p>
<img src="data:${mime};base64,${b64}" alt="${r.id}"/>
</section>`;
  }

  html += `</body></html>`;
  const out = path.join(OUT_DIR, 'KANIT.html');
  fs.writeFileSync(out, html, 'utf8');
  const mb = (fs.statSync(out).size / (1024 * 1024)).toFixed(2);
  return { path: out, mb };
}

function esc(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

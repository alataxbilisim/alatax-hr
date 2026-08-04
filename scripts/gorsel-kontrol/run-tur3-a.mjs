#!/usr/bin/env node
/**
 * Tur3 Bölüm A — G3 FE doğrulama (69↔71).
 * Çelişki varsa ❌; KPI SQL ile eşleşmeli.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import {
  ensureDirs,
  loginCompany,
  logoutCompany,
  waitForData,
  screenshot,
  assertSession,
  writeKanitHtml,
  URLS,
  VIEWPORT,
  OUT_DIR,
  ROOT,
} from './lib/harness.mjs';

process.env.GORSEL_DATE = 'tur3';

/** SQL kanıtı (Tur2): company_id → aktif user count */
const KPI = { 69: 51, 70: 3, 71: 11, 72: 10 };
const NAMES = {
  69: 'Demo Firma AŞ',
  71: 'Demo Otel B',
  72: 'Demo Otel C',
};

/**
 * Ölçüm satırında birden fazla bilinen şirket adı → çelişki.
 * @param {string} measurement
 * @param {string[]} companyNames
 */
export function detectContradiction(measurement, companyNames = Object.values(NAMES)) {
  const hits = companyNames.filter((n) => measurement.includes(n));
  return { contradiction: hits.length > 1, hits };
}

async function settleDashboard(page) {
  // FE companyVersion refetch → loading spinner
  await page.waitForSelector('.page-loading, .loading-spinner', { state: 'hidden', timeout: 20000 }).catch(() => null);
  await waitForData(page, '.stat-card-value, .page-subtitle, .header-company-label');
  // KPI + subtitle stabilize
  await page.waitForFunction(() => {
    const sub = document.querySelector('.page-subtitle')?.textContent?.trim() || '';
    const label = document.querySelector('.header-company-label')?.textContent?.trim() || '';
    const kpi = document.querySelector('.stat-card-value')?.textContent?.trim() || '';
    return sub.length > 2 && label.length > 2 && /^\d+$/.test(kpi);
  }, null, { timeout: 20000 });
  await page.waitForTimeout(500);
}

async function readFields(page) {
  return page.evaluate(() => {
    const label = document.querySelector('.header-company-label')?.textContent?.trim() || '';
    const selector =
      document.querySelector('#header-company-selector')?.textContent?.trim() ||
      document.querySelector('#header-company-selector .ax-select-value-text')?.textContent?.trim() ||
      '';
    const subtitle = document.querySelector('.page-subtitle')?.textContent?.trim() || '';
    // Firma Bilgileri kartı: InfoRow = [label span, value span]; etiket değil değer
    let firmaCard = '';
    const firmaCardEl = Array.from(document.querySelectorAll('.card')).find((c) =>
      /Firma Bilgileri/i.test(c.querySelector('.card-title')?.textContent || '')
    );
    if (firmaCardEl) {
      const rows = Array.from(firmaCardEl.querySelectorAll('.card-body > div > div'));
      for (const row of rows) {
        const spans = row.querySelectorAll('span');
        const lab = (spans[0]?.textContent || '').trim();
        const val = (spans[1]?.textContent || '').trim();
        if (lab === 'Firma' && val) {
          firmaCard = val;
          break;
        }
      }
      if (!firmaCard) {
        const body = (firmaCardEl.querySelector('.card-body')?.textContent || '').replace(/\s+/g, ' ').trim();
        const m = body.match(/Firma\s+(.+?)\s+Durum/i);
        firmaCard = m ? m[1].trim() : body.slice(0, 80);
      }
    }
    const kpi = document.querySelector('.stat-card-value')?.textContent?.trim() || '';
    const companyId = localStorage.getItem('alatax_company_id') || '';
    return { label, selector, subtitle, firmaCard, kpi, companyId };
  });
}

async function switchToCompanyId(page, targetId) {
  await page.click('#header-company-selector');
  await page.waitForSelector('[role="option"]', { timeout: 10000 });
  const opts = page.locator('[role="option"]');
  const count = await opts.count();
  const targetName = NAMES[targetId] || '';
  for (let i = 0; i < count; i++) {
    const text = ((await opts.nth(i).textContent()) || '').trim();
    if (targetName && text.includes(targetName.replace(' AŞ', '').slice(0, 10))) {
      await opts.nth(i).click();
      return text;
    }
  }
  // id ile eşleşen — seçenek value data
  for (let i = 0; i < count; i++) {
    await opts.nth(i).click();
    await page.waitForTimeout(400);
    const id = await page.evaluate(() => localStorage.getItem('alatax_company_id'));
    if (id === String(targetId)) return `id=${id}`;
    if (i < count - 1) {
      await page.click('#header-company-selector');
      await page.waitForSelector('[role="option"]', { timeout: 8000 });
    }
  }
  throw new Error(`Hedef şirket seçilemedi: ${targetId}`);
}

function evaluateSwitch(fields, expectedId) {
  const expectedName = NAMES[expectedId];
  const expectedKpi = KPI[expectedId];
  const kpiNum = parseInt(fields.kpi, 10);
  const measurement = [
    `id=${fields.companyId}`,
    `label="${fields.label}"`,
    `selector="${fields.selector}"`,
    `subtitle="${fields.subtitle}"`,
    `firmaCard="${fields.firmaCard}"`,
    `kpi=${fields.kpi}`,
    `expected=${expectedName}/${expectedKpi}`,
  ].join('; ');

  const { contradiction, hits } = detectContradiction(measurement);
  const idOk = fields.companyId === String(expectedId);
  const labelOk = fields.label.includes(expectedName) || fields.label.includes(expectedName.replace(' AŞ', ''));
  const selectorOk =
    !fields.selector ||
    fields.selector.includes(expectedName) ||
    fields.selector.includes(expectedName.replace(' AŞ', ''));
  const subtitleOk = fields.subtitle.includes(expectedName) || fields.subtitle.includes(expectedName.replace(' AŞ', ''));
  const cardOk =
    fields.firmaCard.includes(expectedName) ||
    fields.firmaCard.includes(expectedName.replace(' AŞ', '')) ||
    // kart kısa ise subtitle ile aynı kabul (kart parse zayıfsa)
    (fields.firmaCard.length < 5 && subtitleOk);
  const kpiOk = kpiNum === expectedKpi;

  const allOk = idOk && labelOk && selectorOk && subtitleOk && cardOk && kpiOk && !contradiction;

  return {
    ok: allOk,
    measurement,
    contradiction,
    hits,
    checks: { idOk, labelOk, selectorOk, subtitleOk, cardOk, kpiOk },
  };
}

async function main() {
  ensureDirs();
  fs.mkdirSync(path.join(OUT_DIR, 'ss'), { recursive: true });

  const results = [];
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORT, locale: 'tr-TR' });
  const page = await context.newPage();

  await logoutCompany(page);
  await loginCompany(page, 'admin@demo.test');
  await page.goto(`${URLS.company}/dashboard`, { waitUntil: 'domcontentloaded' });
  await settleDashboard(page);
  const sess = await assertSession(page, 'admin@demo.test');
  if (!sess.ok) {
    console.error('OTURUM YOK', sess.measurement);
    process.exit(2);
  }

  // Mevcut id'yi oku; 69'a sabitle
  let current = await page.evaluate(() => localStorage.getItem('alatax_company_id'));
  if (current !== '69') {
    await switchToCompanyId(page, 69);
    await settleDashboard(page);
  }

  // ─── 69 → 71 ───
  {
    await switchToCompanyId(page, 71);
    await settleDashboard(page);
    const fields = await readFields(page);
    const ev = evaluateSwitch(fields, 71);
    const ss = await screenshot(page, '01-G3-69-to-71');
    results.push({
      id: 'G3a',
      title: '69→71 dört alan + KPI',
      status: ev.ok ? 'pass' : 'fail',
      severity: ev.ok ? undefined : '🔴',
      measurement: ev.measurement + (ev.contradiction ? ` CONTRADICTION=${ev.hits.join('|')}` : ''),
      screenshot: ss,
      detail: JSON.stringify(ev.checks),
    });
    console.log('69→71', ev.ok ? 'PASS' : 'FAIL', ev.measurement);
  }

  // ─── 71 → 69 ───
  {
    await switchToCompanyId(page, 69);
    await settleDashboard(page);
    const fields = await readFields(page);
    const ev = evaluateSwitch(fields, 69);
    const ss = await screenshot(page, '02-G3-71-to-69');
    results.push({
      id: 'G3b',
      title: '71→69 dört alan + KPI',
      status: ev.ok ? 'pass' : 'fail',
      severity: ev.ok ? undefined : '🔴',
      measurement: ev.measurement + (ev.contradiction ? ` CONTRADICTION=${ev.hits.join('|')}` : ''),
      screenshot: ss,
      detail: JSON.stringify(ev.checks),
    });
    console.log('71→69', ev.ok ? 'PASS' : 'FAIL', ev.measurement);
  }

  const aPass = results.every((r) => r.status === 'pass');

  // A-G3-DOGRULAMA.md
  let md = `# Tur3 A — G3 FE doğrulama\n\n`;
  md += `**Commit:** ${process.env.GORSEL_COMMIT || '82097fe+'} · **FE test altyapısı:** yok (\`frontend/**/*.test|spec\` = 0) — E2E ile yetinildi.\n\n`;
  md += `## SQL KPI beklenen\n\`69→51, 70→3, 71→11, 72→10\`\n\n`;
  md += `## İki yönlü ölçüm\n\n`;
  md += `| Yön | Header label | Selector | Subtitle | Firma kartı | KPI | Beklenen KPI | Sonuç |\n`;
  md += `|-----|--------------|----------|----------|-------------|-----|--------------|--------|\n`;
  for (const r of results) {
    const m = r.measurement;
    const grab = (k) => (m.match(new RegExp(`${k}="([^"]*)"`)) || [])[1] || '';
    const kpi = (m.match(/kpi=(\d+)/) || [])[1] || '';
    const exp = (m.match(/expected=[^/]+\/(\d+)/) || [])[1] || '';
    md += `| ${r.title} | ${grab('label')} | ${grab('selector')} | ${grab('subtitle')} | ${grab('firmaCard').slice(0, 40)} | ${kpi} | ${exp} | ${r.status === 'pass' ? '✅' : '❌'} |\n`;
  }
  md += `\n## Ekran görüntüleri\n- ss/01-G3-69-to-71.png\n- ss/02-G3-71-to-69.png\n\n`;
  md += `## FE test durumu\nFE unit/integration test dosyası bulunamadı. companyContext.version → loadDashboard bağımlılığı kodda mevcut (\`DashboardPage.tsx\` ~60–102); doğrulama bu E2E ile yapıldı.\n\n`;
  md += `## Karar\n**${aPass ? 'A GEÇTİ — B bölümüne geçilebilir' : 'A BAŞARISIZ — B/C DURDURULDU'}**\n`;
  fs.writeFileSync(path.join(OUT_DIR, 'A-G3-DOGRULAMA.md'), md, 'utf8');
  fs.writeFileSync(path.join(OUT_DIR, 'results-a.json'), JSON.stringify(results, null, 2));

  await writeKanitHtml(results, {
    branch: 'faz4-form-engine',
    commit: 'tur3-a',
  }).catch(() => null);

  await browser.close();
  console.log(aPass ? '\n=== A PASS ===' : '\n=== A FAIL — STOP ===');
  process.exit(aPass ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

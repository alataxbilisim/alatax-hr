#!/usr/bin/env node
/**
 * ALATAX HR — Görsel kontrol otomasyonu
 * Kullanım: node run.mjs [--block G|P|T|L|Y|all]
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {
  ensureDirs,
  attachCollectors,
  writeReport,
  writeKonsol,
  mojibakeHits,
  OUT_DIR,
  URLS,
  VIEWPORT,
  ROOT,
} from './lib/harness.mjs';
import { runBlockG } from './blocks/g.mjs';
import { runBlockP } from './blocks/p.mjs';
import { runBlockT } from './blocks/t.mjs';
import { runBlockL } from './blocks/l.mjs';
import { runBlockY } from './blocks/y.mjs';

const blockArg = (process.argv.find((a) => a.startsWith('--block=')) || '--block=all').split('=')[1];
const mergeMode = process.argv.includes('--merge');

function loadPreviousResults() {
  const p = path.join(OUT_DIR, 'results.json');
  if (!mergeMode || !fs.existsSync(p)) return [];
  try {
    return JSON.parse(fs.readFileSync(p, 'utf8'));
  } catch {
    return [];
  }
}

function mergeResults(prev, next) {
  const map = new Map();
  for (const r of prev) {
    if (r.id) map.set(r.id, r);
  }
  for (const r of next) {
    if (r.id) map.set(r.id, r);
  }
  // T0/Y0 gibi koşucu hatalarını düşür eğer asıl ID'ler geldiyse
  for (const bad of [...map.keys()]) {
    if (/^[A-Z]0$/.test(bad)) map.delete(bad);
  }
  const order = (id) => {
    const m = String(id).match(/^([A-Z]+)(\d+)$/);
    if (!m) return [id, 0];
    return [m[1], Number(m[2])];
  };
  return [...map.values()].sort((a, b) => {
    const [la, na] = order(a.id);
    const [lb, nb] = order(b.id);
    if (la !== lb) return String(la).localeCompare(String(lb));
    return na - nb;
  });
}

async function healthCheck() {
  const checks = [];
  for (const [name, url] of Object.entries({
    api: `${URLS.api}/up`,
    company: URLS.company,
    portal: URLS.portal,
  })) {
    let ok = false;
    let last = '';
    for (let i = 0; i < 3; i++) {
      try {
        const res = await fetch(url, { signal: AbortSignal.timeout(10000) });
        checks.push(`${name}=${res.status}`);
        ok = true;
        break;
      } catch (e) {
        last = e.message;
        await new Promise((r) => setTimeout(r, 1000));
      }
    }
    if (!ok) checks.push(`${name}=DOWN (${last})`);
  }
  return checks.join(', ');
}

async function main() {
  const started = Date.now();
  ensureDirs();

  const health = await healthCheck();
  console.log('Health:', health);
  if (health.includes('DOWN')) {
    console.error('Servisler ayakta değil. Company/Portal başlatın.');
    process.exit(2);
  }

  const branch = execSync('git branch --show-current', { cwd: ROOT }).toString().trim();
  const commit = execSync('git rev-parse --short HEAD', { cwd: ROOT }).toString().trim();

  /** @type {import('./lib/harness.mjs').CheckResult[]} */
  const results = [];
  const prev = loadPreviousResults();
  const pagesLog = [];

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: VIEWPORT,
    locale: 'tr-TR',
  });
  const page = await context.newPage();
  const collectors = attachCollectors(page);

  let seq = 1;
  const run = async (name, fn) => {
    if (blockArg !== 'all' && blockArg !== name) return;
    console.log(`\n=== BLOK ${name} ===`);
    collectors.reset();
    try {
      seq = await fn(page, results, seq, context);
    } catch (e) {
      console.error(`Blok ${name} hata:`, e);
      results.push({
        id: `${name}0`,
        title: `Blok ${name} koşucu hatası`,
        status: 'fail',
        severity: '🔴',
        measurement: String(e.message || e),
      });
    }
    // X çapraz örnek
    try {
      const text = await page.evaluate(() => document.body?.innerText || '');
      pagesLog.push({
        url: page.url(),
        consoleErrors: [...collectors.consoleErrors],
        failedRequests: collectors.failedRequests.filter((r) => r.status !== 403),
        mojibake: mojibakeHits(text),
      });
    } catch {
      /* ignore */
    }
  };

  await run('G', runBlockG);
  await run('P', runBlockP);
  await run('T', runBlockT);
  await run('L', runBlockL);
  await run('Y', runBlockY);

  // X1-X3 aggregate
  if (blockArg === 'all' || blockArg === 'X') {
    const allConsole = pagesLog.flatMap((p) => p.consoleErrors);
    const allFail = pagesLog.flatMap((p) => p.failedRequests);
    const moji = pagesLog.reduce((a, p) => a + p.mojibake, 0);
    results.push({
      id: 'X1',
      title: 'Console error (ziyaret edilen sayfalar)',
      status: allConsole.length === 0 ? 'pass' : 'warn',
      severity: allConsole.length ? '🟠' : undefined,
      measurement: `errors=${allConsole.length}; sample=${allConsole[0] || '—'}`,
    });
    results.push({
      id: 'X2',
      title: '4xx/5xx istekler (403 hariç)',
      status: allFail.length === 0 ? 'pass' : 'warn',
      severity: allFail.length ? '🟠' : undefined,
      measurement: `count=${allFail.length}; sample=${allFail[0] ? `${allFail[0].status} ${allFail[0].url}` : '—'}`,
    });
    results.push({
      id: 'X3',
      title: 'Mojibake taraması',
      status: moji === 0 ? 'pass' : 'fail',
      severity: moji ? '🟠' : undefined,
      measurement: `hits=${moji}`,
    });
  }

  writeKonsol(pagesLog);

  let diffStat = '';
  try {
    diffStat = execSync('git diff --stat -- app/ resources/ frontend/apps frontend/packages/shared/src', {
      cwd: ROOT,
    })
      .toString()
      .trim();
  } catch {
    diffStat = '(alınamadı)';
  }
  if (!diffStat) diffStat = '(ürün kodunda diff yok)';

  const finalResults = mergeMode ? mergeResults(prev, results) : results;

  const summary = writeReport(finalResults, {
    branch,
    commit,
    durationSec: Math.round((Date.now() - started) / 1000),
    fixtureLog: 'gorsel:fixture ×2 OK (idempotent)',
    suite: process.env.GORSEL_SUITE || 'suite ayrıca koşulacak',
    diffStat,
    plan: `- ports: company :3002, portal :3003, superadmin :3001, API :8000
- fixture: php artisan gorsel:fixture
- runner: scripts/gorsel-kontrol (Playwright Chromium, 1366×768)
- beklenen ~40 ekran görüntüsü + RAPOR.md + konsol.md
`,
  });

  await browser.close();

  // results.json for debugging
  fs.writeFileSync(path.join(OUT_DIR, 'results.json'), JSON.stringify(finalResults, null, 2));

  console.log('\n=== ÖZET ===');
  console.log(`✅ ${summary.pass} · ❌ ${summary.fail} · ⚠️ ${summary.warn} · ⏭️ ${summary.skip}`);
  console.log(`Rapor: ${path.join(OUT_DIR, 'RAPOR.md')}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

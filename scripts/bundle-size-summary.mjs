#!/usr/bin/env node
/**
 * Frontend paket boyutu özeti — CI için (bilgi amaçlı, eşik yok).
 *
 * Her uygulamanın dist/ çıktısındaki JS/CSS toplamını (ham + gzip) ve en büyük JS
 * parçalarını Markdown tablo olarak yazar; GITHUB_STEP_SUMMARY varsa oraya da ekler.
 *
 * Usage: node scripts/bundle-size-summary.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { gzipSync } from 'node:zlib';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const APPS = ['superadmin', 'company', 'portal'];
const TOP = 5;

const kb = (bytes) => `${(bytes / 1024).toFixed(1)} KB`;

function* files(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) yield* files(full);
    else yield full;
  }
}

const lines = ['## Frontend bundle boyutu', '', '| Uygulama | JS | JS (gzip) | CSS | CSS (gzip) | JS dosya |', '|---|---|---|---|---|---|'];
const largest = [];
let missing = false;

for (const app of APPS) {
  const dist = path.join(ROOT, 'frontend/apps', app, 'dist');
  if (!fs.existsSync(dist)) {
    console.error(`dist not found: ${path.relative(ROOT, dist)} (run pnpm build first)`);
    missing = true;
    continue;
  }
  const totals = { js: 0, jsGz: 0, css: 0, cssGz: 0, jsCount: 0 };
  for (const file of files(dist)) {
    const ext = path.extname(file);
    if (ext !== '.js' && ext !== '.css') continue;
    const content = fs.readFileSync(file);
    const gz = gzipSync(content).length;
    if (ext === '.js') {
      totals.js += content.length;
      totals.jsGz += gz;
      totals.jsCount += 1;
      largest.push({ app, file: path.relative(dist, file).replace(/\\/g, '/'), size: content.length, gz });
    } else {
      totals.css += content.length;
      totals.cssGz += gz;
    }
  }
  lines.push(`| ${app} | ${kb(totals.js)} | ${kb(totals.jsGz)} | ${kb(totals.css)} | ${kb(totals.cssGz)} | ${totals.jsCount} |`);
}

lines.push('', `### En büyük ${TOP} JS parçası (uygulama başına)`, '', '| Uygulama | Dosya | Boyut | gzip |', '|---|---|---|---|');
for (const app of APPS) {
  largest
    .filter((c) => c.app === app)
    .sort((a, b) => b.size - a.size)
    .slice(0, TOP)
    .forEach((c) => lines.push(`| ${app} | ${c.file} | ${kb(c.size)} | ${kb(c.gz)} |`));
}

const markdown = `${lines.join('\n')}\n`;
console.log(markdown);
if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, markdown);
process.exit(missing ? 1 : 0);

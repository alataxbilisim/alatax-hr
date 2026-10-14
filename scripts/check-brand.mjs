#!/usr/bin/env node
/**
 * Eski urun adi tarayici — CI icin. Urun adi Dobedan HR.
 * Exit 1 if the old brand appears in a tracked/untracked (not ignored) file path or content.
 *
 * Istisnalar (ADIM_02):
 *  - GitHub deposu adresi ve geri donus etiketi (degismez).
 *  - docs/cursor-gorevleri/ (gorev tanimlari ve raporlar eski adi bilerek anar), docs/backups/.
 *  - `brand-guard:allow-start` / `brand-guard:allow-end` arasindaki satirlar.
 *
 * Usage: node scripts/check-brand.mjs   (frontend: pnpm check:brand)
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SELF = path.resolve(fileURLToPath(import.meta.url));
const ROOT = path.resolve(path.dirname(SELF), '..');

const OLD_BRAND = /alatax/i;
const ALLOWED_PHRASES = [/github\.com\/alataxbilisim\/alatax-hr/gi, /\balatax-son\b/g];
const ALLOW_START = 'brand-guard:allow-start';
const ALLOW_END = 'brand-guard:allow-end';

const EXCLUDE_PREFIXES = ['docs/cursor-gorevleri/', 'docs/backups/'];
const EXCLUDE_SEGMENTS = ['/node_modules/', '/vendor/', '/dist/'];
const EXCLUDE_FILES = new Set([
  path.relative(ROOT, SELF).replace(/\\/g, '/'),
  'backend/tests/Architecture/BrandGuardTest.php',
]);

function listFiles() {
  const out = execFileSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard'], {
    cwd: ROOT,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
  });
  return [...new Set(out.split('\0').filter(Boolean))];
}

function skipped(rel) {
  if (EXCLUDE_FILES.has(rel)) return true;
  if (EXCLUDE_PREFIXES.some((p) => rel.startsWith(p))) return true;
  const wrapped = `/${rel}`;
  return EXCLUDE_SEGMENTS.some((s) => wrapped.includes(s));
}

function stripAllowed(line) {
  return ALLOWED_PHRASES.reduce((acc, re) => acc.replace(re, ''), line);
}

const hits = [];
for (const rel of listFiles()) {
  if (skipped(rel)) continue;
  const full = path.join(ROOT, rel);
  if (!fs.existsSync(full) || !fs.statSync(full).isFile()) continue;

  if (OLD_BRAND.test(rel)) hits.push({ file: rel, line: 0, sample: '[dosya adı]' });

  const buf = fs.readFileSync(full);
  if (buf.includes(0)) continue;

  let allowed = false;
  const lines = buf.toString('utf8').split(/\r?\n/);
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (line.includes(ALLOW_START)) {
      allowed = true;
      continue;
    }
    if (line.includes(ALLOW_END)) {
      allowed = false;
      continue;
    }
    if (!allowed && OLD_BRAND.test(stripAllowed(line))) {
      hits.push({ file: rel, line: i + 1, sample: line.trim().slice(0, 120) });
    }
  }
  if (allowed) hits.push({ file: rel, line: 0, sample: `[kapanmamış ${ALLOW_START}]` });
}

if (hits.length) {
  console.error(`Old brand found (${hits.length} hit(s)):`);
  for (const h of hits.slice(0, 80)) {
    console.error(`  ${h.file}:${h.line}: ${h.sample}`);
  }
  if (hits.length > 80) {
    console.error(`  ... and ${hits.length - 80} more`);
  }
  process.exit(1);
}

console.log('OK: no old brand references found');

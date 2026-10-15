#!/usr/bin/env node
/**
 * PHPStan baseline büyüme kontrolü — CI için.
 *
 * Çalışma ağacındaki backend/phpstan-baseline.neon toplam hata sayısı (girdilerin `count`
 * toplamı) karşılaştırma ref'indekinden fazlaysa exit 1. Yeni koddaki hata baseline'a
 * eklenmez, düzeltilir; azalma serbesttir.
 *
 * Usage: node scripts/check-phpstan-baseline.mjs <git-ref>   (ör. origin/main, HEAD~1)
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const BASELINE = 'backend/phpstan-baseline.neon';

function errorCount(text) {
  return [...text.matchAll(/^\s+count:\s*(\d+)\s*$/gm)].reduce((sum, m) => sum + Number(m[1]), 0);
}

const ref = process.argv[2];
if (!ref) {
  console.error('usage: check-phpstan-baseline.mjs <git-ref>');
  process.exit(1);
}

let baseText;
try {
  baseText = execFileSync('git', ['show', `${ref}:${BASELINE}`], { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
} catch {
  console.log(`NOTE: ${BASELINE} not found at ${ref}; nothing to compare.`);
  process.exit(0);
}

const base = errorCount(baseText);
const current = errorCount(fs.readFileSync(path.join(ROOT, BASELINE), 'utf8'));
console.log(`phpstan baseline errors: ${current} (ref ${ref}: ${base})`);

if (current > base) {
  console.error(`FAIL: phpstan-baseline.neon grew by ${current - base}; fix the new errors instead of baselining them.`);
  process.exit(1);
}
console.log('OK');

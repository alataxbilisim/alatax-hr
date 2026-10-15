#!/usr/bin/env node
/**
 * Kapsam mandalı (coverage ratchet) — CI için.
 *
 * Ölçülen satır kapsamı tabanın altındaysa exit 1. Taban yalnız elle ve ayrı commit'te
 * yükseltilir; değer hem docs/operasyon/TEST.md hem .github/workflows/ci.yml içinde durur
 * ve `sync` alt komutu ikisinin aynı olduğunu doğrular.
 *
 * Usage:
 *   node scripts/check-coverage.mjs backend  <clover.xml>             [--floor 58.32]
 *   node scripts/check-coverage.mjs frontend <coverage-summary.json>  [--floor 40.00]
 *   node scripts/check-coverage.mjs sync
 *
 * --floor verilmezse ci.yml'deki BACKEND_COVERAGE_FLOOR / FRONTEND_COVERAGE_FLOOR okunur.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CI_FILE = path.join(ROOT, '.github/workflows/ci.yml');
const TEST_DOC = path.join(ROOT, 'docs/operasyon/TEST.md');
const KINDS = { backend: 'BACKEND_COVERAGE_FLOOR', frontend: 'FRONTEND_COVERAGE_FLOOR' };
const RAISE_HINT_MARGIN = 0.5;

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exit(1);
}

function ciFloor(kind) {
  const text = fs.readFileSync(CI_FILE, 'utf8');
  const match = text.match(new RegExp(`${KINDS[kind]}:\\s*["']?(\\d+(?:\\.\\d+)?)["']?`));
  if (!match) fail(`${KINDS[kind]} not found in .github/workflows/ci.yml`);
  return Number(match[1]);
}

function docFloor(kind) {
  const text = fs.readFileSync(TEST_DOC, 'utf8');
  const match = text.match(new RegExp(`<!--\\s*coverage-floor:${kind}=(\\d+(?:\\.\\d+)?)\\s*-->`));
  if (!match) fail(`<!-- coverage-floor:${kind}=... --> marker not found in docs/operasyon/TEST.md`);
  return Number(match[1]);
}

function backendPercent(file) {
  const xml = fs.readFileSync(file, 'utf8');
  const projectMetrics = [...xml.matchAll(/<metrics\b[^>]*\bstatements="(\d+)"[^>]*\bcoveredstatements="(\d+)"/g)].pop();
  if (!projectMetrics) fail(`no <metrics statements=... coveredstatements=...> in ${file}`);
  const [, total, covered] = projectMetrics.map(Number);
  if (total === 0) fail(`clover reports 0 statements in ${file}`);
  return { percent: (covered / total) * 100, covered, total };
}

function frontendPercent(file) {
  const summary = JSON.parse(fs.readFileSync(file, 'utf8'));
  const lines = summary?.total?.lines;
  if (!lines || typeof lines.total !== 'number') fail(`no total.lines in ${file}`);
  if (lines.total === 0) fail(`coverage summary reports 0 lines in ${file}`);
  return { percent: (lines.covered / lines.total) * 100, covered: lines.covered, total: lines.total };
}

function parseFloorArg(args) {
  const index = args.indexOf('--floor');
  if (index === -1) return null;
  const value = Number(args[index + 1]);
  if (!Number.isFinite(value)) fail('--floor needs a number');
  return value;
}

const [command, file, ...rest] = process.argv.slice(2);

if (command === 'sync') {
  let ok = true;
  for (const kind of Object.keys(KINDS)) {
    const ci = ciFloor(kind);
    const doc = docFloor(kind);
    if (ci !== doc) {
      console.error(`FAIL: ${kind} floor mismatch — ci.yml ${ci} vs TEST.md ${doc}`);
      ok = false;
    } else {
      console.log(`OK: ${kind} floor ${ci.toFixed(2)}% (ci.yml = TEST.md)`);
    }
  }
  process.exit(ok ? 0 : 1);
}

if (!(command in KINDS) || !file) {
  fail('usage: check-coverage.mjs <backend|frontend> <file> [--floor N] | sync');
}
if (!fs.existsSync(file)) fail(`coverage file not found: ${file}`);

const floor = parseFloorArg(rest) ?? ciFloor(command);
const { percent, covered, total } = command === 'backend' ? backendPercent(file) : frontendPercent(file);
const measured = Math.floor(percent * 100) / 100;

console.log(`${command} line coverage: ${measured.toFixed(2)}% (${covered}/${total}), floor ${floor.toFixed(2)}%`);

if (measured < floor) {
  fail(`${command} coverage ${measured.toFixed(2)}% is below the floor ${floor.toFixed(2)}%`);
}
if (measured - floor >= RAISE_HINT_MARGIN) {
  console.log(
    `NOTE: coverage is ${(measured - floor).toFixed(2)} points above the floor; raise ${KINDS[command]} ` +
      'in ci.yml and the coverage-floor marker in docs/operasyon/TEST.md (separate commit).',
  );
}
console.log('OK');

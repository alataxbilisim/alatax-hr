#!/usr/bin/env node
/**
 * Mojibake / bozuk UTF-8 tarayici — CI icin.
 * Exit 1 if classic UTF-8->Latin1 mojibake or U+FFFD found in source files.
 *
 * Usage: node scripts/check-mojibake.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const SELF = path.resolve(fileURLToPath(import.meta.url));

const EXCLUDE_DIR = new Set([
  'node_modules',
  'vendor',
  'dist',
  '.git',
  'coverage',
  'build',
  '.turbo',
  '.next',
  'storage',
]);

function shouldSkip(rel, full) {
  if (path.resolve(full) === SELF) return true;
  const norm = rel.replace(/\\/g, '/').toLowerCase();
  if (norm.includes('docs/qa/')) return true;
  if (norm.endsWith('docs/qa_rapor.md')) return true;
  if (norm.includes('pnpm-lock.yaml')) return true;
  return false;
}

const EXT = new Set([
  '.php',
  '.ts',
  '.tsx',
  '.js',
  '.mjs',
  '.cjs',
  '.json',
  '.css',
  '.md',
  '.html',
  '.yml',
  '.yaml',
  '.sh',
  '.vue',
]);

/**
 * Pattern strings built from code points so this file never stores
 * the literal mojibake sequences that we are scanning for.
 */
const PATTERN_SOURCES = [
  // ellipsis / dashes / curly quotes (UTF-8 of those chars misread as cp1252)
  String.fromCharCode(0xe2, 0x20ac, 0xa6),
  String.fromCharCode(0xe2, 0x20ac, 0x94),
  String.fromCharCode(0xe2, 0x20ac, 0x93),
  String.fromCharCode(0xe2, 0x20ac, 0x9c),
  String.fromCharCode(0xe2, 0x20ac, 0x9d),
  String.fromCharCode(0xe2, 0x20ac, 0x98),
  String.fromCharCode(0xe2, 0x20ac, 0x99),
  String.fromCharCode(0xe2, 0x20ac),
  // Turkish letter mojibake pairs
  String.fromCharCode(0xc4, 0xb1),
  String.fromCharCode(0xc5, 0x9f),
  String.fromCharCode(0xc5, 0x9e),
  String.fromCharCode(0xc5, 0x178), // Win-1252 misread of U+015F bytes
  String.fromCharCode(0xc5, 0x160),
  String.fromCharCode(0xc3, 0xbc),
  String.fromCharCode(0xc3, 0xb6),
  String.fromCharCode(0xc3, 0xa7),
  String.fromCharCode(0xc3, 0x87),
  String.fromCharCode(0xc4, 0x9f),
  String.fromCharCode(0xc4, 0x9e),
  String.fromCharCode(0xc4, 0xb0),
  '\uFFFD',
];

const PATTERNS = PATTERN_SOURCES.map(
  (s) => new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
);

function* walk(dir) {
  let entries;
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true });
  } catch {
    return;
  }
  for (const ent of entries) {
    if (EXCLUDE_DIR.has(ent.name)) continue;
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      yield* walk(full);
    } else if (ent.isFile()) {
      const ext = path.extname(ent.name).toLowerCase();
      if (!EXT.has(ext)) continue;
      const rel = path.relative(ROOT, full);
      if (shouldSkip(rel, full)) continue;
      yield full;
    }
  }
}

const hits = [];
for (const file of walk(ROOT)) {
  let text;
  try {
    text = fs.readFileSync(file, 'utf8');
  } catch {
    continue;
  }
  if (text.includes('\u0000')) continue;

  const lines = text.split(/\r?\n/);
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    for (const re of PATTERNS) {
      re.lastIndex = 0;
      if (re.test(line)) {
        hits.push({
          file: path.relative(ROOT, file).replace(/\\/g, '/'),
          line: i + 1,
          sample: line.trim().slice(0, 120),
        });
        break;
      }
    }
  }
}

if (hits.length) {
  console.error(`Mojibake found (${hits.length} line(s)):`);
  for (const h of hits.slice(0, 80)) {
    console.error(`  ${h.file}:${h.line}: ${h.sample}`);
  }
  if (hits.length > 80) {
    console.error(`  ... and ${hits.length - 80} more`);
  }
  process.exit(1);
}

console.log('OK: no mojibake patterns found');

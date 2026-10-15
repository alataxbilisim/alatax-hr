#!/usr/bin/env node
/**
 * Backend ↔ frontend API sözleşme kontrolü — CI için.
 *
 * Frontend kaynağındaki her `api.<method>('<path>')` çağrısının backend `route:list`
 * çıktısında (method + path) karşılığı olmalı. Eşleşmeyen çağrı → exit 1.
 *
 * Usage:
 *   php artisan route:list --json --path=api/v1 > route-list.json
 *   node scripts/check-api-contract.mjs route-list.json [--verbose]
 *
 * Kurallar:
 *   - Şablon dizgedeki `${...}` ifadeleri tek bir path segmenti (parametre) sayılır.
 *   - Sorgu dizgesi (`?a=b`) ve sondaki `/` yok sayılır.
 *   - Path'i tamamen değişkenden gelen çağrılar (`api.get(url)`) eşlenemez; sayısı raporlanır.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const FRONTEND = path.join(ROOT, 'frontend');
const SOURCE_DIRS = ['packages/shared/src', 'apps/company/src', 'apps/portal/src', 'apps/superadmin/src'];
const API_PREFIX = 'api/v1/';

const CALL_RE = /\bapi\.(get|post|put|patch|delete)\s*(?:<[^()]*?>)?\(\s*(['"`])/g;

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exit(1);
}

function listSourceFiles(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'node_modules' || entry.name === 'test') continue;
      out.push(...listSourceFiles(full));
    } else if (/\.(ts|tsx)$/.test(entry.name) && !/\.(test|spec)\.(ts|tsx)$/.test(entry.name) && !entry.name.endsWith('.d.ts')) {
      out.push(full);
    }
  }
  return out;
}

/** Açılış tırnağından sonra, iç içe `${}` ve şablonları dengeleyerek dizge gövdesini okur. */
function readStringLiteral(source, start, quote) {
  let i = start;
  let body = '';
  while (i < source.length) {
    const ch = source[i];
    if (ch === '\\') {
      body += source.slice(i, i + 2);
      i += 2;
      continue;
    }
    if (ch === quote) return { body, end: i + 1 };
    if (quote === '`' && ch === '$' && source[i + 1] === '{') {
      let depth = 1;
      let j = i + 2;
      while (j < source.length && depth > 0) {
        if (source[j] === '{') depth++;
        else if (source[j] === '}') depth--;
        j++;
      }
      body += '${}';
      i = j;
      continue;
    }
    if (quote !== '`' && ch === '\n') return null;
    body += ch;
    i++;
  }
  return null;
}

function normalizeFrontendPath(raw) {
  const withoutQuery = raw.split('?')[0];
  const trimmed = withoutQuery.replace(/\/+$/, '');
  return trimmed
    .split('/')
    .filter((segment, index) => index === 0 || segment !== '')
    .map((segment) => (segment.includes('${}') ? (segment === '${}' ? ':param' : `~${segment}`) : segment))
    .join('/');
}

function extractCalls() {
  const calls = [];
  const dynamic = [];
  for (const dir of SOURCE_DIRS) {
    for (const file of listSourceFiles(path.join(FRONTEND, dir))) {
      const source = fs.readFileSync(file, 'utf8');
      for (const match of source.matchAll(CALL_RE)) {
        const literal = readStringLiteral(source, match.index + match[0].length, match[2]);
        const line = source.slice(0, match.index).split('\n').length;
        const where = `${path.relative(ROOT, file).replace(/\\/g, '/')}:${line}`;
        if (!literal || !literal.body.startsWith('/')) {
          dynamic.push({ method: match[1].toUpperCase(), where });
          continue;
        }
        calls.push({ method: match[1].toUpperCase(), raw: literal.body, path: normalizeFrontendPath(literal.body), where });
      }
      const dynamicRe = /\bapi\.(get|post|put|patch|delete)\s*(?:<[^()]*?>)?\(\s*(?!['"`])[^)\s]/g;
      for (const match of source.matchAll(dynamicRe)) {
        const line = source.slice(0, match.index).split('\n').length;
        dynamic.push({ method: match[1].toUpperCase(), where: `${path.relative(ROOT, file).replace(/\\/g, '/')}:${line}` });
      }
    }
  }
  return { calls, dynamic };
}

function loadBackendRoutes(file) {
  const text = fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, '');
  let routes;
  try {
    routes = JSON.parse(text);
  } catch (error) {
    fail(`${file} is not valid route:list JSON (${error.message})`);
  }
  if (!Array.isArray(routes) || routes.length === 0) fail(`${file} contains no routes`);
  return routes
    .filter((route) => typeof route.uri === 'string' && route.uri.startsWith(API_PREFIX))
    .map((route) => ({
      methods: route.method.split('|').filter((m) => m !== 'HEAD'),
      segments: route.uri.slice(API_PREFIX.length).split('/'),
    }));
}

function segmentMatches(frontendSegment, backendSegment) {
  const backendIsParam = /^\{.+\}$/.test(backendSegment);
  if (frontendSegment === ':param') return true;
  if (frontendSegment.startsWith('~')) {
    if (backendIsParam) return true;
    const pattern = frontendSegment.slice(1).split('${}').map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('[^/]+');
    return new RegExp(`^${pattern}$`).test(backendSegment);
  }
  return backendIsParam || frontendSegment === backendSegment;
}

function routeMatches(call, route) {
  if (!route.methods.includes(call.method)) return false;
  const segments = call.path.replace(/^\//, '').split('/');
  const required = route.segments.filter((s) => !/^\{.+\?\}$/.test(s)).length;
  if (segments.length < required || segments.length > route.segments.length) return false;
  return segments.every((segment, index) => segmentMatches(segment, route.segments[index]));
}

const args = process.argv.slice(2);
const routeFile = args.find((arg) => !arg.startsWith('--'));
const verbose = args.includes('--verbose');
if (!routeFile) fail('usage: node scripts/check-api-contract.mjs <route-list.json> [--verbose]');

const backendRoutes = loadBackendRoutes(path.resolve(routeFile));
const { calls, dynamic } = extractCalls();
if (calls.length === 0) fail('no api.<method>() calls found in frontend sources');

const unmatched = calls.filter((call) => !backendRoutes.some((route) => routeMatches(call, route)));
const unique = new Set(calls.map((c) => `${c.method} ${c.path}`));

console.log(`Backend routes (api/v1): ${backendRoutes.length}`);
console.log(`Frontend calls: ${calls.length} (${unique.size} unique method+path)`);
console.log(`Dynamic-path calls (not checkable): ${dynamic.length}`);
if (verbose) dynamic.forEach((d) => console.log(`  dynamic ${d.method} ${d.where}`));

if (unmatched.length > 0) {
  console.error(`\nUnmatched calls: ${unmatched.length}`);
  unmatched.forEach((c) => console.error(`  ${c.method} ${c.raw}  (${c.where})`));
  process.exit(1);
}
console.log('OK: every frontend API call has a backend route.');

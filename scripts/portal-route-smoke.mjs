/**
 * Portal rota yapısal smoke — her korumalı rota için page modülü dosyası var mı?
 * CI: node scripts/portal-route-smoke.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const routesFile = path.join(root, 'frontend/apps/portal/src/nav/portalProtectedRoutes.ts');
const pagesDir = path.join(root, 'frontend/apps/portal/src/pages');
const appFile = path.join(root, 'frontend/apps/portal/src/App.tsx');

const routesSrc = fs.readFileSync(routesFile, 'utf8');
const appSrc = fs.readFileSync(appFile, 'utf8');

const modules = [...routesSrc.matchAll(/pageModule:\s*'([^']+)'/g)].map((m) => m[1]);
const paths = [...routesSrc.matchAll(/path:\s*'([^']+)'/g)].map((m) => m[1]);

if (modules.length < 13) {
  console.error(`portal-route-smoke: beklenen ≥13 rota, bulunan ${modules.length}`);
  process.exit(1);
}

const missingFiles = [];
for (const mod of modules) {
  const file = path.join(pagesDir, `${mod}.tsx`);
  if (!fs.existsSync(file)) {
    missingFiles.push(mod);
  }
}

const missingInApp = paths.filter((p) => !appSrc.includes(`path="${p}"`));

if (missingFiles.length || missingInApp.length) {
  if (missingFiles.length) {
    console.error('Eksik page modülü:', missingFiles.join(', '));
  }
  if (missingInApp.length) {
    console.error('App.tsx’te eksik path:', missingInApp.join(', '));
  }
  process.exit(1);
}

console.log(`portal-route-smoke OK — ${modules.length} rota`);

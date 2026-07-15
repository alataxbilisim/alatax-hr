/**
 * Menü sözleşmesi: oryantasyon Personel altında + öğe-seviyesi moduleKey lisansı.
 * Çalıştır: node apps/company/scripts/assert-onboarding-nav.mjs (frontend kökünden)
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../../..');
const navPath = path.join(root, 'apps/company/src/components/layout/moduleNav.ts');
const i18nPath = path.join(root, 'packages/shared/src/i18n/locales/tr/common.json');

const nav = fs.readFileSync(navPath, 'utf8');
const i18n = JSON.parse(fs.readFileSync(i18nPath, 'utf8'));

function assert(cond, msg) {
  if (!cond) {
    console.error('FAIL:', msg);
    process.exit(1);
  }
  console.log('OK:', msg);
}

// Rail'de ayrı onboarding grubu yok
assert(!/id:\s*'onboarding'/.test(nav), "operationalModuleGroups içinde id: 'onboarding' yok");

// Personel altında iki path + moduleKey
assert(
  /path:\s*'\/onboarding'[\s\S]*?moduleKey:\s*'onboarding'/.test(nav),
  "/onboarding öğesinde moduleKey: 'onboarding'"
);
assert(
  /path:\s*'\/onboarding\/templates'[\s\S]*?moduleKey:\s*'onboarding'/.test(nav),
  "/onboarding/templates öğesinde moduleKey: 'onboarding'"
);

// getFilteredMenuItems activeModules parametresi
assert(
  /activeModules:\s*string\[\]\s*=\s*\[\]/.test(nav),
  'getFilteredMenuItems(activeModules) imzası'
);
assert(
  /item\.moduleKey\s*&&\s*!activeModules\.includes\(item\.moduleKey\)/.test(nav),
  'öğe-seviyesi lisans filtresi'
);

// Ölü i18n anahtarı silindi
assert(
  i18n.nav?.assetsAssignments === undefined,
  'nav.assetsAssignments silindi'
);
assert(
  i18n.nav?.onboardingProcesses === 'Oryantasyon Süreçleri',
  'nav.onboardingProcesses etiketi'
);
assert(
  i18n.nav?.onboardingTemplates === 'Oryantasyon Şablonları',
  'nav.onboardingTemplates etiketi'
);

// Saf filtre davranışı (getFilteredMenuItems ile aynı mantık)
function filterItems(items, activeModules) {
  return items.filter((item) => {
    if (item.moduleKey && !activeModules.includes(item.moduleKey)) return false;
    return true;
  });
}

const sample = [
  { path: '/employees', labelKey: 'x' },
  { path: '/onboarding', labelKey: 'y', moduleKey: 'onboarding' },
  { path: '/onboarding/templates', labelKey: 'z', moduleKey: 'onboarding' },
];

assert(
  filterItems(sample, ['onboarding']).length === 3,
  'lisanslı: oryantasyon öğeleri görünür'
);
assert(
  filterItems(sample, []).length === 1,
  'lisanssız: oryantasyon öğeleri gizlenir'
);

console.log('\nOnboarding nav contract PASSED');

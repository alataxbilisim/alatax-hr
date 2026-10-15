/**
 * App.tsx kaynağından route listesi üretir (route duman testi ve menü–route tutarlılığı).
 * Yeni bir <Route path="..."> eklendiğinde testler onu otomatik kapsar.
 */

export interface AppRoute {
  /** App.tsx'teki ham path (`/employees/:id`, `/companies/*`, `*`) */
  path: string;
  /** Tarayıcıda açılacak örnek adres; catch-all (`*`) için null */
  url: string | null;
}

const PARAM_SAMPLES: Record<string, string> = {
  companySlug: 'test-sirketi',
  positionSlug: 'yazilim-gelistirici',
  entityType: 'employee',
  token: 'davet-anahtari',
};

const ROUTE_PATH_RE = /<Route\s+path="([^"]+)"/g;
const ANY_PATH_ATTR_RE = /\bpath="([^"]+)"/g;

export function extractRoutePaths(appSource: string): string[] {
  const routePaths = [...appSource.matchAll(ROUTE_PATH_RE)].map((m) => m[1]);
  const allPathAttrs = [...appSource.matchAll(ANY_PATH_ATTR_RE)].map((m) => m[1]);
  if (routePaths.length !== allPathAttrs.length) {
    // path prop'u <Route>'un ilk prop'u değilse ayrıştırıcı kaçırır; sessiz eksik kapsam yerine dur.
    const missed = allPathAttrs.filter((p) => !routePaths.includes(p));
    throw new Error(`Route ayrıştırıcı ${missed.length} path kaçırdı (path ilk prop olmalı): ${missed.join(', ')}`);
  }
  return routePaths;
}

export function toConcreteUrl(path: string): string | null {
  if (path === '*') return null;
  const withoutSplat = path.replace(/\/\*$/, '');
  return withoutSplat.replace(/:([A-Za-z_]+)/g, (_, name: string) => PARAM_SAMPLES[name] ?? '1') || '/';
}

export function buildRouteManifest(appSource: string): AppRoute[] {
  return extractRoutePaths(appSource).map((path) => ({ path, url: toConcreteUrl(path) }));
}

const STATIC_LINK_RES = [
  /\bto="(\/[^"]*)"/g,
  /\bto=\{\s*['"](\/[^'"]*)['"]\s*\}/g,
  /\bnavigate\(\s*['"](\/[^'"]*)['"]/g,
];

/** Kaynaktaki sabit uygulama içi bağlantılar: `to="/x"`, `to={'/x'}`, `navigate('/x')`. Şablon dizgeler kapsam dışı. */
export function extractStaticLinks(source: string): string[] {
  return STATIC_LINK_RES.flatMap((re) => [...source.matchAll(re)].map((m) => m[1]));
}

/** Satır içi menü dizilerindeki `path: '/x'` değerleri. */
export function extractObjectPaths(source: string): string[] {
  return [...source.matchAll(/\bpath:\s*['"](\/[^'"]*)['"]/g)].map((m) => m[1]);
}

/** Menü bağlantısı (`/employees`) App route kalıplarından biriyle eşleşiyor mu? */
export function matchesAnyRoute(link: string, routePaths: string[]): boolean {
  const clean = link.split(/[?#]/)[0].replace(/\/$/, '') || '/';
  return routePaths.some((routePath) => {
    if (routePath === '*') return false;
    const pattern = routePath
      .replace(/\/\*$/, '(?:/.*)?')
      .replace(/:[A-Za-z_]+/g, '[^/]+');
    return new RegExp(`^${pattern}$`).test(clean);
  });
}

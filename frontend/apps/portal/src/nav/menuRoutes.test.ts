import { describe, expect, it } from 'vitest';
import { extractRoutePaths, extractStaticLinks, matchesAnyRoute } from '@shared/test';
import appSource from '../App.tsx?raw';
import { PORTAL_MORE_LINKS } from './portalMoreLinks';
import { PORTAL_PROTECTED_ROUTES } from './portalProtectedRoutes';

const routePaths = extractRoutePaths(appSource);

const sources = import.meta.glob<string>(['../**/*.{ts,tsx}', '!../**/*.test.{ts,tsx}', '!../test/**'], {
  query: '?raw',
  import: 'default',
  eager: true,
});

function unmatched(links: string[]): string[] {
  return [...new Set(links)].filter((link) => !matchesAnyRoute(link, routePaths));
}

describe('portal menü–route tutarlılığı', () => {
  it('"Daha fazla" menüsündeki her bağlantının route\'u var', () => {
    expect(unmatched(PORTAL_MORE_LINKS.map((link) => link.path))).toEqual([]);
  });

  it('masaüstü rayı ve alt gezinme çubuğu bağlantılarının route\'u var', () => {
    const shellLinks = [
      ...extractStaticLinks(sources['../components/shell/DesktopRail.tsx']),
      ...extractStaticLinks(sources['../components/ui/BottomNav.tsx']),
    ];
    expect(shellLinks.length).toBeGreaterThanOrEqual(10);
    expect(unmatched(shellLinks)).toEqual([]);
  });

  it('korumalı route kaydı App.tsx ile birebir aynı', () => {
    for (const route of PORTAL_PROTECTED_ROUTES) {
      expect(routePaths).toContain(route.path);
    }
  });

  it('kaynaktaki sabit uygulama içi bağlantıların hepsi bir route\'a gider', () => {
    const links = Object.values(sources).flatMap(extractStaticLinks);
    expect(links.length).toBeGreaterThan(10);
    expect(unmatched(links)).toEqual([]);
  });
});

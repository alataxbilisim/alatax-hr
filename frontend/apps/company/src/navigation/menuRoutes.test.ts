import { describe, expect, it } from 'vitest';
import { extractRoutePaths, extractStaticLinks, matchesAnyRoute } from '@shared/test';
import appSource from '../App.tsx?raw';
import { moduleGroups } from '../components/layout/moduleNav';
import { PAGE_REGISTRY } from './pageRegistry';

const routePaths = extractRoutePaths(appSource);

const sources = import.meta.glob<string>(['../**/*.{ts,tsx}', '!../**/*.test.{ts,tsx}', '!../test/**'], {
  query: '?raw',
  import: 'default',
  eager: true,
});

function unmatched(links: string[]): string[] {
  return [...new Set(links)].filter((link) => !matchesAnyRoute(link, routePaths));
}

describe('company menü–route tutarlılığı', () => {
  it('her sidebar menü öğesinin bir route\'u var', () => {
    const menuPaths = moduleGroups.flatMap((group) => group.items.map((item) => item.path));
    expect(menuPaths.length).toBeGreaterThan(50);
    expect(unmatched(menuPaths)).toEqual([]);
  });

  it('sayfa kaydındaki (PAGE_REGISTRY) her route App.tsx\'te tanımlı', () => {
    const registryRoutes = PAGE_REGISTRY.map((entry) => entry.route);
    expect(registryRoutes.filter((route) => !routePaths.includes(route) && !matchesAnyRoute(route, routePaths))).toEqual([]);
  });

  it('kaynaktaki sabit uygulama içi bağlantıların hepsi bir route\'a gider', () => {
    const links = Object.values(sources).flatMap(extractStaticLinks);
    expect(links.length).toBeGreaterThan(20);
    expect(unmatched(links)).toEqual([]);
  });
});

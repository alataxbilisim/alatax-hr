import { describe, expect, it } from 'vitest';
import { extractObjectPaths, extractRoutePaths, extractStaticLinks, matchesAnyRoute } from '@shared/test';
import appSource from '../App.tsx?raw';
import adminLayoutSource from './AdminLayout.tsx?raw';

const routePaths = extractRoutePaths(appSource);

const sources = import.meta.glob<string>(['../**/*.{ts,tsx}', '!../**/*.test.{ts,tsx}'], {
  query: '?raw',
  import: 'default',
  eager: true,
});

function unmatched(links: string[]): string[] {
  return [...new Set(links)].filter((link) => !matchesAnyRoute(link, routePaths));
}

describe('superadmin menü–route tutarlılığı', () => {
  it('yan menüdeki her bağlantının route\'u var', () => {
    const menuPaths = extractObjectPaths(adminLayoutSource);
    expect(menuPaths).toHaveLength(6);
    expect(unmatched(menuPaths)).toEqual([]);
  });

  it('kaynaktaki sabit uygulama içi bağlantıların hepsi bir route\'a gider', () => {
    const links = Object.values(sources).flatMap(extractStaticLinks);
    expect(unmatched(links)).toEqual([]);
  });
});

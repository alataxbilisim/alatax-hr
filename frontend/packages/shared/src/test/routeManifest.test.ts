import { describe, expect, it } from 'vitest';
import {
  buildRouteManifest,
  extractObjectPaths,
  extractRoutePaths,
  extractStaticLinks,
  matchesAnyRoute,
  toConcreteUrl,
} from './routeManifest';

const SOURCE = `
  <Routes>
    <Route element={<Layout />}>
      <Route path="/login" element={<Login />} />
      <Route
        path="/employees/:id"
        element={<Detail />}
      />
      <Route path="/companies/*" element={<Companies />} />
      <Route path="/careers/:companySlug/:positionSlug" element={<Apply />} />
    </Route>
    <Route path="*" element={<NotFound />} />
  </Routes>
`;

describe('routeManifest', () => {
  it('App kaynağındaki tüm path\'leri sırasıyla çıkarır', () => {
    expect(extractRoutePaths(SOURCE)).toEqual(['/login', '/employees/:id', '/companies/*', '/careers/:companySlug/:positionSlug', '*']);
  });

  it('path ilk prop değilse sessizce kaçırmak yerine hata verir', () => {
    expect(() => extractRoutePaths('<Route element={<X />} path="/x" />')).toThrow(/kaçırdı/);
  });

  it('parametreleri örnek değerlerle doldurur', () => {
    expect(toConcreteUrl('/employees/:id')).toBe('/employees/1');
    expect(toConcreteUrl('/companies/*')).toBe('/companies');
    expect(toConcreteUrl('/careers/:companySlug/:positionSlug')).toBe('/careers/test-sirketi/yazilim-gelistirici');
    expect(toConcreteUrl('*')).toBeNull();
    expect(buildRouteManifest(SOURCE)).toHaveLength(5);
  });

  it('menü bağlantısını route kalıplarıyla eşler', () => {
    const paths = extractRoutePaths(SOURCE);
    expect(matchesAnyRoute('/employees/5', paths)).toBe(true);
    expect(matchesAnyRoute('/companies/12/edit', paths)).toBe(true);
    expect(matchesAnyRoute('/login?next=/x', paths)).toBe(true);
    expect(matchesAnyRoute('/olmayan', paths)).toBe(false);
  });

  it('kaynaktaki sabit bağlantıları ve menü path\'lerini çıkarır', () => {
    const source = `
      <Link to="/leaves">x</Link> <NavLink to={'/requests'} /> navigate('/dashboard');
      <Link to={\`/employees/\${id}\`} /> navigate(-1);
      const items = [{ path: '/users', label: 'U' }, { path: "/logs" }];
    `;
    expect(extractStaticLinks(source)).toEqual(['/leaves', '/requests', '/dashboard']);
    expect(extractObjectPaths(source)).toEqual(['/users', '/logs']);
  });
});

import { act, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import {
  anonymousState,
  apiOk,
  authenticatedState,
  branchContextResponse,
  buildRouteManifest,
  captureConsoleErrors,
  companyAdminUser,
  companyContextResponse,
  emptyApiFallbackHandlers,
  persistSession,
  renderWithProviders,
  server,
  userWithPermissions,
} from '@shared/test';
import type { User } from '@shared/types';
import appSource from './App.tsx?raw';
import App from './App';
import branchContextReducer from './store/branchContextSlice';
import companyContextReducer from './store/companyContextSlice';
import { companySmokeHandlers } from './test/smokeHandlers';

const routes = buildRouteManifest(appSource);
const extraReducers = { branchContext: branchContextReducer, companyContext: companyContextReducer };
/** Oturum gerektirmeyen (şifre alanı içerebilen) sayfalar. */
const PUBLIC_PATHS = ['/login', '/forgot-password', '/reset-password', '/invite/:token', '/force-password-change'];

function useSession(user: User) {
  persistSession(user);
  server.use(
    apiOk('get', '/auth/me', { user }),
    apiOk('get', '/context/companies', companyContextResponse),
    apiOk('get', '/context/branches', branchContextResponse),
    ...companySmokeHandlers,
    ...emptyApiFallbackHandlers()
  );
}

async function settle() {
  await waitFor(
    () => expect(document.querySelector('.loading-screen, .page-loading, .loading-spinner')).toBeNull(),
    { timeout: 15000 }
  );
  await act(() => new Promise((resolve) => setTimeout(resolve, 50)));
}

function renderApp(url: string, user: User | null) {
  return renderWithProviders(<App />, {
    route: url,
    extraReducers,
    preloadedState: { auth: user ? authenticatedState(user) : anonymousState() },
  });
}

describe('company route duman testi', () => {
  const admin = companyAdminUser();

  beforeEach(() => useSession(admin));

  it('App.tsx route listesi okunabiliyor', () => {
    expect(routes.length).toBeGreaterThan(100);
  });

  it.each(routes.filter((r) => r.url !== null).map((r) => [r.path, r.url as string]))(
    '%s çökmeden ve konsol hatası olmadan render olur',
    async (path, url) => {
      const consoleErrors = captureConsoleErrors();
      try {
        renderApp(url, admin);
        await settle();

        expect(consoleErrors.errors).toEqual([]);
        expect(screen.queryByText('Bir Hata Oluştu')).not.toBeInTheDocument();
        expect(screen.queryByText(/Erişim Engeli/)).not.toBeInTheDocument();
        if (!PUBLIC_PATHS.includes(path)) {
          expect(document.querySelector('input[name="password"]')).toBeNull();
        }
      } finally {
        consoleErrors.restore();
      }
    }
  );

  it('bilinmeyen adres (catch-all) 404 sayfasını gösterir', async () => {
    renderApp('/olmayan-sayfa-xyz', admin);
    await settle();
    expect(screen.queryByText('Bir Hata Oluştu')).not.toBeInTheDocument();
    expect(document.body.textContent).toMatch(/404|bulunamadı/i);
  });
});

describe('company yetkisiz erişim yönlendirmeleri', () => {
  it('oturum yoksa korumalı route giriş sayfasına yönlenir', async () => {
    server.use(...emptyApiFallbackHandlers());
    renderApp('/employees', null);
    await waitFor(() => expect(document.querySelector('input[type="password"]')).toBeInTheDocument());
  });

  it('izni olmayan panel kullanıcısı izinli route\'ta erişim engeli görür', async () => {
    const user = userWithPermissions(['leaves.requests.approve']);
    useSession(user);
    renderApp('/employees', user);
    await settle();
    expect(screen.getByText('Erişim Engeli')).toBeInTheDocument();
    expect(screen.getByText('employees.list.view')).toBeInTheDocument();
  });
});

import { act, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { login, logout } from '@shared/store/slices/authSlice';
import {
  apiOk,
  buildRouteManifest,
  captureConsoleErrors,
  emptyApiFallbackHandlers,
  persistSession,
  portalEmployeeUser,
  server,
} from '@shared/test';
import appSource from './App.tsx?raw';
import App from './App';
import { store } from './store';
import { PORTAL_PROTECTED_ROUTES } from './nav/portalProtectedRoutes';
import { portalSmokeHandlers } from './test/smokeHandlers';

const routes = buildRouteManifest(appSource);
const employee = portalEmployeeUser();

function signIn() {
  persistSession(employee);
  store.dispatch(login.fulfilled({ user: employee, token: 'test-token' }, 'smoke', { email: '', password: '' }));
}

function signOut() {
  store.dispatch(logout.fulfilled(undefined, 'smoke'));
}

async function settle() {
  await waitFor(() => expect(document.querySelector('.loading-spinner, .loading-screen')).toBeNull(), {
    timeout: 15000,
  });
  await act(() => new Promise((resolve) => setTimeout(resolve, 50)));
}

function renderAt(url: string) {
  window.history.pushState({}, '', url);
  return render(<App />);
}

beforeEach(() => {
  server.use(apiOk('get', '/auth/me', { user: employee }), ...portalSmokeHandlers, ...emptyApiFallbackHandlers());
});

describe('portal route duman testi', () => {
  beforeEach(() => signIn());

  it('korumalı route kaydı App.tsx ile aynı', () => {
    const appPaths = routes.map((r) => r.path);
    for (const route of PORTAL_PROTECTED_ROUTES) {
      expect(appPaths).toContain(route.path);
    }
  });

  it.each(routes.filter((r) => r.url !== null).map((r) => [r.path, r.url as string]))(
    '%s çökmeden ve konsol hatası olmadan render olur',
    async (_path, url) => {
      const consoleErrors = captureConsoleErrors();
      try {
        renderAt(url);
        await settle();

        expect(consoleErrors.errors).toEqual([]);
        expect(document.body.textContent).not.toMatch(/Bir Hata Oluştu/);
        if (PORTAL_PROTECTED_ROUTES.some((r) => r.path === url)) {
          expect(window.location.pathname).toBe(url);
        }
      } finally {
        consoleErrors.restore();
      }
    }
  );

  it('bilinmeyen adres 404 gösterir', async () => {
    renderAt('/olmayan-sayfa-xyz');
    expect(await screen.findByText('404 - Sayfa Bulunamadı')).toBeInTheDocument();
  });

  it('şifre değiştirmesi zorunlu kullanıcı zorunlu şifre sayfasına yönlenir', async () => {
    const mustChange = { ...employee, must_change_password: true };
    store.dispatch(login.fulfilled({ user: mustChange, token: 'test-token' }, 'smoke', { email: '', password: '' }));
    renderAt('/leaves');
    await waitFor(() => expect(window.location.pathname).toBe('/force-password-change'));
  });
});

describe('portal yetkisiz erişim yönlendirmeleri', () => {
  beforeEach(() => signOut());

  it.each(PORTAL_PROTECTED_ROUTES.map((r) => [r.path]))('oturum yokken %s giriş sayfasına yönlenir', async (path) => {
    renderAt(path);
    await waitFor(() => expect(window.location.pathname).toBe('/login'));
    expect(document.querySelector('input[type="password"]')).toBeInTheDocument();
  });
});

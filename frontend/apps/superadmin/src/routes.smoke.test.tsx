import React from 'react';
import { act, screen, waitFor } from '@testing-library/react';
import { useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it } from 'vitest';
import type { User } from '@shared/types';
import {
  anonymousState,
  apiError,
  apiOk,
  authenticatedState,
  buildRouteManifest,
  captureConsoleErrors,
  companyAdminUser,
  emptyApiFallbackHandlers,
  persistSession,
  renderWithProviders,
  server,
  superAdminUser,
} from '@shared/test';
import appSource from './App.tsx?raw';
import App from './App';

const routes = buildRouteManifest(appSource);
const PUBLIC_PATHS = ['/login', '/forgot-password', '/reset-password'];

const LocationProbe: React.FC = () => <output data-testid="location">{useLocation().pathname}</output>;

function renderApp(url: string, user: User | null) {
  return renderWithProviders(
    <>
      <App />
      <LocationProbe />
    </>,
    { route: url, preloadedState: { auth: user ? authenticatedState(user) : anonymousState() } }
  );
}

async function settle() {
  await waitFor(() => expect(document.querySelector('.loading-screen, .loading-spinner')).toBeNull(), {
    timeout: 15000,
  });
  await act(() => new Promise((resolve) => setTimeout(resolve, 50)));
}

describe('superadmin route duman testi', () => {
  const admin = superAdminUser();

  beforeEach(() => {
    persistSession(admin);
    server.use(apiOk('get', '/auth/me', { user: admin }), ...emptyApiFallbackHandlers());
  });

  it.each(routes.filter((r) => r.url !== null).map((r) => [r.path, r.url as string]))(
    '%s çökmeden ve konsol hatası olmadan render olur',
    async (path, url) => {
      const consoleErrors = captureConsoleErrors();
      try {
        renderApp(url, admin);
        await settle();

        expect(consoleErrors.errors).toEqual([]);
        if (!PUBLIC_PATHS.includes(path)) {
          expect(screen.getByTestId('location')).not.toHaveTextContent(/^\/login$/);
        }
      } finally {
        consoleErrors.restore();
      }
    }
  );

  it('bilinmeyen adres panoya yönlenir', async () => {
    renderApp('/olmayan-sayfa-xyz', admin);
    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent(/^\/dashboard$/));
  });
});

describe('superadmin yetkisiz erişim yönlendirmeleri', () => {
  const protectedUrls = routes
    .filter((r) => r.url !== null && r.url !== '/' && !PUBLIC_PATHS.includes(r.path))
    .map((r) => [r.url as string]);

  it.each(protectedUrls)('oturum yokken %s giriş sayfasına yönlenir', async (url) => {
    server.use(apiError('get', '/auth/me', 401, 'Unauthenticated.'), ...emptyApiFallbackHandlers());
    renderApp(url, null);
    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent(/^\/login$/));
  });

  it('şirket yöneticisi süper yönetici paneline giremez', async () => {
    const companyAdmin = companyAdminUser();
    persistSession(companyAdmin);
    server.use(apiOk('get', '/auth/me', { user: companyAdmin }), ...emptyApiFallbackHandlers());
    renderApp('/companies', companyAdmin);
    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent(/^\/login$/));
  });
});

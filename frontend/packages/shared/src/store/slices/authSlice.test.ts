import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import {
  apiError,
  apiOk,
  apiUrl,
  authenticatedState,
  companyAdminUser,
  createTestStore,
  okResponse,
  server,
  userWithPermissions,
} from '../../test';
import { checkAuth, login, logout, verifyTwoFactor } from './authSlice';

describe('authSlice — giriş', () => {
  it('başarılı girişte oturumu açar ve token/kullanıcıyı saklar', async () => {
    const user = companyAdminUser();
    server.use(apiOk('post', '/auth/login', { user, token: 'yeni-token' }));
    const store = createTestStore();

    await store.dispatch(login({ email: user.email, password: 'secret' }));

    const state = store.getState().auth;
    expect(state.isAuthenticated).toBe(true);
    expect(state.user?.email).toBe(user.email);
    expect(state.token).toBe('yeni-token');
    expect(localStorage.getItem('token')).toBe('yeni-token');
    expect(JSON.parse(localStorage.getItem('user') ?? 'null')).toMatchObject({ id: user.id });
  });

  it('hatalı şifrede sunucu mesajını state.error\'a yazar, oturum açmaz', async () => {
    server.use(apiError('post', '/auth/login', 422, 'E-posta veya şifre hatalı.'));
    const store = createTestStore();

    await store.dispatch(login({ email: 'x@test.local', password: 'yanlis' }));

    const state = store.getState().auth;
    expect(state.isAuthenticated).toBe(false);
    expect(state.error).toBe('E-posta veya şifre hatalı.');
    expect(localStorage.getItem('token')).toBeNull();
  });

  it('portal yönlendirme kodunu reddedilen payload\'da taşır', async () => {
    server.use(
      apiError('post', '/auth/login', 403, 'Portalı kullanın.', {
        code: 'PORTAL_ONLY',
        portal_url: 'http://portal.test',
      })
    );
    const store = createTestStore();

    const result = await store.dispatch(login({ email: 'p@test.local', password: 'x' }));

    expect(result.payload).toEqual({ message: 'Portalı kullanın.', code: 'PORTAL_ONLY', portal_url: 'http://portal.test' });
  });
});

describe('authSlice — 2FA challenge akışı', () => {
  it('2FA gerekiyorsa challenge döner, token yazmaz ve eski token\'ı siler', async () => {
    localStorage.setItem('token', 'eski');
    server.use(
      apiOk('post', '/auth/login', {
        requires_2fa: true,
        challenge_token: 'challenge-1',
        expires_in: 300,
        user: { id: 5, name: 'İki Adımlı', email: '2fa@test.local' },
      })
    );
    const store = createTestStore();

    const result = await store.dispatch(login({ email: '2fa@test.local', password: 'secret' }));

    expect(result.payload).toMatchObject({ requires_2fa: true, challenge_token: 'challenge-1' });
    expect(store.getState().auth.isAuthenticated).toBe(false);
    expect(store.getState().auth.token).toBeNull();
    expect(localStorage.getItem('token')).toBeNull();
  });

  it('doğru kodla challenge tamamlanır ve oturum açılır', async () => {
    const user = companyAdminUser({ two_factor_enabled: true });
    let challengeHeader: string | null = null;
    server.use(
      http.post(apiUrl('/auth/2fa/verify'), ({ request }) => {
        challengeHeader = request.headers.get('authorization');
        return okResponse({ user, token: 'gercek-token' });
      })
    );
    const store = createTestStore();

    await store.dispatch(verifyTwoFactor({ challenge_token: 'challenge-1', code: '123456' }));

    expect(challengeHeader).toBe('Bearer challenge-1');
    expect(store.getState().auth.isAuthenticated).toBe(true);
    expect(store.getState().auth.token).toBe('gercek-token');
    expect(localStorage.getItem('token')).toBe('gercek-token');
  });

  it('yanlış kodda durum kodunu ve mesajı döner', async () => {
    server.use(apiError('post', '/auth/2fa/verify', 422, 'Kod geçersiz.'));
    const store = createTestStore();

    const result = await store.dispatch(verifyTwoFactor({ challenge_token: 'c', code: '000000' }));

    expect(result.payload).toEqual({ status: 422, message: 'Kod geçersiz.' });
    expect(store.getState().auth.error).toBe('Kod geçersiz.');
    expect(store.getState().auth.isAuthenticated).toBe(false);
  });
});

describe('authSlice — oturum yaşam döngüsü', () => {
  it('logout state ve localStorage\'ı temizler', async () => {
    const user = companyAdminUser();
    localStorage.setItem('token', 't');
    server.use(apiOk('post', '/auth/logout', null));
    const store = createTestStore({}, { auth: authenticatedState(user, 't') });

    await store.dispatch(logout());

    expect(store.getState().auth.isAuthenticated).toBe(false);
    expect(store.getState().auth.user).toBeNull();
    expect(localStorage.getItem('token')).toBeNull();
  });

  it('checkAuth token yoksa oturumu kapalı tutar ve istek atmaz', async () => {
    const store = createTestStore();

    const result = await store.dispatch(checkAuth());

    expect(result.payload).toBeNull();
    expect(store.getState().auth.isAuthenticated).toBe(false);
  });

  it('checkAuth 401 alırsa oturumu düşürür', async () => {
    localStorage.setItem('token', 'gecersiz');
    window.history.pushState({}, '', '/login');
    server.use(apiError('get', '/auth/me', 401, 'Unauthenticated.'));
    const store = createTestStore({}, { auth: authenticatedState(companyAdminUser(), 'gecersiz') });

    await store.dispatch(checkAuth());

    expect(store.getState().auth.isAuthenticated).toBe(false);
    expect(localStorage.getItem('token')).toBeNull();
  });

  it('sessiz checkAuth hafif /me yanıtında izinleri ve aktif modülleri korur', async () => {
    const previous = userWithPermissions(['employees.list.view']);
    localStorage.setItem('token', 't');
    let lightParam: string | null = null;
    server.use(
      http.get(apiUrl('/auth/me'), ({ request }) => {
        lightParam = new URL(request.url).searchParams.get('light');
        return okResponse({ user: { ...previous, name: 'Yeni Ad', permissions: [], company: { ...previous.company, active_modules: [] } } });
      })
    );
    const store = createTestStore({}, { auth: authenticatedState(previous, 't') });

    await store.dispatch(checkAuth({ silent: true }));

    const user = store.getState().auth.user;
    expect(lightParam).toBe('1');
    expect(user?.name).toBe('Yeni Ad');
    expect(user?.permissions).toEqual(['employees.list.view']);
    expect(user?.company?.active_modules).toEqual(previous.company?.active_modules);
  });

  it('sessiz checkAuth ağ hatasında oturumu düşürmez', async () => {
    localStorage.setItem('token', 't');
    server.use(apiError('get', '/auth/me', 500, 'Sunucu hatası'));
    const store = createTestStore({}, { auth: authenticatedState(companyAdminUser(), 't') });

    await store.dispatch(checkAuth({ silent: true }));

    expect(store.getState().auth.isAuthenticated).toBe(true);
  });
});

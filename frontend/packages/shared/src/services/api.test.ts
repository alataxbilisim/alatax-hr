import { http, HttpResponse } from 'msw';
import toast from 'react-hot-toast';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api, { authApi } from './api';
import { browserNavigation } from './browserNavigation';
import { getValidationErrors, isAxiosValidationError, isPermissionError } from './apiHelpers';
import { apiError, apiUrl, okResponse, server, setCompanyContext } from '../test';

function captureHeaders(path: string) {
  const seen: Headers[] = [];
  server.use(
    http.get(apiUrl(path), ({ request }) => {
      seen.push(request.headers);
      return okResponse({ ok: true });
    })
  );
  return seen;
}

describe('api istemcisi — istek başlıkları', () => {
  it('localStorage token\'ını Bearer olarak ekler', async () => {
    localStorage.setItem('token', 'abc123');
    const seen = captureHeaders('/dashboard');

    await api.get('/dashboard');

    expect(seen[0].get('authorization')).toBe('Bearer abc123');
    expect(seen[0].get('accept')).toBe('application/json');
  });

  it('token yoksa Authorization başlığı göndermez', async () => {
    const seen = captureHeaders('/dashboard');

    await api.get('/dashboard');

    expect(seen[0].has('authorization')).toBe(false);
  });

  it('şirket ve şube bağlamını X-Company-Id / X-Branch-Id olarak ekler', async () => {
    setCompanyContext(7, 12);
    const seen = captureHeaders('/employees');

    await api.get('/employees');

    expect(seen[0].get('x-company-id')).toBe('7');
    expect(seen[0].get('x-branch-id')).toBe('12');
  });

  it('çağrıda verilen bağlam başlıklarını ezmez', async () => {
    setCompanyContext(7, 12);
    const seen = captureHeaders('/employees');

    await api.get('/employees', { headers: { 'X-Company-Id': '3', 'X-Branch-Id': 'all' } });

    expect(seen[0].get('x-company-id')).toBe('3');
    expect(seen[0].get('x-branch-id')).toBe('all');
  });
});

describe('api istemcisi — 2FA challenge', () => {
  it('verifyTwoFactor challenge token\'ını kullanır, eski oturum token\'ını göndermez', async () => {
    localStorage.setItem('token', 'eski-oturum');
    let authHeader: string | null = null;
    let body: unknown = null;
    server.use(
      http.post(apiUrl('/auth/2fa/verify'), async ({ request }) => {
        authHeader = request.headers.get('authorization');
        body = await request.json();
        return okResponse({ user: { id: 1 }, token: 'gercek-token' });
      })
    );

    const response = await authApi.verifyTwoFactor({ code: '123456' }, 'challenge-xyz');

    expect(authHeader).toBe('Bearer challenge-xyz');
    expect(body).toEqual({ code: '123456' });
    expect(response.data.data.token).toBe('gercek-token');
  });
});

describe('api istemcisi — hata eşlemesi', () => {
  beforeEach(() => {
    vi.spyOn(browserNavigation, 'assign').mockImplementation(() => {});
  });

  it('401: oturumu temizler ve korumalı sayfadan /login\'e yönlendirir', async () => {
    localStorage.setItem('token', 'suresi-dolmus');
    localStorage.setItem('user', '{"id":1}');
    window.history.pushState({}, '', '/employees');
    const toastError = vi.spyOn(toast, 'error');
    server.use(apiError('get', '/auth/me', 401, 'Unauthenticated.'));

    await expect(api.get('/auth/me')).rejects.toMatchObject({ response: { status: 401 } });

    expect(localStorage.getItem('token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
    expect(browserNavigation.assign).toHaveBeenCalledWith('/login');
    expect(toastError).toHaveBeenCalledTimes(1);
  });

  it('401: giriş sayfasındayken yönlendirme yapmaz, hatalı giriş mesajını gösterir', async () => {
    localStorage.setItem('token', 'yanlis');
    window.history.pushState({}, '', '/login');
    const toastError = vi.spyOn(toast, 'error');
    server.use(apiError('post', '/auth/login', 401, 'Hatalı giriş.'));

    await expect(api.post('/auth/login', {})).rejects.toBeTruthy();

    expect(browserNavigation.assign).not.toHaveBeenCalled();
    expect(localStorage.getItem('token')).toBeNull();
    expect(toastError).toHaveBeenCalledWith('Hatalı giriş.');
  });

  it('401: giriş sayfasındaki diğer uçlarda (2FA) toast göstermez', async () => {
    window.history.pushState({}, '', '/login');
    const toastError = vi.spyOn(toast, 'error');
    server.use(apiError('post', '/auth/2fa/verify', 401, 'Geçersiz doğrulama kodu'));

    await expect(api.post('/auth/2fa/verify', {})).rejects.toBeTruthy();

    expect(toastError).not.toHaveBeenCalled();
  });

  it('403: sunucu mesajını toast olarak gösterir', async () => {
    const toastError = vi.spyOn(toast, 'error');
    server.use(apiError('get', '/users', 403, 'Bu işlem için izniniz yok.'));

    const error = await api.get('/users').catch((e: unknown) => e);

    expect(toastError).toHaveBeenCalledWith('Bu işlem için izniniz yok.');
    expect(isPermissionError(error)).toBe(true);
  });

  it('403: mesaj yoksa varsayılan yetki mesajını gösterir', async () => {
    const toastError = vi.spyOn(toast, 'error');
    server.use(http.get(apiUrl('/users'), () => HttpResponse.json({}, { status: 403 })));

    await api.get('/users').catch(() => undefined);

    expect(toastError).toHaveBeenCalledWith('Bu işlem için yetkiniz bulunmamaktadır.');
  });

  it('422: ilk alan hatasını gösterir ve alan hatalarını eşler', async () => {
    const toastError = vi.spyOn(toast, 'error');
    server.use(
      apiError('post', '/employees', 422, 'Doğrulama hatası', {
        email: ['E-posta zaten kayıtlı.'],
        first_name: ['Ad zorunludur.'],
      })
    );

    const error = await api.post('/employees', {}).catch((e: unknown) => e);

    expect(toastError).toHaveBeenCalledWith('E-posta zaten kayıtlı.');
    expect(isAxiosValidationError(error)).toBe(true);
    expect(getValidationErrors(error)).toEqual({ email: 'E-posta zaten kayıtlı.', first_name: 'Ad zorunludur.' });
  });

  it('422: alan hatası yoksa mesajı gösterir', async () => {
    const toastError = vi.spyOn(toast, 'error');
    server.use(apiError('post', '/employees', 422, 'Geçersiz istek.'));

    await api.post('/employees', {}).catch(() => undefined);

    expect(toastError).toHaveBeenCalledWith('Geçersiz istek.');
  });

  it('500: genel sunucu hatası mesajı gösterir', async () => {
    const toastError = vi.spyOn(toast, 'error');
    server.use(apiError('get', '/dashboard', 500, 'stack trace'));

    await api.get('/dashboard').catch(() => undefined);

    expect(toastError).toHaveBeenCalledWith('Sunucu hatası. Lütfen daha sonra tekrar deneyin.');
  });
});

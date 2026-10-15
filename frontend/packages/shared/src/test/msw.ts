import { http, HttpResponse, type JsonBodyType } from 'msw';
import api from '../services/api';
import type { PaginationMeta } from '../types/api';

type HttpMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

/** Backend paginator boş sayfada `from`/`to` için null döner. */
type EnvelopeMeta = Partial<Omit<PaginationMeta, 'from' | 'to'> & { from: number | null; to: number | null }>;

/** api.ts istemcisinin baseURL'i; testler aynı kökü kullanır. */
export const API_BASE_URL = String(api.defaults.baseURL ?? '').replace(/\/$/, '');

/** `/employees/:id` → `http://.../api/v1/employees/:id` (MSW yol parametreleri desteklenir). */
export function apiUrl(path: string): string {
  return `${API_BASE_URL}${path.startsWith('/') ? path : `/${path}`}`;
}

/** Backend ApiResponse zarfı: { success, message, data, errors, timestamp, meta? } */
export function envelope<T>(
  data: T,
  options: { success?: boolean; message?: string; errors?: Record<string, unknown> | null; meta?: EnvelopeMeta } = {}
): Record<string, unknown> {
  const body: Record<string, unknown> = {
    success: options.success ?? true,
    message: options.message ?? '',
    data,
    errors: options.errors ?? null,
    timestamp: '2026-01-01T00:00:00+03:00',
  };
  if (options.meta) {
    body.meta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null, ...options.meta };
  }
  return body;
}

export function okResponse<T>(data: T, status = 200): HttpResponse<JsonBodyType> {
  return HttpResponse.json(envelope(data) as JsonBodyType, { status });
}

export function paginatedResponse<T>(items: T[], meta: EnvelopeMeta = {}): HttpResponse<JsonBodyType> {
  return HttpResponse.json(
    envelope(items, { meta: { total: items.length, to: items.length || null, from: items.length ? 1 : null, ...meta } }) as JsonBodyType
  );
}

export function errorResponse(
  status: number,
  message: string,
  errors: Record<string, unknown> | null = null
): HttpResponse<JsonBodyType> {
  return HttpResponse.json(envelope(null, { success: false, message, errors }) as JsonBodyType, { status });
}

/** Tek uç için başarılı yanıt handler'ı. */
export function apiOk<T>(method: HttpMethod, path: string, data: T) {
  return http[method](apiUrl(path), () => okResponse(data));
}

/** Tek uç için sayfalı liste handler'ı. */
export function apiPaginated<T>(path: string, items: T[], meta: EnvelopeMeta = {}) {
  return http.get(apiUrl(path), () => paginatedResponse(items, meta));
}

/** Tek uç için hata handler'ı (401/403/404/422/500…). */
export function apiError(
  method: HttpMethod,
  path: string,
  status: number,
  message: string,
  errors: Record<string, unknown> | null = null
) {
  return http[method](apiUrl(path), () => errorResponse(status, message, errors));
}

/**
 * Tanımsız her API çağrısına boş ama geçerli zarf döner (route duman testi).
 * GET → boş sayfalı liste; yazma → data: null.
 */
export function emptyApiFallbackHandlers() {
  return [
    http.get(apiUrl('/*'), () => paginatedResponse([])),
    http.post(apiUrl('/*'), () => okResponse(null)),
    http.put(apiUrl('/*'), () => okResponse(null)),
    http.patch(apiUrl('/*'), () => okResponse(null)),
    http.delete(apiUrl('/*'), () => okResponse(null)),
  ];
}

/**
 * API Helper Functions with Type Safety
 * Bu dosya API çağrıları için type-safe helper fonksiyonlar sağlar
 */

import type { AxiosResponse } from 'axios';
import { isAxiosError } from 'axios';
import { 
  ApiResponse, 
  PaginatedResponse, 
  PaginationMeta,
  isApiResponse,
  isPaginatedResponse 
} from '../types/api';

/**
 * Extract data from API response
 */
export function extractData<T>(response: AxiosResponse<ApiResponse<T>>): T {
  if (isApiResponse<T>(response.data)) {
    return response.data.data;
  }
  throw new Error('Invalid API response format');
}

/**
 * Extract paginated data from API response
 */
export function extractPaginatedData<T>(
  response: AxiosResponse<PaginatedResponse<T>>
): { data: T[]; meta: PaginationMeta } {
  if (isPaginatedResponse<T>(response.data)) {
    return {
      data: response.data.data,
      meta: response.data.meta,
    };
  }
  throw new Error('Invalid paginated API response format');
}

/**
 * Type-safe API request wrapper
 */
export async function apiRequest<T>(
  request: () => Promise<AxiosResponse<ApiResponse<T>>>
): Promise<T> {
  const response = await request();
  return extractData(response);
}

/**
 * Type-safe paginated API request wrapper
 */
export async function paginatedRequest<T>(
  request: () => Promise<AxiosResponse<PaginatedResponse<T>>>
): Promise<{ data: T[]; meta: PaginationMeta }> {
  const response = await request();
  return extractPaginatedData(response);
}

/**
 * Build query params from object
 */
export function buildQueryParams(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      if (Array.isArray(value)) {
        result[key] = value.join(',');
      } else if (typeof value === 'object') {
        result[key] = JSON.stringify(value);
      } else {
        result[key] = String(value);
      }
    }
  }
  
  return result;
}

/**
 * Format date for API (YYYY-MM-DD)
 */
export function formatDateForApi(date: Date | string): string {
  const d = new Date(date);
  return d.toISOString().split('T')[0];
}

/**
 * Format datetime for API (ISO 8601)
 */
export function formatDateTimeForApi(date: Date | string): string {
  const d = new Date(date);
  return d.toISOString();
}

/**
 * Parse date from API
 */
export function parseDateFromApi(dateString: string): Date {
  return new Date(dateString);
}

/**
 * Handle API error response
 */
export function getErrorMessage(error: unknown, fallback = 'Bir hata oluştu'): string {
  if (isAxiosError<{ message?: string }>(error)) {
    const message = error.response?.data?.message;
    if (typeof message === 'string' && message.length > 0) {
      return message;
    }
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

/**
 * Get validation errors from API error
 */
export function getValidationErrors(
  error: unknown
): Record<string, string> | null {
  if (isAxiosError<{ errors?: Record<string, string[]> }>(error)) {
    const errors = error.response?.data?.errors;
    if (errors) {
      const result: Record<string, string> = {};
      for (const [key, messages] of Object.entries(errors)) {
        result[key] = messages[0];
      }
      return result;
    }
  }

  return null;
}

/**
 * Check if error is a validation error (422)
 */
export function isAxiosValidationError(error: unknown): boolean {
  if (typeof error === 'object' && error !== null) {
    const err = error as { response?: { status?: number } };
    return err.response?.status === 422;
  }
  return false;
}

/**
 * Check if error is an authentication error (401)
 */
export function isAuthError(error: unknown): boolean {
  if (typeof error === 'object' && error !== null) {
    const err = error as { response?: { status?: number } };
    return err.response?.status === 401;
  }
  return false;
}

/**
 * Check if error is a permission error (403)
 */
export function isPermissionError(error: unknown): boolean {
  if (typeof error === 'object' && error !== null) {
    const err = error as { response?: { status?: number } };
    return err.response?.status === 403;
  }
  return false;
}

/**
 * Check if error is a not found error (404)
 */
export function isNotFoundError(error: unknown): boolean {
  if (typeof error === 'object' && error !== null) {
    const err = error as { response?: { status?: number } };
    return err.response?.status === 404;
  }
  return false;
}

/**
 * Retry failed request with exponential backoff
 */
export async function withRetry<T>(
  fn: () => Promise<T>,
  maxRetries = 3,
  baseDelay = 1000
): Promise<T> {
  let lastError: unknown;
  
  for (let attempt = 0; attempt < maxRetries; attempt++) {
    try {
      return await fn();
    } catch (error) {
      lastError = error;
      
      // Don't retry auth errors or validation errors
      if (isAuthError(error) || isAxiosValidationError(error)) {
        throw error;
      }
      
      // Wait before retrying
      if (attempt < maxRetries - 1) {
        const delay = baseDelay * Math.pow(2, attempt);
        await new Promise(resolve => setTimeout(resolve, delay));
      }
    }
  }
  
  throw lastError;
}


/**
 * Standard API Response Types
 * Backend'deki ApiResponse trait ile uyumlu
 */

// Base API Response
export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors: Record<string, string[]> | null;
  timestamp: string;
}

// Paginated Response Meta
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number;
  to?: number;
}

// Paginated API Response
export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  meta: PaginationMeta;
}

// Error Response
export interface ErrorResponse {
  success: false;
  message: string;
  data: null;
  errors: Record<string, string[]> | null;
  timestamp: string;
}

// Validation Error Response
export interface ValidationErrorResponse extends ErrorResponse {
  errors: Record<string, string[]>;
}

// API Request Options
export interface ApiRequestOptions {
  params?: Record<string, unknown>;
  headers?: Record<string, string>;
  signal?: AbortSignal;
}

// Pagination Request Params
export interface PaginationParams {
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
}

// Search/Filter Params
export interface SearchParams extends PaginationParams {
  search?: string;
  [key: string]: unknown;
}

// Date Range Filter
export interface DateRangeFilter {
  date_from?: string;
  date_to?: string;
}

// Common Entity Fields
export interface BaseEntity {
  id: number;
  created_at: string;
  updated_at: string;
}

// Soft Delete Entity
export interface SoftDeleteEntity extends BaseEntity {
  deleted_at: string | null;
}

// Company Scoped Entity
export interface CompanyScopedEntity extends BaseEntity {
  company_id: number;
}

// User Reference (for relations)
export interface UserReference {
  id: number;
  name: string;
  email?: string;
  avatar?: string | null;
}

// Company Reference
export interface CompanyReference {
  id: number;
  name: string;
  slug?: string;
  logo?: string | null;
}

// Module Reference
export interface ModuleReference {
  id: number;
  name: string;
  slug: string;
  is_active?: boolean;
}

// Status Badge Info
export interface StatusInfo {
  value: string;
  label: string;
  color: string;
  icon?: string;
}

// Select Option
export interface SelectOption<T = string | number> {
  value: T;
  label: string;
  disabled?: boolean;
}

// File Upload Response
export interface FileUploadResponse {
  path: string;
  url: string;
  filename: string;
  size: number;
  mime_type: string;
}

// Batch Operation Result
export interface BatchOperationResult {
  success_count: number;
  failure_count: number;
  failed_ids?: number[];
  errors?: Record<number, string>;
}

// Export/Import Result
export interface ExportResult {
  file_url: string;
  filename: string;
  record_count: number;
}

export interface ImportResult {
  imported_count: number;
  skipped_count: number;
  error_count: number;
  errors?: Array<{
    row: number;
    message: string;
  }>;
}

// Type Guards
export function isApiResponse<T>(response: unknown): response is ApiResponse<T> {
  return (
    typeof response === 'object' &&
    response !== null &&
    'success' in response &&
    'message' in response &&
    'data' in response
  );
}

export function isPaginatedResponse<T>(response: unknown): response is PaginatedResponse<T> {
  return isApiResponse(response) && 'meta' in response;
}

export function isErrorResponse(response: unknown): response is ErrorResponse {
  return isApiResponse(response) && response.success === false;
}

export function isValidationError(response: unknown): response is ValidationErrorResponse {
  return isErrorResponse(response) && response.errors !== null;
}

/** Firma API anahtarı */
export interface ApiKey {
  id: number;
  name: string;
  key?: string;
  description?: string | null;
  permissions?: string[] | null;
  last_used_at?: string | null;
  expires_at?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}


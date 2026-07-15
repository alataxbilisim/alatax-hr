import axios from 'axios';
import type { AxiosError, AxiosInstance, InternalAxiosRequestConfig, AxiosResponse } from 'axios';
import toast from 'react-hot-toast';
import type { ApiResponse } from '../types/api';

// API Base URL
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1';

// Axios instance oluştur
const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
});

// Request interceptor - Token + şube bağlamı (X-Branch-Id)
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const existing = config.headers.Authorization;
    if (!existing) {
      const token = localStorage.getItem('token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    }
    // Company panel şube seçici — localStorage (Redux ile senkron)
    const branchId = localStorage.getItem('alatax_branch_id');
    if (branchId && !config.headers['X-Branch-Id']) {
      config.headers['X-Branch-Id'] = branchId;
    }
    return config;
  },
  (error: AxiosError) => {
    return Promise.reject(error);
  }
);

// Response interceptor - Hata yönetimi
api.interceptors.response.use(
  (response: AxiosResponse) => {
    return response;
  },
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const { response } = error;

    if (response) {
      switch (response.status) {
        case 401:
          // Token geçersiz - logout yap (public auth sayfalarında yeniden yönlendirme yapma)
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          {
            const path = window.location.pathname;
            const isPublicAuth =
              path.includes('/login') ||
              path.includes('/forgot-password') ||
              path.includes('/reset-password') ||
              path.includes('/invite');
            if (!isPublicAuth) {
              window.location.href = '/login';
              toast.error('Oturumunuz sona erdi. Lütfen tekrar giriş yapın.');
            }
          }
          break;
        case 403:
          toast.error(response.data?.message || 'Bu işlem için yetkiniz bulunmamaktadır.');
          break;
        case 404:
          toast.error(response.data?.message || 'İstenen kaynak bulunamadı.');
          break;
        case 422:
          // Validation hataları - özel handling
          const errors = response.data?.errors;
          if (errors) {
            const firstError = Object.values(errors)[0]?.[0];
            if (firstError) {
              toast.error(firstError);
            }
          } else {
            toast.error(response.data?.message || 'Doğrulama hatası.');
          }
          break;
        case 429: {
          // Login / 2FA challenge sayfası kendi i18n mesajını gösterir
          const path = window.location.pathname;
          const isPublicAuth =
            path.includes('/login') ||
            path.includes('/forgot-password') ||
            path.includes('/reset-password') ||
            path.includes('/invite');
          if (!isPublicAuth) {
            toast.error(response.data?.message || 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.');
          }
          break;
        }
        case 500:
          toast.error('Sunucu hatası. Lütfen daha sonra tekrar deneyin.');
          break;
        default:
          toast.error(response.data?.message || 'Bir hata oluştu.');
      }
    } else if (error.request) {
      toast.error('Sunucuya bağlanılamadı. İnternet bağlantınızı kontrol edin.');
    }

    return Promise.reject(error);
  }
);

export default api;

// Auth API
export const authApi = {
  login: (data: { email: string; password: string; portal_login?: boolean }) =>
    api.post('/auth/login', data),
  /** Challenge Bearer ile TOTP veya recovery kod doğrula → gerçek token */
  verifyTwoFactor: (
    data: { code?: string; recovery_code?: string },
    challengeToken: string
  ) =>
    api.post('/auth/2fa/verify', data, {
      headers: { Authorization: `Bearer ${challengeToken}` },
    }),
  register: (data: { company_name: string; name: string; email: string; password: string; password_confirmation: string }) => 
    api.post('/auth/register', data),
  logout: () => 
    api.post('/auth/logout'),
  me: (opts?: { light?: boolean }) =>
    api.get('/auth/me', { params: opts?.light ? { light: 1 } : undefined }),
  updateProfile: (data: FormData | Record<string, unknown>) => 
    api.put('/auth/profile', data, {
      headers: data instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : {},
    }),
  updatePassword: (data: { current_password: string; password: string; password_confirmation: string }) => 
    api.put('/auth/password', data),
  forgotPassword: (data: { email: string }) => 
    api.post('/auth/forgot-password', data),
  resetPassword: (data: { token: string; email: string; password: string; password_confirmation: string }) =>
    api.post('/auth/reset-password', data),
  showInvitation: (token: string) =>
    api.get(`/auth/invitation/${encodeURIComponent(token)}`),
  acceptInvitation: (data: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => api.post('/auth/accept-invitation', data),
  /** Self-service 2FA (management.users.edit gerekmez) */
  getSelf2FAStatus: () =>
    api.get<ApiResponse<{ two_factor_enabled: boolean; has_secret: boolean }>>('/auth/2fa/status'),
  enableSelf2FA: () =>
    api.post<ApiResponse<{
      secret: string;
      qr_code_url: string;
      qr_code_svg?: string;
      recovery_codes: string[];
      two_factor_enabled: boolean;
    }>>('/auth/2fa/enable'),
  confirmSelf2FA: (data: { code: string }) =>
    api.post<ApiResponse<{ two_factor_enabled: boolean }>>('/auth/2fa/confirm', data),
  disableSelf2FA: (data: { code?: string; password?: string }) =>
    api.post<ApiResponse<null>>('/auth/2fa/disable', data),
  getSelfRecoveryCodes: () =>
    api.get<ApiResponse<{ remaining_count: number; message: string }>>('/auth/2fa/recovery-codes'),
  regenerateSelfRecoveryCodes: (data: { code?: string; password?: string }) =>
    api.post<ApiResponse<{ recovery_codes: string[] }>>('/auth/2fa/recovery-codes/regenerate', data),
};

// Dashboard API
export const dashboardApi = {
  get: () => api.get('/dashboard'),
};

// Users API
export const usersApi = {
  list: (params?: Record<string, unknown>) => 
    api.get('/users', { params }),
  portalCandidates: (params?: Record<string, unknown>) =>
    api.get('/users/portal-candidates', { params }),
  grantPanelAccess: (id: number, data: { role: string }) =>
    api.post(`/users/${id}/panel-access`, data),
  revokePanelAccess: (id: number) =>
    api.delete(`/users/${id}/panel-access`),
  get: (id: number) => 
    api.get(`/users/${id}`),
  create: (data: Record<string, unknown>) => 
    api.post('/users', data),
  update: (id: number, data: Record<string, unknown>) => 
    api.put(`/users/${id}`, data),
  delete: (id: number) => 
    api.delete(`/users/${id}`),
  invite: (data: Record<string, unknown>) => 
    api.post('/users/invite', data),
  resetPassword: (id: number, data: Record<string, unknown>) => 
    api.post(`/users/${id}/reset-password`, data),
  bulkUpdate: (data: Record<string, unknown>) => 
    api.post('/users/bulk-update', data),
  toggleStatus: (id: number) => 
    api.post(`/users/${id}/toggle-status`),
  uploadAvatar: (id: number, file: File) => {
    const formData = new FormData();
    formData.append('avatar', file);
    return api.post(`/users/${id}/avatar`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  deleteAvatar: (id: number) => 
    api.delete(`/users/${id}/avatar`),
  export: (params?: Record<string, unknown>) => 
    api.get('/users/export', { params, responseType: 'blob' }),
  import: (file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post<ApiResponse<{ success: number; failed: number; errors: string[] }>>('/users/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  enable2FA: (id: number) =>
    api.post<ApiResponse<{ secret: string; qr_code_url: string; recovery_codes: string[] }>>(`/users/${id}/2fa/enable`),
  verify2FA: (id: number, data: { code: string }) =>
    api.post<ApiResponse<null>>(`/users/${id}/2fa/verify`, data),
  disable2FA: (id: number) =>
    api.post<ApiResponse<null>>(`/users/${id}/2fa/disable`),
  getRecoveryCodes: (id: number) =>
    api.get<ApiResponse<{ recovery_codes: string[] }>>(`/users/${id}/2fa/recovery-codes`),
  regenerateRecoveryCodes: (id: number) =>
    api.post<ApiResponse<{ recovery_codes: string[] }>>(`/users/${id}/2fa/recovery-codes/regenerate`),
  getSessions: (id: number) =>
    api.get<ApiResponse<Array<{ id: number; name: string; last_used_at: string | null; created_at: string; is_current: boolean }>>>(`/users/${id}/sessions`),
  revokeSession: (id: number, tokenId: number) =>
    api.delete<ApiResponse<null>>(`/users/${id}/sessions/${tokenId}`),
  revokeAllSessions: (id: number) =>
    api.delete<ApiResponse<{ revoked_count: number }>>(`/users/${id}/sessions`),
};

// Webhooks API
export const webhooksApi = {
  list: (params?: Record<string, unknown>) =>
    api.get('/webhooks', { params }),
  get: (id: number) =>
    api.get(`/webhooks/${id}`),
  create: (data: Record<string, unknown>) =>
    api.post('/webhooks', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put(`/webhooks/${id}`, data),
  delete: (id: number) =>
    api.delete(`/webhooks/${id}`),
  getLogs: (id: number, params?: Record<string, unknown>) =>
    api.get(`/webhooks/${id}/logs`, { params }),
  test: (id: number) =>
    api.post<ApiResponse<{ message: string }>>(`/webhooks/${id}/test`),
  regenerateSecret: (id: number) =>
    api.post<ApiResponse<{ secret: string }>>(`/webhooks/${id}/regenerate-secret`),
};

// Roles API
export const rolesApi = {
  list: () => 
    api.get('/roles'),
  get: (id: number) => 
    api.get(`/roles/${id}`),
  create: (data: { name: string; permissions: string[] }) => 
    api.post('/roles', data),
  update: (id: number, data: { name?: string; permissions?: string[] }) => 
    api.put(`/roles/${id}`, data),
  delete: (id: number) => 
    api.delete(`/roles/${id}`),
  permissions: () => 
    api.get('/permissions'),
};

// Company API
export const companyApi = {
  get: () => 
    api.get('/company'),
  update: (data: FormData | Record<string, unknown>) => 
    api.put('/company', data, {
      headers: data instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : {},
    }),
  modules: () => 
    api.get('/company/modules'),
  uploadLogo: (file: File) => {
    const formData = new FormData();
    formData.append('logo', file);
    return api.post('/company/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  deleteLogo: () => api.delete('/company/logo'),
  getSettings: () => api.get('/company/settings'),
  updateSettings: (data: Record<string, unknown>) => api.put('/company/settings', data),
  testSmtp: (data: { to: string }) => api.post('/company/settings/smtp/test', data),
  testSms: (data: { phone: string }) => api.post('/company/settings/sms/test', data),
};

// Branches API
export const branchesApi = {
  list: (params?: Record<string, unknown>) => api.get('/branches', { params }),
  get: (id: number) => api.get(`/branches/${id}`),
  create: (data: Record<string, unknown>) => api.post('/branches', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/branches/${id}`, data),
  delete: (id: number) => api.delete(`/branches/${id}`),
  setHeadquarters: (id: number) => api.post(`/branches/${id}/set-headquarters`),
  employees: (id: number, params?: Record<string, unknown>) => api.get(`/branches/${id}/employees`, { params }),
};

// Activity Logs API
export const activityLogsApi = {
  list: (params?: Record<string, unknown>) => 
    api.get('/activity-logs', { params }),
  get: (id: number) => 
    api.get(`/activity-logs/${id}`),
  export: (params?: Record<string, unknown>) => 
    api.get('/activity-logs/export', { params, responseType: 'blob' }),
};

// API Keys API
export const apiKeysApi = {
  list: (params?: Record<string, unknown>) => 
    api.get('/api-keys', { params }),
  get: (id: number) => 
    api.get(`/api-keys/${id}`),
  create: (data: Record<string, unknown>) => 
    api.post('/api-keys', data),
  update: (id: number, data: Record<string, unknown>) => 
    api.put(`/api-keys/${id}`, data),
  delete: (id: number) => 
    api.delete(`/api-keys/${id}`),
  regenerate: (id: number) => 
    api.post(`/api-keys/${id}/regenerate`),
};

// Notifications API
export const notificationsApi = {
  list: (params?: Record<string, unknown>) => 
    api.get('/notifications', { params }),
  markAsRead: (id: string) => 
    api.post(`/notifications/${id}/read`),
  markAllAsRead: () => 
    api.post('/notifications/read-all'),
};

// Admin API
export const adminApi = {
  // Dashboard
  dashboard: () => 
    api.get('/admin/dashboard'),
  
  // Companies
  companies: {
    list: (params?: Record<string, unknown>) => 
      api.get('/admin/companies', { params }),
    get: (id: number) => 
      api.get(`/admin/companies/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/admin/companies', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/admin/companies/${id}`, data),
    delete: (id: number) => 
      api.delete(`/admin/companies/${id}`),
    toggleStatus: (id: number, status: string) => 
      api.post(`/admin/companies/${id}/toggle-status`, { status }),
    syncModules: (id: number, modules: Array<{ id: number; is_active: boolean; expires_at?: string }>) => 
      api.post(`/admin/companies/${id}/modules`, { modules }),
    assignPackage: (id: number, data: { license_package_id: number; duration_months?: number; add_to_ledger?: boolean; custom_price?: number }) => 
      api.post(`/admin/companies/${id}/assign-package`, data),
    extendLicense: (id: number, data: { months: number; add_to_ledger?: boolean; amount?: number; description?: string }) => 
      api.post(`/admin/companies/${id}/extend-license`, data),
    // Cari Hesap
    getLedger: (id: number, params?: Record<string, unknown>) => 
      api.get(`/admin/companies/${id}/ledger`, { params }),
    addDebit: (id: number, data: { amount: number; description: string; reference_type?: string; invoice_number?: string; due_date?: string; notes?: string }) => 
      api.post(`/admin/companies/${id}/ledger/debit`, data),
    addCredit: (id: number, data: { amount: number; description: string; payment_method: string; payment_reference?: string; payment_date?: string; notes?: string }) => 
      api.post(`/admin/companies/${id}/ledger/credit`, data),
  },
  
  // License Packages (Lisans Paketleri)
  licensePackages: {
    list: (params?: Record<string, unknown>) => 
      api.get('/admin/license-packages', { params }),
    get: (id: number) => 
      api.get(`/admin/license-packages/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/admin/license-packages', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/admin/license-packages/${id}`, data),
    delete: (id: number) => 
      api.delete(`/admin/license-packages/${id}`),
    syncModules: (id: number, modules: Array<{ id: number; is_included: boolean; additional_price?: number }>) => 
      api.post(`/admin/license-packages/${id}/modules`, { modules }),
    duplicate: (id: number) => 
      api.post(`/admin/license-packages/${id}/duplicate`),
  },
  
  // Available Modules (for package creation)
  availableModules: () => 
    api.get('/admin/available-modules'),
  
  // Ledger Summary (Cari Özet)
  ledgerSummary: (params?: Record<string, unknown>) => 
    api.get('/admin/ledger/summary', { params }),
  
  // Modules
  modules: {
    list: (params?: Record<string, unknown>) => 
      api.get('/admin/modules', { params }),
    get: (id: number) => 
      api.get(`/admin/modules/${id}`),
    create: <T extends object>(data: T) => 
      api.post('/admin/modules', data),
    update: <T extends object>(id: number, data: T) => 
      api.put(`/admin/modules/${id}`, data),
    delete: (id: number) => 
      api.delete(`/admin/modules/${id}`),
  },
  
  // Users
  users: {
    list: (params?: Record<string, unknown>) => 
      api.get('/admin/users', { params }),
    get: (id: number) => 
      api.get(`/admin/users/${id}`),
  },
  
  // Logs
  logs: (params?: Record<string, unknown>) => 
    api.get('/admin/logs', { params }),
};

// Recruitment API
export const recruitmentApi = {
  // Job Positions
  positions: {
    list: (params?: Record<string, unknown>) => 
      api.get('/recruitment/positions', { params }),
    get: (id: number) => 
      api.get(`/recruitment/positions/${id}`),
    create: <T extends object>(data: T) => 
      api.post('/recruitment/positions', data),
    update: <T extends object>(id: number, data: T) => 
      api.put(`/recruitment/positions/${id}`, data),
    delete: (id: number) => 
      api.delete(`/recruitment/positions/${id}`),
  },
  
  // Applications
  applications: {
    list: (params?: Record<string, unknown>) => 
      api.get('/recruitment/applications', { params }),
    get: (id: number) => 
      api.get(`/recruitment/applications/${id}`),
    create: (data: {
      job_position_id: number;
      first_name: string;
      last_name: string;
      email: string;
      phone?: string | null;
      notes?: string | null;
      consent_kvkk: boolean;
      source?: string;
      form_data?: Record<string, unknown> | null;
    }) => api.post('/recruitment/applications', data),
    updateStatus: (id: number, data: { status: string; note?: string }) => 
      api.put(`/recruitment/applications/${id}/status`, data),
    updateNotes: (id: number, notes: string) => 
      api.put(`/recruitment/applications/${id}/notes`, { notes }),
    rate: (id: number, rating: number) => 
      api.put(`/recruitment/applications/${id}/rate`, { rating }),
    convertToEmployee: (
      id: number,
      data?: { branch_id?: number | null; employee_code?: string; hire_date?: string; department_id?: number | null }
    ) => api.post(`/recruitment/applications/${id}/convert-to-employee`, data ?? {}),
  },
  
  // CV Pool
  cvPool: {
    list: (params?: Record<string, unknown>) => 
      api.get('/recruitment/cv-pool', { params }),
    bulkTag: (ids: number[], tag: string) => 
      api.post('/recruitment/cv-pool/bulk-tag', { ids, tag }),
    removeTag: (id: number, tag: string) => 
      api.delete(`/recruitment/cv-pool/${id}/tag`, { data: { tag } }),
    rate: (id: number, rating: number) => 
      api.put(`/recruitment/cv-pool/${id}/rate`, { rating }),
  },
  
  // Forms
  forms: {
    list: (params?: Record<string, unknown>) => 
      api.get('/recruitment/forms', { params }),
    get: (id: number) => 
      api.get(`/recruitment/forms/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/recruitment/forms', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/recruitment/forms/${id}`, data),
    delete: (id: number) => 
      api.delete(`/recruitment/forms/${id}`),
  },
  
  // Reports
  reports: {
    summary: (params?: Record<string, unknown>) => 
      api.get('/recruitment/reports/summary', { params }),
    byPosition: (params?: Record<string, unknown>) => 
      api.get('/recruitment/reports/by-position', { params }),
    bySource: (params?: Record<string, unknown>) => 
      api.get('/recruitment/reports/by-source', { params }),
    trends: (params?: Record<string, unknown>) => 
      api.get('/recruitment/reports/trends', { params }),
    timeToHire: (params?: Record<string, unknown>) => 
      api.get('/recruitment/reports/time-to-hire', { params }),
  },
  
  // Interviews
  interviews: {
    list: (params?: Record<string, unknown>) => 
      api.get('/recruitment/interviews', { params }),
    get: (id: number) => 
      api.get(`/recruitment/interviews/${id}`),
    getTypes: () => 
      api.get('/recruitment/interviews/types'),
    calendar: (params?: Record<string, unknown>) => 
      api.get('/recruitment/interviews/calendar', { params }),
    create: (data: Record<string, unknown>) => 
      api.post('/recruitment/interviews', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/recruitment/interviews/${id}`, data),
    delete: (id: number) => 
      api.delete(`/recruitment/interviews/${id}`),
    complete: (id: number, data: Record<string, unknown>) => 
      api.post(`/recruitment/interviews/${id}/complete`, data),
    cancel: (id: number, data: Record<string, unknown>) => 
      api.post(`/recruitment/interviews/${id}/cancel`, data),
  },
};

// Documents API
export const documentsApi = {
  // Categories
  categories: {
    list: (params?: Record<string, unknown>) => 
      api.get('/documents/categories', { params }),
    create: <T extends object>(data: T) => 
      api.post('/documents/categories', data),
    update: <T extends object>(id: number, data: T) => 
      api.put(`/documents/categories/${id}`, data),
    delete: (id: number) => 
      api.delete(`/documents/categories/${id}`),
  },
  
  // Documents
  files: {
    list: (params?: Record<string, unknown>) => 
      api.get('/documents', { params }),
    get: (id: number) => 
      api.get(`/documents/${id}`),
    create: (data: FormData) => 
      api.post('/documents', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    update: (id: number, data: FormData | Record<string, unknown>) => 
      api.put(`/documents/${id}`, data, {
        headers: data instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : {},
      }),
    delete: (id: number) => 
      api.delete(`/documents/${id}`),
    download: (id: number) => 
      api.get(`/documents/${id}/download`, { responseType: 'blob' }),
    versions: (id: number) => 
      api.get(`/documents/${id}/versions`),
    downloadVersion: (id: number, versionId: number) => 
      api.get(`/documents/${id}/versions/${versionId}/download`, { responseType: 'blob' }),
  },
  
  // Statistics
  stats: () => api.get('/documents/stats'),
  
  // Reports
  reports: {
    getMetadata: () => 
      api.get('/documents/reports/metadata'),
    getWidgetData: (data: { dimension: string; measure: string; filters?: Record<string, unknown>; limit?: number }) => 
      api.post('/documents/reports/widget-data', data),
    getKpiData: (data: { kpi_type: string; filters?: Record<string, unknown> }) => 
      api.post('/documents/reports/kpi-data', data),
    getSummary: () => 
      api.get('/documents/reports/summary'),
  },
  
  // Dashboards (BI Dashboard - saved reports)
  dashboards: {
    getAll: () => api.get('/documents/dashboards'),
    get: (id: number) => api.get(`/documents/dashboards/${id}`),
    create: (data: Record<string, unknown>) => api.post('/documents/dashboards', data),
    update: (id: number, data: Record<string, unknown>) => api.put(`/documents/dashboards/${id}`, data),
    delete: (id: number) => api.delete(`/documents/dashboards/${id}`),
    toggleFavorite: (id: number) => api.post(`/documents/dashboards/${id}/favorite`),
  },
};

// Leaves (İzin Yönetimi) API
export const leavesApi = {
  // Leave Types
  types: {
    list: (params?: Record<string, unknown>) => 
      api.get('/leaves/types', { params }),
    get: (id: number) => 
      api.get(`/leaves/types/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/leaves/types', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/leaves/types/${id}`, data),
    delete: (id: number) => 
      api.delete(`/leaves/types/${id}`),
  },
  
  // Leave Requests
  requests: {
    list: (params?: Record<string, unknown>) => 
      api.get('/leaves/requests', { params }),
    get: (id: number) => 
      api.get(`/leaves/requests/${id}`),
    create: (data: FormData) => 
      api.post('/leaves/requests', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    approve: (id: number, note?: string) => 
      api.post(`/leaves/requests/${id}/approve`, { note }),
    reject: (id: number, reason: string) => 
      api.post(`/leaves/requests/${id}/reject`, { reason }),
    cancel: (id: number) => 
      api.post(`/leaves/requests/${id}/cancel`),
    myRequests: (params?: Record<string, unknown>) => 
      api.get('/leaves/requests/my', { params }),
    pendingApprovals: (params?: Record<string, unknown>) => 
      api.get('/leaves/requests/pending', { params }),
  },
  
  // Leave Balance
  balance: {
    list: (params?: Record<string, unknown>) => 
      api.get('/leaves/balance', { params }),
    myBalance: (year?: number) => 
      api.get('/leaves/balance/my', { params: { year } }),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/leaves/balance/${id}`, data),
    bulkUpdate: (data: Record<string, unknown>) => 
      api.post('/leaves/balance/bulk', data),
  },
  
  // Calendar
  calendar: {
    get: (params: { start_date: string; end_date: string; user_id?: number }) => 
      api.get('/leaves/calendar', { params }),
    today: () => 
      api.get('/leaves/calendar/today'),
    upcoming: (days?: number) => 
      api.get('/leaves/calendar/upcoming', { params: { days } }),
  },

  // Holidays (Tatil Takvimi)
  holidays: {
    list: (params?: Record<string, unknown>) => 
      api.get('/leaves/holidays', { params }),
    get: (id: number) => 
      api.get(`/leaves/holidays/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/leaves/holidays', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/leaves/holidays/${id}`, data),
    delete: (id: number) => 
      api.delete(`/leaves/holidays/${id}`),
    getTypes: () => 
      api.get('/leaves/holidays/types'),
    getRange: (params: { start_date: string; end_date: string }) => 
      api.get('/leaves/holidays/range', { params }),
    checkDate: (date: string) => 
      api.post('/leaves/holidays/check-date', { date }),
  },

  // Accrual Policies (Hakediş Politikaları)
  accrualPolicies: {
    list: (params?: Record<string, unknown>) => 
      api.get('/leaves/accrual-policies', { params }),
    get: (id: number) => 
      api.get(`/leaves/accrual-policies/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/leaves/accrual-policies', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/leaves/accrual-policies/${id}`, data),
    delete: (id: number) => 
      api.delete(`/leaves/accrual-policies/${id}`),
    getTypes: () => 
      api.get('/leaves/accrual-policies/types'),
    getLogTypes: () => 
      api.get('/leaves/accrual-policies/log-types'),
    getUserLogs: (userId: number, params?: Record<string, unknown>) => 
      api.get(`/leaves/accrual-policies/user/${userId}/logs`, { params }),
    processMonthly: (data: { month: number; year: number }) => 
      api.post('/leaves/accrual-policies/process-monthly', data),
    processCarryover: (data: { year: number }) => 
      api.post('/leaves/accrual-policies/process-carryover', data),
  },
};

// Onboarding API
export const onboardingApi = {
  // Templates
  templates: {
    list: (params?: Record<string, unknown>) => 
      api.get('/onboarding/templates', { params }),
    get: (id: number) => 
      api.get(`/onboarding/templates/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/onboarding/templates', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/onboarding/templates/${id}`, data),
    delete: (id: number) => 
      api.delete(`/onboarding/templates/${id}`),
    duplicate: (id: number) => 
      api.post(`/onboarding/templates/${id}/duplicate`),
  },
  
  // Processes
  processes: {
    list: (params?: Record<string, unknown>) => 
      api.get('/onboarding/processes', { params }),
    get: (id: number) => 
      api.get(`/onboarding/processes/${id}`),
    create: (data: Record<string, unknown>) => 
      api.post('/onboarding/processes', data),
    update: (id: number, data: Record<string, unknown>) => 
      api.put(`/onboarding/processes/${id}`, data),
    delete: (id: number) => 
      api.delete(`/onboarding/processes/${id}`),
    addTask: (processId: number, data: Record<string, unknown>) => 
      api.post(`/onboarding/processes/${processId}/tasks`, data),
    completeTask: (processId: number, taskId: number, data?: Record<string, unknown>) => 
      api.post(`/onboarding/processes/${processId}/tasks/${taskId}/complete`, data),
    skipTask: (processId: number, taskId: number) =>
      api.post(`/onboarding/processes/${processId}/tasks/${taskId}/skip`),
    finalizeOffboarding: (processId: number) =>
      api.post(`/onboarding/processes/${processId}/finalize-offboarding`),
    cancelOffboarding: (processId: number) =>
      api.post(`/onboarding/processes/${processId}/cancel-offboarding`),
    clearanceForm: (processId: number) =>
      api.get(`/onboarding/processes/${processId}/clearance-form`, { responseType: 'blob' }),
    dashboard: () =>
      api.get('/onboarding/dashboard'),
  },
};

// Performance API
export const performanceApi = {
  // Periods
  periods: {
    list: (params?: Record<string, unknown>) =>
      api.get('/performance/periods', { params }),
    get: (id: number) =>
      api.get(`/performance/periods/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/performance/periods', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/performance/periods/${id}`, data),
    delete: (id: number) =>
      api.delete(`/performance/periods/${id}`),
    activate: (id: number) =>
      api.post(`/performance/periods/${id}/activate`),
    close: (id: number) =>
      api.post(`/performance/periods/${id}/close`),
  },
  // Criteria
  criteria: {
    list: (params?: Record<string, unknown>) =>
      api.get('/performance/criteria', { params }),
    get: (id: number) =>
      api.get(`/performance/criteria/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/performance/criteria', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/performance/criteria/${id}`, data),
    delete: (id: number) =>
      api.delete(`/performance/criteria/${id}`),
  },
  // Reviews
  reviews: {
    list: (params?: Record<string, unknown>) =>
      api.get('/performance/reviews', { params }),
    get: (id: number) =>
      api.get(`/performance/reviews/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/performance/reviews', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/performance/reviews/${id}`, data),
    delete: (id: number) =>
      api.delete(`/performance/reviews/${id}`),
    submit: (id: number) =>
      api.post(`/performance/reviews/${id}/submit`),
    approve: (id: number) =>
      api.post(`/performance/reviews/${id}/approve`),
    reject: (id: number, reason?: string) =>
      api.post(`/performance/reviews/${id}/reject`, { reason }),
  },
};

// Training API
export const trainingApi = {
  // Categories
  categories: () =>
    api.get('/training/categories'),
  // Trainings
  trainings: {
    list: (params?: Record<string, unknown>) =>
      api.get('/training/trainings', { params }),
    get: (id: number) =>
      api.get(`/training/trainings/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/training/trainings', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/training/trainings/${id}`, data),
    delete: (id: number) =>
      api.delete(`/training/trainings/${id}`),
  },
  // Sessions
  sessions: {
    list: (params?: Record<string, unknown>) =>
      api.get('/training/sessions', { params }),
    get: (id: number) =>
      api.get(`/training/sessions/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/training/sessions', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/training/sessions/${id}`, data),
    delete: (id: number) =>
      api.delete(`/training/sessions/${id}`),
    addParticipant: (id: number, userId: number) =>
      api.post(`/training/sessions/${id}/participants`, { user_id: userId }),
    removeParticipant: (id: number, userId: number) =>
      api.delete(`/training/sessions/${id}/participants/${userId}`),
    updateAttendance: (id: number, data: Record<string, unknown>) =>
      api.post(`/training/sessions/${id}/attendance`, data),
  },
};

// Assets API
export const assetsApi = {
  // Categories
  categories: {
    list: () =>
      api.get('/assets/categories'),
    get: (id: number) =>
      api.get(`/assets/categories/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/assets/categories', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/assets/categories/${id}`, data),
    delete: (id: number) =>
      api.delete(`/assets/categories/${id}`),
  },
  // Items
  items: {
    list: (params?: Record<string, unknown>) =>
      api.get('/assets/items', { params }),
    get: (id: number) =>
      api.get(`/assets/items/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/assets/items', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/assets/items/${id}`, data),
    delete: (id: number) =>
      api.delete(`/assets/items/${id}`),
    assign: (id: number, data: Record<string, unknown>) =>
      api.post(`/assets/items/${id}/assign`, data),
    return: (id: number, data?: Record<string, unknown>) =>
      api.post(`/assets/items/${id}/return`, data),
  },
  // Maintenance
  maintenance: {
    list: (params?: Record<string, unknown>) =>
      api.get('/assets/maintenance', { params }),
    get: (id: number) =>
      api.get(`/assets/maintenance/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/assets/maintenance', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/assets/maintenance/${id}`, data),
    delete: (id: number) =>
      api.delete(`/assets/maintenance/${id}`),
  },
};

// Surveys API
export const surveysApi = {
  types: () => api.get('/surveys/types'),
  list: (params?: Record<string, unknown>) => api.get('/surveys', { params }),
  get: (id: number) => api.get(`/surveys/${id}`),
  create: (data: Record<string, unknown>) => api.post('/surveys', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/surveys/${id}`, data),
  delete: (id: number) => api.delete(`/surveys/${id}`),
  submit: (id: number, data: { responses: Array<{ question_id: number; answer_text?: string; answer_numeric?: number; answer_array?: unknown[] }> }) => 
    api.post(`/surveys/${id}/submit`, data),
  results: (id: number) => api.get(`/surveys/${id}/results`),
};

// Attendance API (Company HR)
export const attendanceApi = {
  list: (params?: Record<string, unknown>) => api.get('/attendance', { params }),
  dailySummary: (date?: string) => api.get('/attendance/daily-summary', { params: { date } }),
  get: (id: number) => api.get(`/attendance/${id}`),
  create: (data: Record<string, unknown>) => api.post('/attendance', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/attendance/${id}`, data),
  approve: (id: number) => api.post(`/attendance/${id}/approve`),
  bulkApprove: (ids: number[]) => api.post('/attendance/bulk-approve', { ids }),
  issueKioskToken: (data?: { branch_id?: number }) =>
    api.post('/attendance/kiosk/token', data ?? {}),
  report: (params?: Record<string, unknown>) => api.get('/attendance/reports', { params }),
  reportExport: (params?: Record<string, unknown>) =>
    api.get('/attendance/reports/export', { params, responseType: 'blob' }),
};

// Shifts API (Company HR) — vardiya tanım + atama
export const shiftsApi = {
  list: (params?: Record<string, unknown>) => api.get('/shifts', { params }),
  get: (id: number) => api.get(`/shifts/${id}`),
  create: (data: Record<string, unknown>) => api.post('/shifts', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/shifts/${id}`, data),
  delete: (id: number) => api.delete(`/shifts/${id}`),
  assignments: {
    list: (params?: Record<string, unknown>) => api.get('/employee-shifts', { params }),
    assign: (data: Record<string, unknown>) => api.post('/employee-shifts', data),
    bulkAssign: (data: Record<string, unknown>) => api.post('/employee-shifts/bulk', data),
    remove: (id: number) => api.delete(`/employee-shifts/${id}`),
  },
};

// Expenses API (Company HR) — portal own API ayrı (portalApi.expenses)
export const expensesApi = {
  claims: {
    list: (params?: Record<string, unknown>) => api.get('/expenses/claims', { params }),
    get: (id: number) => api.get(`/expenses/claims/${id}`),
    approve: (id: number, data?: { note?: string }) =>
      api.post(`/expenses/claims/${id}/approve`, data ?? {}),
    reject: (id: number, data: { reason: string }) =>
      api.post(`/expenses/claims/${id}/reject`, data),
    markPaid: (
      id: number,
      data?: { payment_method?: string; payment_reference?: string; note?: string }
    ) => api.post(`/expenses/claims/${id}/mark-paid`, data ?? {}),
  },
  categories: {
    list: (params?: Record<string, unknown>) => api.get('/expenses/categories', { params }),
    get: (id: number) => api.get(`/expenses/categories/${id}`),
    create: (data: Record<string, unknown>) => api.post('/expenses/categories', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/expenses/categories/${id}`, data),
    delete: (id: number) => api.delete(`/expenses/categories/${id}`),
  },
};

// Analytics API
export const analyticsApi = {
  summary: () => api.get('/analytics/summary'),
  workforce: () => api.get('/analytics/workforce'),
  turnover: () => api.get('/analytics/turnover'),
  recruitment: (params?: Record<string, unknown>) => api.get('/analytics/recruitment', { params }),
  leaves: () => api.get('/analytics/leaves'),
  training: (params?: Record<string, unknown>) => api.get('/analytics/training', { params }),
};

// Public API (Başvuru formları)
export const publicApi = {
  // Jobs
  jobs: {
    list: (companySlug: string) =>
      api.get(`/public/companies/${companySlug}/jobs`),
    get: (positionSlug: string) =>
      api.get(`/public/jobs/${positionSlug}`),
    form: (companySlug: string, positionSlug: string) =>
      api.get(`/public/companies/${companySlug}/jobs/${positionSlug}/form`),
  },

  // Applications — companySlug tenant için zorunlu
  apply: (companySlug: string, positionSlug: string, data: FormData) => {
    if (!data.has('company_slug')) {
      data.append('company_slug', companySlug);
    }
    return api.post(`/public/companies/${companySlug}/jobs/${positionSlug}/apply`, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
};

// Portal API (Personel Self-Servis)
export const portalApi = {
  // Dashboard
  dashboard: () => api.get('/portal/dashboard'),
  
  // Profile
  profile: {
    get: () => api.get('/portal/profile'),
    update: (data: Record<string, unknown>) => api.put('/portal/profile', data),
    updatePassword: (data: { current_password: string; password: string; password_confirmation: string }) => 
      api.put('/portal/profile/password', data),
    updateAvatar: (data: FormData) => 
      api.post('/portal/profile/avatar', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
  },
  
  // Leaves (İzinler)
  leaves: {
    types: () => api.get('/portal/leaves/types'),
    balances: (year?: number) => api.get('/portal/leaves/balances', { params: { year } }),
    list: (params?: Record<string, unknown>) => api.get('/portal/leaves', { params }),
    get: (id: number) => api.get(`/portal/leaves/${id}`),
    create: (data: FormData) => 
      api.post('/portal/leaves', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    update: (id: number, data: FormData) => 
      api.put(`/portal/leaves/${id}`, data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    cancel: (id: number) => api.post(`/portal/leaves/${id}/cancel`),
  },
  
  // Documents (Belgeler)
  documents: {
    categories: () => api.get('/portal/documents/categories'),
    list: (params?: Record<string, unknown>) => api.get('/portal/documents', { params }),
    get: (id: number) => api.get(`/portal/documents/${id}`),
    download: (id: number) => api.get(`/portal/documents/${id}/download`, { responseType: 'blob' }),
  },
  
  // Payslips (Bordrolar)
  payslips: {
    years: () => api.get('/portal/payslips/years'),
    list: (params?: Record<string, unknown>) => api.get('/portal/payslips', { params }),
    get: (id: number) => api.get(`/portal/payslips/${id}`),
    download: (id: number) => api.get(`/portal/payslips/${id}/download`, { responseType: 'blob' }),
  },
  
  // Announcements (Duyurular)
  announcements: {
    unreadCount: () => api.get('/portal/announcements/unread-count'),
    list: (params?: Record<string, unknown>) => api.get('/portal/announcements', { params }),
    get: (id: number) => api.get(`/portal/announcements/${id}`),
    acknowledge: (id: number) => api.post(`/portal/announcements/${id}/acknowledge`),
  },
  
  // Requests (Talepler)
  requests: {
    types: () => api.get('/portal/requests/types'),
    pendingCount: () => api.get('/portal/requests/pending-count'),
    list: (params?: Record<string, unknown>) => api.get('/portal/requests', { params }),
    get: (id: number) => api.get(`/portal/requests/${id}`),
    create: (data: FormData) => 
      api.post('/portal/requests', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    update: (id: number, data: Record<string, unknown>) => api.put(`/portal/requests/${id}`, data),
    cancel: (id: number) => api.post(`/portal/requests/${id}/cancel`),
  },
  
  // Training (Eğitimler)
  training: {
    list: (params?: Record<string, unknown>) => api.get('/portal/training', { params }),
    available: (params?: Record<string, unknown>) => api.get('/portal/training/available', { params }),
    get: (id: number) => api.get(`/portal/training/${id}`),
    certificates: {
      list: (params?: Record<string, unknown>) => api.get('/portal/training/certificates/list', { params }),
      get: (id: number) => api.get(`/portal/training/certificates/${id}`),
    },
  },
  
  // Performance (Performans)
  performance: {
    reviews: {
      list: (params?: Record<string, unknown>) => api.get('/portal/performance/reviews', { params }),
      get: (id: number) => api.get(`/portal/performance/reviews/${id}`),
      addComment: (id: number, data: { employee_comments: string }) => 
        api.post(`/portal/performance/reviews/${id}/comment`, data),
    },
    okrs: {
      list: (params?: Record<string, unknown>) => api.get('/portal/performance/okrs', { params }),
      get: (id: number) => api.get(`/portal/performance/okrs/${id}`),
    },
    keyResults: {
      update: (id: number, data: { current_value: number; note?: string }) => 
        api.put(`/portal/performance/key-results/${id}`, data),
    },
    feedbacks: {
      list: (params?: Record<string, unknown>) => api.get('/portal/performance/feedbacks', { params }),
      create: (data: { employee_id: number; type: string; content: string; is_anonymous?: boolean }) => 
        api.post('/portal/performance/feedbacks', data),
    },
  },
  
  // Surveys (Anketler)
  surveys: {
    list: (params?: Record<string, unknown>) => api.get('/portal/surveys', { params }),
    completed: (params?: Record<string, unknown>) => api.get('/portal/surveys/completed', { params }),
    get: (id: number) => api.get(`/portal/surveys/${id}`),
    start: (id: number) => api.post(`/portal/surveys/${id}/start`),
    submit: (id: number, data: { responses: Array<{ question_id: number; answer_text?: string; answer_numeric?: number; answer_array?: unknown[] }> }) =>
      api.post(`/portal/surveys/${id}/submit`, data),
  },
  
  // Timesheet (Puantaj)
  timesheet: {
    todayStatus: () => api.get('/portal/timesheet/today'),
    clockIn: (data?: { latitude?: number; longitude?: number }) => api.post('/portal/timesheet/clock-in', data),
    clockOut: (data?: { latitude?: number; longitude?: number }) => api.post('/portal/timesheet/clock-out', data),
    startBreak: () => api.post('/portal/timesheet/break/start'),
    endBreak: () => api.post('/portal/timesheet/break/end'),
    weeklyRecords: (weekStart?: string) => api.get('/portal/timesheet/weekly', { params: { week_start: weekStart } }),
    monthlyRecords: (year?: number, month?: number) => api.get('/portal/timesheet/monthly', { params: { year, month } }),
    shifts: (weekStart?: string) => api.get('/portal/timesheet/shifts', { params: { week_start: weekStart } }),
    qrScan: (data: { token: string; latitude?: number; longitude?: number }) =>
      api.post('/portal/timesheet/qr-scan', data),
  },
  
  // Expenses (Masraf Yönetimi)
  expenses: {
    categories: () => api.get('/portal/expenses/categories'),
    summary: () => api.get('/portal/expenses/summary'),
    list: (params?: Record<string, unknown>) => api.get('/portal/expenses', { params }),
    get: (id: number) => api.get(`/portal/expenses/${id}`),
    create: (data: Record<string, unknown>) => api.post('/portal/expenses', data),
    update: (id: number, data: Record<string, unknown>) => api.put(`/portal/expenses/${id}`, data),
    submit: (id: number) => api.post(`/portal/expenses/${id}/submit`),
    cancel: (id: number) => api.delete(`/portal/expenses/${id}`),
    uploadReceipt: (itemId: number, formData: FormData) => 
      api.post(`/portal/expenses/items/${itemId}/receipt`, formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
  },
};

// Employees API (Personel Yönetimi)
export const employeesApi = {
  getAll: (params?: object) => api.get('/employees', { params }),
  getById: (id: number) => api.get(`/employees/${id}`),
  create: <T extends object>(data: T) => api.post('/employees', data),
  update: <T extends object>(id: number, data: T) => api.put(`/employees/${id}`, data),
  delete: (id: number) => api.delete(`/employees/${id}`),
  export: (params?: Record<string, unknown>) => api.get('/employees/export', { params, responseType: 'blob' }),
  import: (file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post('/employees/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  importTemplate: () => api.get('/employees/import/template', { responseType: 'blob' }),
  bulkUpdate: (ids: number[], data: Record<string, unknown>) => 
    api.post('/employees/bulk-update', { ids, data }),
  bulkDelete: (ids: number[]) => 
    api.post('/employees/bulk-delete', { ids }),
  createPortalAccess: (
    id: number,
    data: {
      email: string;
      name: string;
      access_mode?: 'invite' | 'set_password';
      password?: string;
    }
  ) => api.post(`/employees/${id}/portal-access`, data),
  revokePortalAccess: (id: number) => 
    api.delete(`/employees/${id}/portal-access`),
  startOffboarding: (
    id: number,
    data: {
      termination_reason_code: string;
      termination_date: string;
      exit_notes?: string;
      template_id?: number;
      assigned_to?: number;
    }
  ) => api.post(`/employees/${id}/offboarding`, data),
  getCustomFields: () => api.get('/employees/custom-fields'),
  getDepartments: () => api.get('/employees/departments'),
  getManagers: () => api.get('/employees/managers'),
  
  // Alt veriler
  getLeaves: (id: number) => api.get(`/employees/${id}/leaves`),
  getTrainings: (id: number) => api.get(`/employees/${id}/trainings`),
  getAssets: (id: number) => api.get(`/employees/${id}/assets`),
  getPerformance: (id: number) => api.get(`/employees/${id}/performance`),
  getActivity: (id: number, params?: Record<string, unknown>) => 
    api.get(`/employees/${id}/activity`, { params }),
  
  // Belgeler
  documents: {
    list: (employeeId: number, params?: Record<string, unknown>) => 
      api.get(`/employees/${employeeId}/documents`, { params }),
    get: (employeeId: number, docId: number) => 
      api.get(`/employees/${employeeId}/documents/${docId}`),
    upload: (employeeId: number, data: FormData) => 
      api.post(`/employees/${employeeId}/documents`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
      }),
    update: (employeeId: number, docId: number, data: Record<string, unknown>) => 
      api.put(`/employees/${employeeId}/documents/${docId}`, data),
    delete: (employeeId: number, docId: number) => 
      api.delete(`/employees/${employeeId}/documents/${docId}`),
    download: (employeeId: number, docId: number) => 
      api.get(`/employees/${employeeId}/documents/${docId}/download`, { responseType: 'blob' }),
    categories: () => api.get('/employee-documents/categories'),
    expiringSoon: (days?: number) => 
      api.get('/employee-documents/expiring-soon', { params: { days } }),
  },
  
  // Organizasyon ve Raporlar
  getOrganizationChart: (params?: { mode?: 'people' | 'department' | 'hybrid' }) =>
    api.get('/employees/organization-chart', { params }),
  getStats: (params?: Record<string, unknown>) => api.get('/employees/stats', { params }),
  exportReport: (params?: Record<string, unknown>) => api.get('/employees/export-report', { params, responseType: 'blob' }),
  
  // BI Raporlama
  reports: {
    // Metadata (boyutlar, metrikler, filtreler)
    getMetadata: () => api.get('/employees/reports/metadata'),

    /** Şube karşılaştırma — reports.cross_branch */
    getByBranch: () => api.get('/employees/reports/by-branch'),
    
    // Dinamik veri aggregation
    getData: (config: {
      dimension: string;
      measure: string;
      filters?: Record<string, unknown>;
    }) => api.post('/employees/reports/data', config),
    
    // Kayıtlı raporlar
    getSaved: (params?: { favorites_only?: boolean }) => 
      api.get('/employees/reports/saved', { params }),
    save: (data: {
      name: string;
      description?: string;
      config: Record<string, unknown>;
      is_shared?: boolean;
    }) => api.post('/employees/reports/saved', data),
    update: (id: number, data: {
      name?: string;
      description?: string;
      config?: Record<string, unknown>;
      is_shared?: boolean;
    }) => api.put(`/employees/reports/saved/${id}`, data),
    delete: (id: number) => api.delete(`/employees/reports/saved/${id}`),
    toggleFavorite: (id: number) => api.post(`/employees/reports/saved/${id}/favorite`),
    
    // Export
    exportExcel: (config: {
      dimension: string;
      measure: string;
      filters?: Record<string, unknown>;
    }) => api.post('/employees/reports/export/excel', config, { responseType: 'blob' }),
  },

  // Dashboard (Çoklu Widget BI Dashboard)
  dashboards: {
    // Dashboard listesi
    getAll: (params?: { favorites_only?: boolean }) => 
      api.get('/employees/dashboards', { params }),
    
    // Tek dashboard
    getById: (id: number) => api.get(`/employees/dashboards/${id}`),
    
    // Dashboard oluştur
    create: <TWidget extends object>(data: {
      name: string;
      description?: string;
      widgets: TWidget[];
      layout_config?: object;
      is_shared?: boolean;
    }) => api.post('/employees/dashboards', data),
    
    // Dashboard güncelle
    update: <TWidget extends object>(id: number, data: {
      name?: string;
      description?: string;
      widgets?: TWidget[];
      layout_config?: object;
      is_shared?: boolean;
    }) => api.put(`/employees/dashboards/${id}`, data),
    
    // Dashboard sil
    delete: (id: number) => api.delete(`/employees/dashboards/${id}`),
    
    // Favori toggle
    toggleFavorite: (id: number) => api.post(`/employees/dashboards/${id}/favorite`),
    
    // Widget verisi getir
    getWidgetData: <TConfig extends object>(data: {
      type: string;
      config: TConfig;
    }) => api.post('/employees/dashboards/widget-data', data),
    
    // Excel export
    exportExcel: (id: number) => 
      api.get(`/employees/dashboards/${id}/export/excel`, { responseType: 'blob' }),
  },
};

// Departments API (Departmanlar)
export const departmentsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/departments', { params }),
  getById: (id: number) => api.get(`/departments/${id}`),
  create: <T extends object>(data: T) => api.post('/departments', data),
  update: <T extends object>(id: number, data: T) => api.put(`/departments/${id}`, data),
  delete: (id: number) => api.delete(`/departments/${id}`),
  getManagers: () => api.get('/departments/managers'),
  getHierarchy: () => api.get('/departments/hierarchy'),
};

/** A5 — Firma unvan / pozisyon kataloğu (recruitment job-positions değil) */
export interface PositionCatalogItem {
  id: number;
  code: string;
  name: string;
  department_id?: number | null;
  department?: { id: number; name: string; code?: string } | null;
  sgk_occupation_code?: string | null;
  description?: string | null;
  is_active: boolean;
  is_system: boolean;
  sort_order?: number;
}

export const positionsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/positions', { params }),
  getById: (id: number) => api.get(`/positions/${id}`),
  create: <T extends object>(data: T) => api.post('/positions', data),
  update: <T extends object>(id: number, data: T) => api.put(`/positions/${id}`, data),
  delete: (id: number) => api.delete(`/positions/${id}`),
};

// Custom Fields API (Özel Alanlar)
export const customFieldsApi = {
  getAll: (entityType?: string) => 
    api.get('/custom-fields', { params: { entity_type: entityType } }),
  getById: (id: number) => api.get(`/custom-fields/${id}`),
  create: <T extends object>(data: T) => api.post('/custom-fields', data),
  update: <T extends object>(id: number, data: T) => api.put(`/custom-fields/${id}`, data),
  delete: (id: number) => api.delete(`/custom-fields/${id}`),
  reorder: (fields: Array<{ id: number; sort_order: number }>) => 
    api.post('/custom-fields/reorder', { fields }),
  getFieldTypes: () => api.get('/custom-fields/field-types'),
  getEntityTypes: () => api.get('/custom-fields/entity-types'),
};

/** Form Engine — layout + alan metadata (FAZ 4A) */
export const formDefinitionsApi = {
  get: (entityType: string) => api.get(`/form-definitions/${entityType}`),
  update: <T extends object>(entityType: string, data: T) =>
    api.put(`/form-definitions/${entityType}`, data),
};

/** Lookup Engine — form dropdown (GET /lookups/{type}) + yönetim CRUD */
export interface LookupItem {
  id: number;
  company_id: number | null;
  lookup_type: string;
  value: string;
  label: string;
  color: string | null;
  sort_order: number;
  is_active: boolean;
  is_system: boolean;
  is_hybrid: boolean;
  parent_lookup_id: number | null;
  meta: Record<string, unknown> | null;
  is_company_override: boolean;
}

export const lookupsApi = {
  forType: (type: string) => api.get(`/lookups/${type}`),
  resolve: (lookupType: string, value: string) =>
    api.get('/lookups-resolve', { params: { lookup_type: lookupType, value } }),
  manageList: (lookupType: string, activeOnly = false) =>
    // Query string'de boolean "false" Laravel `boolean` kuralını kırar → 0/1 gönder
    api.get('/lookups-manage', {
      params: { lookup_type: lookupType, active_only: activeOnly ? 1 : 0 },
    }),
  create: <T extends object>(data: T) => api.post('/lookups-manage', data),
  update: <T extends object>(id: number, data: T) => api.put(`/lookups-manage/${id}`, data),
  delete: (id: number) => api.delete(`/lookups-manage/${id}`),
  reorder: (lookupType: string, items: Array<{ value: string; sort_order: number }>) =>
    api.post('/lookups-manage/reorder', { lookup_type: lookupType, items }),
};


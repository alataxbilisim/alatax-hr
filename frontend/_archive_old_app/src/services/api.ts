import axios from 'axios';
import type { AxiosError, AxiosInstance, InternalAxiosRequestConfig, AxiosResponse } from 'axios';
import toast from 'react-hot-toast';

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

// Request interceptor - Token ekle
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
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
          // Token geçersiz - logout yap
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          window.location.href = '/login';
          toast.error('Oturumunuz sona erdi. Lütfen tekrar giriş yapın.');
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
  login: (data: { email: string; password: string }) => 
    api.post('/auth/login', data),
  register: (data: { company_name: string; name: string; email: string; password: string; password_confirmation: string }) => 
    api.post('/auth/register', data),
  logout: () => 
    api.post('/auth/logout'),
  me: () => 
    api.get('/auth/me'),
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
};

// Dashboard API
export const dashboardApi = {
  get: () => api.get('/dashboard'),
};

// Users API
export const usersApi = {
  list: (params?: Record<string, unknown>) => 
    api.get('/users', { params }),
  get: (id: number) => 
    api.get(`/users/${id}`),
  create: (data: Record<string, unknown>) => 
    api.post('/users', data),
  update: (id: number, data: Record<string, unknown>) => 
    api.put(`/users/${id}`, data),
  delete: (id: number) => 
    api.delete(`/users/${id}`),
  toggleStatus: (id: number) => 
    api.post(`/users/${id}/toggle-status`),
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
};

// Activity Logs API
export const activityLogsApi = {
  list: (params?: Record<string, unknown>) => 
    api.get('/activity-logs', { params }),
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
    create: (data: Record<string, unknown>) => 
      api.post('/admin/modules', data),
    update: (id: number, data: Record<string, unknown>) => 
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
    create: (data: Record<string, unknown>) => 
      api.post('/recruitment/positions', data),
    update: (id: number, data: Record<string, unknown>) => 
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
    updateStatus: (id: number, data: { status: string; note?: string }) => 
      api.put(`/recruitment/applications/${id}/status`, data),
    updateNotes: (id: number, notes: string) => 
      api.put(`/recruitment/applications/${id}/notes`, { notes }),
    rate: (id: number, rating: number) => 
      api.put(`/recruitment/applications/${id}/rate`, { rating }),
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
};

// Documents API
export const documentsApi = {
  // Categories
  categories: {
    list: (params?: Record<string, unknown>) => 
      api.get('/documents/categories', { params }),
    create: (data: Record<string, unknown>) => 
      api.post('/documents/categories', data),
    update: (id: number, data: Record<string, unknown>) => 
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
    dashboard: () => 
      api.get('/onboarding/dashboard'),
  },
};

// Public API (Başvuru formları)
export const publicApi = {
  // Jobs
  jobs: {
    list: (companySlug: string) => 
      api.get(`/public/companies/${companySlug}/jobs`),
    get: (positionSlug: string) => 
      api.get(`/public/jobs/${positionSlug}`),
  },
  
  // Applications
  apply: (positionSlug: string, data: FormData) => 
    api.post(`/public/jobs/${positionSlug}/apply`, data, { 
      headers: { 'Content-Type': 'multipart/form-data' } 
    }),
};


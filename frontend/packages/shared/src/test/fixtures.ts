import { MODULE_KEYS } from '../constants/modules';
import type { FormDefinitionPayload, FormFieldMeta } from '../form-engine/types';
import type { Company, User } from '../types/modules';

export const ALL_MODULE_KEYS: string[] = Object.values(MODULE_KEYS);

const TIMESTAMP = '2026-01-01T00:00:00+03:00';

export function makeCompany(overrides: Partial<Company> = {}): Company {
  return {
    id: 1,
    name: 'Test Şirketi A.Ş.',
    slug: 'test-sirketi',
    status: 'active',
    active_modules: ALL_MODULE_KEYS,
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

export function makeUser(overrides: Partial<User> = {}): User {
  return {
    id: 10,
    home_company_id: 1,
    name: 'Test Kullanıcı',
    email: 'kullanici@test.local',
    type: 'user',
    is_active: true,
    must_change_password: false,
    two_factor_enabled: false,
    company: makeCompany(),
    roles: [],
    permissions: [],
    preferences: {},
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

/** Şirket yöneticisi: tüm modüller açık, izin kontrolünü atlar. */
export function companyAdminUser(overrides: Partial<User> = {}): User {
  return makeUser({ id: 1, name: 'Şirket Yöneticisi', email: 'admin@test.local', type: 'company_admin', ...overrides });
}

/** Belirli izinlere sahip panel kullanıcısı. */
export function userWithPermissions(permissions: string[], overrides: Partial<User> = {}): User {
  return makeUser({ permissions, ...overrides });
}

/** Yalnız portal self-servis izinleri olan personel. */
export function portalEmployeeUser(overrides: Partial<User> = {}): User {
  return makeUser({
    id: 20,
    name: 'Portal Personeli',
    email: 'personel@test.local',
    permissions: ['leaves.requests.view', 'leaves.requests.create', 'documents.list.view'],
    ...overrides,
  });
}

export function superAdminUser(overrides: Partial<User> = {}): User {
  return makeUser({
    id: 99,
    home_company_id: null,
    name: 'Süper Yönetici',
    email: 'super@test.local',
    type: 'super_admin',
    company: null,
    ...overrides,
  });
}

/** Auth slice'ı oturum açmış duruma getiren preloaded state. */
export function authenticatedState(user: User, token = 'test-token') {
  return { user, token, isAuthenticated: true, isLoading: false, error: null };
}

export function anonymousState() {
  return { user: null, token: null, isAuthenticated: false, isLoading: false, error: null };
}

/** localStorage oturumu (store modül yüklenirken okunan uygulamalar için). */
export function persistSession(user: User, token = 'test-token'): void {
  localStorage.setItem('token', token);
  localStorage.setItem('user', JSON.stringify(user));
}

/** Şirket/şube bağlamı: api.ts bu anahtarlardan X-Company-Id / X-Branch-Id üretir. */
export function setCompanyContext(companyId: number | string, branchId?: number | string): void {
  localStorage.setItem('dobedan_company_id', String(companyId));
  if (branchId !== undefined) {
    localStorage.setItem('dobedan_branch_id', String(branchId));
  }
}

export const companyContextResponse = {
  companies: [{ id: 1, name: 'Test Şirketi A.Ş.', slug: 'test-sirketi', is_active: true }],
  active_company_id: 1,
};

/** Form Engine alan meta verisi (API `form-definitions/resolve` sözleşmesi). */
export function makeFormField(overrides: Partial<FormFieldMeta>): FormFieldMeta {
  const fieldKey = overrides.field_key ?? 'alan';
  const label = overrides.field_label ?? 'Alan';
  return {
    id: 1,
    company_id: 1,
    entity_type: 'employee',
    is_system: true,
    system_key: null,
    field_key: fieldKey,
    field_label: label,
    label_override: null,
    effective_label: overrides.effective_label ?? label,
    field_type: 'text',
    is_required: false,
    is_required_override: null,
    effective_required: false,
    is_active: true,
    is_hidden: false,
    sort_order: 0,
    validation_rules: [],
    field_permission: null,
    ...overrides,
  };
}

export function makeFormDefinition(fields: FormFieldMeta[], overrides: Partial<FormDefinitionPayload> = {}): FormDefinitionPayload {
  return {
    id: 1,
    company_id: 1,
    entity_type: 'employee',
    name: 'Personel Formu',
    is_active: true,
    is_system_default: true,
    layout: { sections: [] },
    fields,
    ...overrides,
  };
}

export const branchContextResponse = {
  branches: [{ id: 5, name: 'Merkez', code: 'MRK' }],
  can_select_all: true,
  locked_branch_id: null,
};

/**
 * Hiyerarşik Yetkilendirme Sistemi Sabitleri
 * Format: {module}.{page}.{action}
 */

// CRUD Aksiyonları
export const ACTIONS = {
  VIEW: 'view',
  CREATE: 'create',
  EDIT: 'edit',
  DELETE: 'delete',
  EXPORT: 'export',
  IMPORT: 'import',
  APPROVE: 'approve',
  CANCEL: 'cancel',
} as const;

export type ActionType = typeof ACTIONS[keyof typeof ACTIONS];

// Modül Tanımları
export const MODULES = {
  // Temel Modüller
  DASHBOARD: 'dashboard',
  MANAGEMENT: 'management',
  EMPLOYEES: 'employees',
  
  // Lisanslı Modüller
  RECRUITMENT: 'recruitment',
  LEAVES: 'leaves',
  DOCUMENTS: 'documents',
  ONBOARDING: 'onboarding',
  PERFORMANCE: 'performance',
  TRAINING: 'training',
  ASSETS: 'assets',
  SURVEYS: 'surveys',
  ANALYTICS: 'analytics',
  TIMESHEET: 'timesheet',
  EXPENSES: 'expenses',
} as const;

export type ModuleType = typeof MODULES[keyof typeof MODULES];

// Sayfa Tanımları (Her modül için)
export const PAGES = {
  // Management Modülü
  [MODULES.MANAGEMENT]: {
    USERS: 'users',
    ROLES: 'roles',
    BRANCHES: 'branches',
    AUDIT_LOGS: 'audit_logs',
    SETTINGS: 'settings',
    WEBHOOKS: 'webhooks',
    API_KEYS: 'api_keys',
    WORKFLOWS: 'workflows',
    FORMS: 'forms',
    NOTIFICATIONS: 'notifications',
  },
  
  // Employees Modülü
  [MODULES.EMPLOYEES]: {
    LIST: 'list',
    DEPARTMENTS: 'departments',
    POSITIONS: 'positions',
    ORGANIZATION: 'organization',
    CUSTOM_FIELDS: 'custom_fields',
    REPORTS: 'reports',
    DOCUMENTS: 'documents',
  },
  
  // Recruitment Modülü
  [MODULES.RECRUITMENT]: {
    POSITIONS: 'positions',
    APPLICATIONS: 'applications',
    CV_POOL: 'cv_pool',
    CUSTOM_FIELDS: 'custom_fields',
  },
  
  // Leaves Modülü
  [MODULES.LEAVES]: {
    REQUESTS: 'requests',
    TYPES: 'types',
    BALANCES: 'balances',
    CALENDAR: 'calendar',
    HOLIDAYS: 'holidays',
    ACCRUAL_POLICIES: 'accrual_policies',
    CUSTOM_FIELDS: 'custom_fields',
  },
  
  // Documents Modülü
  [MODULES.DOCUMENTS]: {
    LIST: 'list',
    CATEGORIES: 'categories',
    CUSTOM_FIELDS: 'custom_fields',
  },
  
  // Onboarding Modülü
  [MODULES.ONBOARDING]: {
    PROCESSES: 'processes',
    TEMPLATES: 'templates',
  },
  
  // Performance Modülü
  [MODULES.PERFORMANCE]: {
    REVIEWS: 'reviews',
    PERIODS: 'periods',
    CRITERIA: 'criteria',
    OKR: 'okr',
    FEEDBACK: 'feedback',
    COMPETENCIES: 'competencies',
    ONE_ON_ONE: 'one_on_one',
    CUSTOM_FIELDS: 'custom_fields',
  },
  
  // Training Modülü
  [MODULES.TRAINING]: {
    LIST: 'list',
    SESSIONS: 'sessions',
    CUSTOM_FIELDS: 'custom_fields',
  },
  
  // Assets Modülü
  [MODULES.ASSETS]: {
    LIST: 'list',
    CATEGORIES: 'categories',
    ASSIGNMENTS: 'assignments',
    MAINTENANCE: 'maintenance',
    CUSTOM_FIELDS: 'custom_fields',
  },
  
  // Surveys Modülü
  [MODULES.SURVEYS]: {
    LIST: 'list',
  },
  
  // Analytics Modülü
  [MODULES.ANALYTICS]: {
    REPORTS: 'reports',
  },
  
  // Timesheet Modülü
  [MODULES.TIMESHEET]: {
    ATTENDANCE: 'attendance',
    SHIFTS: 'shifts',
    KIOSK: 'kiosk',
  },
  
  // Expenses Modülü
  [MODULES.EXPENSES]: {
    CLAIMS: 'claims',
    CATEGORIES: 'categories',
  },
} as const;

// Sayfa bazlı aksiyon tanımları (hangi sayfada hangi aksiyonlar mevcut)
export const PAGE_ACTIONS: Record<string, Record<string, ActionType[]>> = {
  [MODULES.MANAGEMENT]: {
    users: ['view', 'create', 'edit', 'delete', 'export', 'import'],
    roles: ['view', 'create', 'edit', 'delete'],
    branches: ['view', 'create', 'edit', 'delete'],
    audit_logs: ['view', 'export'],
    settings: ['view', 'edit'],
    webhooks: ['view', 'create', 'edit', 'delete'],
    api_keys: ['view', 'create', 'edit', 'delete'],
    workflows: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.EMPLOYEES]: {
    list: ['view', 'create', 'edit', 'delete', 'export', 'import'],
    departments: ['view', 'create', 'edit', 'delete'],
    positions: ['view', 'create', 'edit', 'delete'],
    organization: ['view'],
    custom_fields: ['view', 'create', 'edit', 'delete'],
    reports: ['view', 'export'],
    documents: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.RECRUITMENT]: {
    positions: ['view', 'create', 'edit', 'delete'],
    applications: ['view', 'edit', 'delete', 'approve'],
    cv_pool: ['view', 'export'],
    custom_fields: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.LEAVES]: {
    requests: ['view', 'create', 'edit', 'delete', 'approve', 'cancel'],
    types: ['view', 'create', 'edit', 'delete'],
    balances: ['view', 'edit'],
    calendar: ['view'],
    holidays: ['view', 'create', 'edit', 'delete'],
    accrual_policies: ['view', 'create', 'edit', 'delete'],
    custom_fields: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.DOCUMENTS]: {
    list: ['view', 'create', 'edit', 'delete', 'approve'],
    categories: ['view', 'create', 'edit', 'delete'],
    custom_fields: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.ONBOARDING]: {
    processes: ['view', 'create', 'edit', 'delete'],
    templates: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.PERFORMANCE]: {
    reviews: ['view', 'create', 'edit', 'delete', 'approve'],
    periods: ['view', 'create', 'edit', 'delete'],
    criteria: ['view', 'create', 'edit', 'delete'],
    okr: ['view', 'create', 'edit', 'delete'],
    feedback: ['view', 'create'],
    competencies: ['view', 'create', 'edit', 'delete'],
    one_on_one: ['view', 'create', 'edit', 'delete'],
    custom_fields: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.TRAINING]: {
    list: ['view', 'create', 'edit', 'delete'],
    sessions: ['view', 'create', 'edit', 'delete'],
    custom_fields: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.ASSETS]: {
    list: ['view', 'create', 'edit', 'delete', 'export'],
    categories: ['view', 'create', 'edit', 'delete'],
    assignments: ['view', 'create', 'edit', 'delete'],
    maintenance: ['view', 'create', 'edit', 'delete'],
    custom_fields: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.SURVEYS]: {
    list: ['view', 'create', 'edit', 'delete'],
  },
  [MODULES.ANALYTICS]: {
    reports: ['view', 'export'],
  },
  [MODULES.TIMESHEET]: {
    attendance: ['view', 'create', 'edit', 'approve'],
    shifts: ['view', 'create', 'edit', 'delete'],
    kiosk: ['view'],
  },
  [MODULES.EXPENSES]: {
    claims: ['view', 'create', 'edit', 'delete', 'approve'],
    categories: ['view', 'create', 'edit', 'delete'],
  },
};

// Modül etiketleri (UI için)
export const MODULE_LABELS: Record<ModuleType, string> = {
  [MODULES.DASHBOARD]: 'Dashboard',
  [MODULES.MANAGEMENT]: 'Yönetim',
  [MODULES.EMPLOYEES]: 'Personel',
  [MODULES.RECRUITMENT]: 'İşe Alım',
  [MODULES.LEAVES]: 'İzin Yönetimi',
  [MODULES.DOCUMENTS]: 'Evrak Yönetimi',
  [MODULES.ONBOARDING]: 'Onboarding',
  [MODULES.PERFORMANCE]: 'Performans',
  [MODULES.TRAINING]: 'Eğitim',
  [MODULES.ASSETS]: 'Varlık Yönetimi',
  [MODULES.SURVEYS]: 'Anketler',
  [MODULES.ANALYTICS]: 'HR Analitik',
  [MODULES.TIMESHEET]: 'Puantaj',
  [MODULES.EXPENSES]: 'Masraf Yönetimi',
};

// Sayfa etiketleri (UI için)
export const PAGE_LABELS: Record<string, Record<string, string>> = {
  [MODULES.MANAGEMENT]: {
    users: 'Kullanıcılar',
    roles: 'Roller',
    branches: 'Şubeler',
    audit_logs: 'Log & Denetim',
    settings: 'Ayarlar',
    webhooks: 'Webhook\'lar',
    api_keys: 'API Anahtarları',
    workflows: 'Onay Akışları',
  },
  [MODULES.EMPLOYEES]: {
    list: 'Personel Listesi',
    departments: 'Departmanlar',
    positions: 'Pozisyonlar',
    organization: 'Organizasyon Şeması',
    custom_fields: 'Özel Alanlar',
    reports: 'Raporlar',
    documents: 'Belgeler',
  },
  [MODULES.RECRUITMENT]: {
    positions: 'Pozisyonlar',
    applications: 'Başvurular',
    cv_pool: 'CV Havuzu',
    custom_fields: 'Özel Alanlar',
  },
  [MODULES.LEAVES]: {
    requests: 'İzin Talepleri',
    types: 'İzin Türleri',
    balances: 'İzin Bakiyeleri',
    calendar: 'Takvim',
    holidays: 'Tatil Günleri',
    accrual_policies: 'Hakediş Politikaları',
    custom_fields: 'Özel Alanlar',
  },
  [MODULES.DOCUMENTS]: {
    list: 'Belgeler',
    categories: 'Kategoriler',
    custom_fields: 'Özel Alanlar',
  },
  [MODULES.ONBOARDING]: {
    processes: 'Süreçler',
    templates: 'Şablonlar',
  },
  [MODULES.PERFORMANCE]: {
    reviews: 'Değerlendirmeler',
    periods: 'Dönemler',
    criteria: 'Kriterler',
    okr: 'OKR',
    feedback: 'Geri Bildirim',
    competencies: 'Yetkinlikler',
    one_on_one: '1-on-1 Görüşmeler',
    custom_fields: 'Özel Alanlar',
  },
  [MODULES.TRAINING]: {
    list: 'Eğitimler',
    sessions: 'Oturumlar',
    custom_fields: 'Özel Alanlar',
  },
  [MODULES.ASSETS]: {
    list: 'Varlık Listesi',
    categories: 'Kategoriler',
    assignments: 'Atamalar',
    maintenance: 'Bakım',
    custom_fields: 'Özel Alanlar',
  },
  [MODULES.SURVEYS]: {
    list: 'Anket Listesi',
  },
  [MODULES.ANALYTICS]: {
    reports: 'Raporlar',
  },
  [MODULES.TIMESHEET]: {
    attendance: 'Puantaj',
    shifts: 'Vardiyalar',
    kiosk: 'PDKS Ekranı',
  },
  [MODULES.EXPENSES]: {
    claims: 'Masraf Talepleri',
    categories: 'Kategoriler',
  },
};

// Aksiyon etiketleri (UI için)
export const ACTION_LABELS: Record<ActionType, string> = {
  [ACTIONS.VIEW]: 'Görüntüle',
  [ACTIONS.CREATE]: 'Oluştur',
  [ACTIONS.EDIT]: 'Düzenle',
  [ACTIONS.DELETE]: 'Sil',
  [ACTIONS.EXPORT]: 'Dışa Aktar',
  [ACTIONS.IMPORT]: 'İçe Aktar',
  [ACTIONS.APPROVE]: 'Onayla',
  [ACTIONS.CANCEL]: 'İptal',
};

// Yetki oluşturma yardımcı fonksiyonu
export function createPermission(module: string, page: string, action: string): string {
  return `${module}.${page}.${action}`;
}

/**
 * Portal self-servis izinleri (employee rol seti).
 * Bunların dışında kalan herhangi bir izin = Company panel erişimi.
 */
export const PORTAL_SELF_PERMISSIONS: readonly string[] = [
  'employees.list.view',
  'employees.view',
  'documents.list.view',
  'documents.view',
  'leaves.requests.view',
  'leaves.requests.create',
  'leaves.calendar.view',
  'leaves.view',
  'leaves.create',
  'training.list.view',
  'training.sessions.view',
  'trainings.view',
  'performance.reviews.view',
  'performance.feedback.view',
] as const;

export type PanelAccessUser = {
  type?: string | null;
  permissions?: string[] | null;
  roles?: Array<string | { name: string }> | null;
};

/**
 * Company panel erişimi var mı? (izin tabanlı; type=user tek başına yetmez)
 */
export function hasPanelAccess(user: PanelAccessUser | null | undefined): boolean {
  if (!user) return false;

  if (user.type === 'super_admin' || user.type === 'company_admin') {
    return true;
  }

  const roles = user.roles ?? [];
  const roleNames = roles.map((r) => (typeof r === 'string' ? r : r.name));
  if (roleNames.includes('admin')) {
    return true;
  }

  const permissions = user.permissions ?? [];
  return permissions.some((p) => !PORTAL_SELF_PERMISSIONS.includes(p));
}

// Wildcard yetki kontrolü
export function matchesPermission(
  userPermissions: string[],
  requiredModule: string,
  requiredPage: string,
  requiredAction: string
): boolean {
  const fullPermission = createPermission(requiredModule, requiredPage, requiredAction);
  
  // Tam eşleşme
  if (userPermissions.includes(fullPermission)) {
    return true;
  }
  
  // Sayfa wildcard (module.page.*)
  if (userPermissions.includes(`${requiredModule}.${requiredPage}.*`)) {
    return true;
  }
  
  // Modül wildcard (module.*)
  if (userPermissions.includes(`${requiredModule}.*`)) {
    return true;
  }
  
  // Global wildcard (*)
  if (userPermissions.includes('*')) {
    return true;
  }
  
  return false;
}

// Tüm yetkileri oluştur (Backend seeder için referans)
export function generateAllPermissions(): string[] {
  const permissions: string[] = [];
  
  Object.entries(PAGE_ACTIONS).forEach(([module, pages]) => {
    Object.entries(pages).forEach(([page, actions]) => {
      actions.forEach(action => {
        permissions.push(createPermission(module, page, action));
      });
    });
  });
  
  return permissions;
}

// Modül bazlı wildcard yetkileri
export function generateModuleWildcards(): string[] {
  return Object.values(MODULES).map(module => `${module}.*`);
}

// Route path'ten modül ve sayfa bilgisi çıkar
export function getPermissionFromPath(path: string): { module: string; page: string } | null {
  const pathMappings: Record<string, { module: string; page: string }> = {
    '/users': { module: 'management', page: 'users' },
    '/roles': { module: 'management', page: 'roles' },
    '/branches': { module: 'management', page: 'branches' },
    '/audit-logs': { module: 'management', page: 'audit_logs' },
    '/settings': { module: 'management', page: 'settings' },
    '/webhooks': { module: 'management', page: 'webhooks' },
    '/employees': { module: 'employees', page: 'list' },
    '/employees/departments': { module: 'employees', page: 'departments' },
    '/employees/positions': { module: 'employees', page: 'positions' },
    '/employees/organization': { module: 'employees', page: 'organization' },
    '/employees/custom-fields': { module: 'employees', page: 'custom_fields' },
    '/employees/reports': { module: 'employees', page: 'reports' },
    '/recruitment/positions': { module: 'recruitment', page: 'positions' },
    '/recruitment/applications': { module: 'recruitment', page: 'applications' },
    '/recruitment/custom-fields': { module: 'recruitment', page: 'custom_fields' },
    '/leaves': { module: 'leaves', page: 'requests' },
    '/leaves/types': { module: 'leaves', page: 'types' },
    '/leaves/balances': { module: 'leaves', page: 'balances' },
    '/leaves/custom-fields': { module: 'leaves', page: 'custom_fields' },
    '/documents': { module: 'documents', page: 'list' },
    '/documents/categories': { module: 'documents', page: 'categories' },
    '/documents/custom-fields': { module: 'documents', page: 'custom_fields' },
    '/onboarding': { module: 'onboarding', page: 'processes' },
    '/onboarding/templates': { module: 'onboarding', page: 'templates' },
    '/performance': { module: 'performance', page: 'reviews' },
    '/performance/periods': { module: 'performance', page: 'periods' },
    '/performance/criteria': { module: 'performance', page: 'criteria' },
    '/performance/custom-fields': { module: 'performance', page: 'custom_fields' },
    '/training': { module: 'training', page: 'list' },
    '/training/sessions': { module: 'training', page: 'sessions' },
    '/training/custom-fields': { module: 'training', page: 'custom_fields' },
    '/assets': { module: 'assets', page: 'list' },
    '/assets/categories': { module: 'assets', page: 'categories' },
    '/assets/assignments': { module: 'assets', page: 'assignments' },
    '/assets/custom-fields': { module: 'assets', page: 'custom_fields' },
    '/surveys': { module: 'surveys', page: 'list' },
    '/analytics': { module: 'analytics', page: 'reports' },
  };
  
  // Tam eşleşme
  if (pathMappings[path]) {
    return pathMappings[path];
  }
  
  // Prefix eşleşme (detay sayfaları için)
  for (const [mappedPath, permission] of Object.entries(pathMappings)) {
    if (path.startsWith(mappedPath + '/')) {
      return permission;
    }
  }
  
  return null;
}


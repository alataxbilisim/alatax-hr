import React from 'react';
import {
  BsSpeedometer2,
  BsPeople,
  BsBriefcase,
  BsFileEarmarkText,
  BsCalendarCheck,
  BsPersonCheck,
  BsPersonBadge,
  BsGraphUp,
  BsMortarboard,
  BsLaptop,
  BsClipboardData,
  BsBarChartLine,
} from 'react-icons/bs';
// Menü öğesi için yetki bilgisi
export interface MenuItemPermission {
  module: string;
  page: string;
}

export interface MenuItem {
  path: string;
  label: string;
  badge?: number;
  permission?: MenuItemPermission; // Yetki bilgisi
}

export interface ModuleGroup {
  id: string;
  icon: React.ElementType;
  label: string;
  color?: string;
  basePath?: string;
  items: MenuItem[];
  moduleKey?: string;
  permissionModule?: string; // Modül bazlı yetki kontrolü için
}

// Module definitions with sub-menus and permissions
export const moduleGroups: ModuleGroup[] = [
  {
    id: 'dashboard',
    icon: BsSpeedometer2,
    label: 'Dashboard',
    color: '#10b981',
    basePath: '/dashboard',
    items: [
      { path: '/dashboard', label: 'Genel Bakış' },
    ],
  },
  {
    id: 'management',
    icon: BsPeople,
    label: 'Yönetim',
    color: '#6366f1',
    permissionModule: 'management',
    items: [
      { path: '/users', label: 'Kullanıcılar', permission: { module: 'management', page: 'users' } },
      { path: '/roles', label: 'Roller', permission: { module: 'management', page: 'roles' } },
      { path: '/branches', label: 'Şubeler', permission: { module: 'management', page: 'branches' } },
      { path: '/audit-logs', label: 'Log & Denetim', permission: { module: 'management', page: 'audit_logs' } },
      { path: '/settings', label: 'Ayarlar', permission: { module: 'management', page: 'settings' } },
      { path: '/lookups', label: 'Listeler', permission: { module: 'management', page: 'lookups' } },
      { path: '/webhooks', label: 'Webhook\'lar', permission: { module: 'management', page: 'webhooks' } },
    ],
  },
  {
    id: 'employees',
    icon: BsPersonBadge,
    label: 'Personel',
    color: '#22c55e',
    basePath: '/employees',
    permissionModule: 'employees',
    items: [
      { path: '/employees', label: 'Personel Listesi', permission: { module: 'employees', page: 'list' } },
      { path: '/employees/departments', label: 'Departmanlar', permission: { module: 'employees', page: 'departments' } },
      { path: '/employees/organization', label: 'Organizasyon Şeması', permission: { module: 'employees', page: 'organization' } },
      { path: '/employees/custom-fields', label: 'Özel Alanlar', permission: { module: 'employees', page: 'custom_fields' } },
      { path: '/employees/reports', label: 'Raporlar', permission: { module: 'employees', page: 'reports' } },
    ],
  },
  {
    id: 'recruitment',
    icon: BsBriefcase,
    label: 'İşe Alım',
    color: '#f59e0b',
    basePath: '/recruitment',
    moduleKey: 'job-applications',
    permissionModule: 'recruitment',
    items: [
      { path: '/recruitment/positions', label: 'Pozisyonlar', permission: { module: 'recruitment', page: 'positions' } },
      { path: '/recruitment/applications', label: 'Başvurular', permission: { module: 'recruitment', page: 'applications' } },
      { path: '/recruitment/interviews', label: 'Mülakatlar', permission: { module: 'recruitment', page: 'applications' } },
      { path: '/recruitment/cv-pool', label: 'CV Havuzu', permission: { module: 'recruitment', page: 'cv_pool' } },
      { path: '/recruitment/reports', label: 'Raporlar', permission: { module: 'recruitment', page: 'applications' } },
      { path: '/recruitment/custom-fields', label: 'Özel Alanlar', permission: { module: 'recruitment', page: 'custom_fields' } },
    ],
  },
  {
    id: 'leaves',
    icon: BsCalendarCheck,
    label: 'İzinler',
    color: '#06b6d4',
    basePath: '/leaves',
    moduleKey: 'leave-management',
    permissionModule: 'leaves',
    items: [
      { path: '/leaves', label: 'İzin Talepleri', permission: { module: 'leaves', page: 'requests' } },
      { path: '/leaves/types', label: 'İzin Türleri', permission: { module: 'leaves', page: 'types' } },
      { path: '/leaves/balances', label: 'Bakiyeler', permission: { module: 'leaves', page: 'balances' } },
      { path: '/leaves/calendar', label: 'Takvim', permission: { module: 'leaves', page: 'calendar' } },
      { path: '/leaves/holidays', label: 'Tatiller', permission: { module: 'leaves', page: 'holidays' } },
      { path: '/leaves/policies', label: 'Hakediş Politikaları', permission: { module: 'leaves', page: 'accrual_policies' } },
      { path: '/leaves/reports', label: 'Raporlar', permission: { module: 'leaves', page: 'requests' } },
      { path: '/leaves/custom-fields', label: 'Özel Alanlar', permission: { module: 'leaves', page: 'custom_fields' } },
    ],
  },
  {
    id: 'documents',
    icon: BsFileEarmarkText,
    label: 'Evraklar',
    color: '#8b5cf6',
    basePath: '/documents',
    moduleKey: 'document-management',
    permissionModule: 'documents',
    items: [
      { path: '/documents', label: 'Belgeler', permission: { module: 'documents', page: 'list' } },
      { path: '/documents/categories', label: 'Kategoriler', permission: { module: 'documents', page: 'categories' } },
      { path: '/documents/reports', label: 'Raporlar', permission: { module: 'documents', page: 'reports' } },
      { path: '/documents/custom-fields', label: 'Özel Alanlar', permission: { module: 'documents', page: 'custom_fields' } },
    ],
  },
  {
    id: 'onboarding',
    icon: BsPersonCheck,
    label: 'Onboarding',
    color: '#ec4899',
    basePath: '/onboarding',
    moduleKey: 'onboarding',
    permissionModule: 'onboarding',
    items: [
      { path: '/onboarding', label: 'Süreçler', permission: { module: 'onboarding', page: 'processes' } },
      { path: '/onboarding/templates', label: 'Şablonlar', permission: { module: 'onboarding', page: 'templates' } },
    ],
  },
  {
    id: 'performance',
    icon: BsGraphUp,
    label: 'Performans',
    color: '#14b8a6',
    basePath: '/performance',
    moduleKey: 'performance',
    permissionModule: 'performance',
    items: [
      { path: '/performance', label: 'Değerlendirmeler', permission: { module: 'performance', page: 'reviews' } },
      { path: '/performance/periods', label: 'Dönemler', permission: { module: 'performance', page: 'periods' } },
      { path: '/performance/criteria', label: 'Kriterler', permission: { module: 'performance', page: 'criteria' } },
      { path: '/performance/custom-fields', label: 'Özel Alanlar', permission: { module: 'performance', page: 'custom_fields' } },
    ],
  },
  {
    id: 'training',
    icon: BsMortarboard,
    label: 'Eğitim',
    color: '#f97316',
    basePath: '/training',
    moduleKey: 'training',
    permissionModule: 'training',
    items: [
      { path: '/training', label: 'Eğitimler', permission: { module: 'training', page: 'list' } },
      { path: '/training/sessions', label: 'Oturumlar', permission: { module: 'training', page: 'sessions' } },
      { path: '/training/custom-fields', label: 'Özel Alanlar', permission: { module: 'training', page: 'custom_fields' } },
    ],
  },
  {
    id: 'assets',
    icon: BsLaptop,
    label: 'Varlıklar',
    color: '#64748b',
    basePath: '/assets',
    moduleKey: 'asset-management',
    permissionModule: 'assets',
    items: [
      { path: '/assets', label: 'Varlık Listesi', permission: { module: 'assets', page: 'list' } },
      { path: '/assets/categories', label: 'Kategoriler', permission: { module: 'assets', page: 'categories' } },
      { path: '/assets/assignments', label: 'Atamalar', permission: { module: 'assets', page: 'assignments' } },
      { path: '/assets/custom-fields', label: 'Özel Alanlar', permission: { module: 'assets', page: 'custom_fields' } },
    ],
  },
  {
    id: 'surveys',
    icon: BsClipboardData,
    label: 'Anketler',
    color: '#a855f7',
    basePath: '/surveys',
    moduleKey: 'surveys',
    permissionModule: 'surveys',
    items: [
      { path: '/surveys', label: 'Anket Listesi', permission: { module: 'surveys', page: 'list' } },
    ],
  },
  {
    id: 'analytics',
    icon: BsBarChartLine,
    label: 'Analitik',
    color: '#0ea5e9',
    basePath: '/analytics',
    moduleKey: 'hr-analytics',
    permissionModule: 'analytics',
    items: [
      { path: '/analytics', label: 'Raporlar', permission: { module: 'analytics', page: 'reports' } },
    ],
  },
];

export function getFilteredMenuItems(
  module: ModuleGroup,
  user: { type: string; permissions: string[] } | null
): MenuItem[] {
  if (!user) return [];
  
  // Company Admin ve Super Admin her şeyi görebilir
  if (user.type === 'company_admin' || user.type === 'super_admin') {
    return module.items;
  }

  return module.items.filter(item => {
    if (!item.permission) return true;
    
    const { module: permModule, page } = item.permission;
    const permissions = user.permissions || [];

    // Wildcard kontrolleri
    if (permissions.includes('*')) return true;
    if (permissions.includes(`${permModule}.*`)) return true;
    if (permissions.includes(`${permModule}.${page}.*`)) return true;

    // Sayfaya ait herhangi bir aksiyon yetkisi var mı?
    return permissions.some(p => p.startsWith(`${permModule}.${page}.`));
  });
}


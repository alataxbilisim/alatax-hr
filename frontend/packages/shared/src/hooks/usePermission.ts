import { useSelector } from 'react-redux';
import { matchesPermission } from '../constants/permissions';

interface RootState {
  auth: {
    user: {
      type: string;
      permissions: string[];
      company?: {
        active_modules: string[];
      };
    } | null;
  };
}

/**
 * Hiyerarşik yetkilendirme hook'u
 * 
 * Kullanım:
 * const { hasPermission, canView, canCreate, canEdit, canDelete } = usePermission();
 * 
 * if (hasPermission('employees', 'list', 'view')) { ... }
 * if (canView('employees', 'departments')) { ... }
 * if (canCreate('leaves', 'requests')) { ... }
 */
export function usePermission() {
  const { user } = useSelector((state: RootState) => state.auth);

  /**
   * Yetki kontrolü
   * @param module - Modül adı (employees, leaves, etc.)
   * @param page - Sayfa adı (list, departments, etc.)
   * @param action - Aksiyon (view, create, edit, delete, etc.)
   */
  const hasPermission = (module: string, page: string, action: string): boolean => {
    if (!user) return false;

    // Company Admin ve Super Admin her şeyi yapabilir
    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return true;
    }

    const permissions = user.permissions || [];
    return matchesPermission(permissions, module, page, action);
  };

  /**
   * Modül erişimi kontrolü
   * @param module - Modül adı
   */
  const hasModuleAccess = (module: string): boolean => {
    if (!user) return false;

    // Company Admin ve Super Admin her şeyi yapabilir
    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return true;
    }

    const permissions = user.permissions || [];
    
    // Modül wildcard kontrolü
    if (permissions.includes(`${module}.*`) || permissions.includes('*')) {
      return true;
    }

    // Modüle ait herhangi bir yetki var mı?
    return permissions.some(p => p.startsWith(`${module}.`));
  };

  /**
   * Sayfa erişimi kontrolü
   * @param module - Modül adı
   * @param page - Sayfa adı
   */
  const hasPageAccess = (module: string, page: string): boolean => {
    if (!user) return false;

    // Company Admin ve Super Admin her şeyi yapabilir
    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return true;
    }

    const permissions = user.permissions || [];

    // Wildcard kontrolleri
    if (permissions.includes('*')) return true;
    if (permissions.includes(`${module}.*`)) return true;
    if (permissions.includes(`${module}.${page}.*`)) return true;

    // Sayfaya ait herhangi bir aksiyon yetkisi var mı?
    return permissions.some(p => p.startsWith(`${module}.${page}.`));
  };

  // Shorthand helper'lar
  const canView = (module: string, page: string) => hasPermission(module, page, 'view');
  const canCreate = (module: string, page: string) => hasPermission(module, page, 'create');
  const canEdit = (module: string, page: string) => hasPermission(module, page, 'edit');
  const canDelete = (module: string, page: string) => hasPermission(module, page, 'delete');
  const canExport = (module: string, page: string) => hasPermission(module, page, 'export');
  const canImport = (module: string, page: string) => hasPermission(module, page, 'import');
  const canApprove = (module: string, page: string) => hasPermission(module, page, 'approve');

  /**
   * Birden fazla yetki kontrolü (AND)
   */
  const hasAllPermissions = (permissions: Array<{ module: string; page: string; action: string }>): boolean => {
    return permissions.every(p => hasPermission(p.module, p.page, p.action));
  };

  /**
   * Birden fazla yetki kontrolü (OR)
   */
  const hasAnyPermission = (permissions: Array<{ module: string; page: string; action: string }>): boolean => {
    return permissions.some(p => hasPermission(p.module, p.page, p.action));
  };

  /**
   * Kullanıcının admin olup olmadığını kontrol et
   */
  const isAdmin = (): boolean => {
    return user?.type === 'company_admin' || user?.type === 'super_admin';
  };

  /**
   * Kullanıcının super admin olup olmadığını kontrol et
   */
  const isSuperAdmin = (): boolean => {
    return user?.type === 'super_admin';
  };

  return {
    // Temel kontroller
    hasPermission,
    hasModuleAccess,
    hasPageAccess,
    
    // CRUD shorthand'ler
    canView,
    canCreate,
    canEdit,
    canDelete,
    canExport,
    canImport,
    canApprove,
    
    // Çoklu kontroller
    hasAllPermissions,
    hasAnyPermission,
    
    // Admin kontrolleri
    isAdmin,
    isSuperAdmin,
    
    // User bilgisi
    user,
  };
}

export default usePermission;


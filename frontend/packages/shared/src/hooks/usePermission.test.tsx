import React from 'react';
import { renderHook } from '@testing-library/react';
import { Provider } from 'react-redux';
import { describe, expect, it } from 'vitest';
import type { User } from '../types/modules';
import {
  anonymousState,
  authenticatedState,
  companyAdminUser,
  createTestStore,
  superAdminUser,
  userWithPermissions,
} from '../test';
import { usePermission } from './usePermission';

function renderPermission(user: User | null) {
  const store = createTestStore({}, { auth: user ? authenticatedState(user) : anonymousState() });
  const wrapper: React.FC<{ children: React.ReactNode }> = ({ children }) => <Provider store={store}>{children}</Provider>;
  return renderHook(() => usePermission(), { wrapper }).result.current;
}

describe('usePermission', () => {
  it('oturum yoksa hiçbir izin vermez', () => {
    const perm = renderPermission(null);
    expect(perm.hasPermission('employees', 'list', 'view')).toBe(false);
    expect(perm.hasModuleAccess('employees')).toBe(false);
    expect(perm.hasPageAccess('employees', 'list')).toBe(false);
  });

  it('şirket yöneticisi ve süper yönetici her şeye erişir', () => {
    for (const user of [companyAdminUser(), superAdminUser()]) {
      const perm = renderPermission(user);
      expect(perm.hasPermission('reports', 'definitions', 'delete')).toBe(true);
      expect(perm.isAdmin()).toBe(true);
    }
    expect(renderPermission(superAdminUser()).isSuperAdmin()).toBe(true);
    expect(renderPermission(companyAdminUser()).isSuperAdmin()).toBe(false);
  });

  it('kullanıcı izinlerini tam eşleşme ve joker ile değerlendirir', () => {
    const perm = renderPermission(userWithPermissions(['employees.list.view', 'leaves.requests.*']));
    expect(perm.canView('employees', 'list')).toBe(true);
    expect(perm.canEdit('employees', 'list')).toBe(false);
    expect(perm.canApprove('leaves', 'requests')).toBe(true);
    expect(perm.hasAnyPermission([
      { module: 'assets', page: 'list', action: 'view' },
      { module: 'employees', page: 'list', action: 'view' },
    ])).toBe(true);
    expect(perm.hasAllPermissions([
      { module: 'employees', page: 'list', action: 'view' },
      { module: 'employees', page: 'list', action: 'delete' },
    ])).toBe(false);
  });

  it('modül erişimi: modüle ait herhangi bir izin veya modül jokeri yeterli', () => {
    const perm = renderPermission(userWithPermissions(['leaves.calendar.view', 'assets.*']));
    expect(perm.hasModuleAccess('leaves')).toBe(true);
    expect(perm.hasModuleAccess('assets')).toBe(true);
    expect(perm.hasModuleAccess('employees')).toBe(false);
  });

  it('sayfa erişimi: sayfa izni veya sayfa/modül/global joker', () => {
    expect(renderPermission(userWithPermissions(['leaves.balances.edit'])).hasPageAccess('leaves', 'balances')).toBe(true);
    expect(renderPermission(userWithPermissions(['leaves.balances.edit'])).hasPageAccess('leaves', 'types')).toBe(false);
    expect(renderPermission(userWithPermissions(['leaves.types.*'])).hasPageAccess('leaves', 'types')).toBe(true);
    expect(renderPermission(userWithPermissions(['*'])).hasPageAccess('reports', 'schedules')).toBe(true);
  });
});

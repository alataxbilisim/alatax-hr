import { describe, expect, it } from 'vitest';
import { getPermissionFromPath, hasPanelAccess, matchesPermission } from './permissions';

describe('matchesPermission', () => {
  it('tam eşleşmeyi kabul eder', () => {
    expect(matchesPermission(['employees.list.view'], 'employees', 'list', 'view')).toBe(true);
    expect(matchesPermission(['employees.list.view'], 'employees', 'list', 'edit')).toBe(false);
  });

  it('sayfa, modül ve global joker izinleri kabul eder', () => {
    expect(matchesPermission(['employees.list.*'], 'employees', 'list', 'delete')).toBe(true);
    expect(matchesPermission(['employees.*'], 'employees', 'salary', 'view')).toBe(true);
    expect(matchesPermission(['*'], 'reports', 'definitions', 'create')).toBe(true);
  });

  it('başka modülün jokerini kabul etmez', () => {
    expect(matchesPermission(['leaves.*'], 'employees', 'list', 'view')).toBe(false);
    expect(matchesPermission(['employees.salary.*'], 'employees', 'list', 'view')).toBe(false);
  });
});

describe('hasPanelAccess', () => {
  it('backend panel_access bayrağını tek doğruluk kaynağı sayar', () => {
    expect(hasPanelAccess({ type: 'company_admin', panel_access: false })).toBe(false);
    expect(hasPanelAccess({ type: 'user', permissions: [], panel_access: true })).toBe(true);
  });

  it('bayrak yoksa yönetici tiplerine erişim verir', () => {
    expect(hasPanelAccess({ type: 'company_admin' })).toBe(true);
    expect(hasPanelAccess({ type: 'super_admin' })).toBe(true);
  });

  it('yalnız portal self-servis izinleri olan personele panel erişimi vermez', () => {
    expect(hasPanelAccess({ type: 'user', permissions: ['leaves.requests.view', 'leaves.requests.create'] })).toBe(false);
    expect(hasPanelAccess({ type: 'user', permissions: ['leaves.requests.approve'] })).toBe(true);
  });

  it('panel erişimli rolü tanır', () => {
    expect(hasPanelAccess({ type: 'user', roles: [{ name: 'ik', panel_access: true }] })).toBe(true);
  });

  it('kullanıcı yoksa erişim yok', () => {
    expect(hasPanelAccess(null)).toBe(false);
  });
});

describe('getPermissionFromPath', () => {
  it('kanonik ve eski yolları aynı izne eşler', () => {
    expect(getPermissionFromPath('/organization/departments')).toEqual({ module: 'employees', page: 'departments' });
    expect(getPermissionFromPath('/employees/departments')).toEqual({ module: 'employees', page: 'departments' });
  });

  it('detay sayfalarını önek eşleşmesiyle çözer', () => {
    expect(getPermissionFromPath('/employees/42')).toEqual({ module: 'employees', page: 'list' });
  });

  it('bilinmeyen yolda null döner', () => {
    expect(getPermissionFromPath('/olmayan')).toBeNull();
  });
});

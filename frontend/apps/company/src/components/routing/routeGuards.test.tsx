import React from 'react';
import { screen } from '@testing-library/react';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import {
  anonymousState,
  authenticatedState,
  companyAdminUser,
  makeCompany,
  renderWithProviders,
  userWithPermissions,
} from '@shared/test';
import { MODULE_KEYS } from '@shared/constants/modules';
import type { User } from '@shared/types';
import ModuleProtectedRoute from './ModuleProtectedRoute';
import PermissionProtectedRoute from './PermissionProtectedRoute';

function renderGuarded(element: React.ReactElement, user: User | null) {
  return renderWithProviders(
    <Routes>
      <Route path="/guarded" element={element} />
      <Route path="/login" element={<p>Giriş sayfası</p>} />
      <Route path="/dashboard" element={<p>Ana sayfa</p>} />
    </Routes>,
    { route: '/guarded', preloadedState: { auth: user ? authenticatedState(user) : anonymousState() } }
  );
}

describe('PermissionProtectedRoute', () => {
  const guarded = (
    <PermissionProtectedRoute module="employees" page="list" action="edit">
      <p>Korumalı içerik</p>
    </PermissionProtectedRoute>
  );

  it('oturum yoksa girişe yönlendirir', () => {
    renderGuarded(guarded, null);
    expect(screen.getByText('Giriş sayfası')).toBeInTheDocument();
  });

  it('izin varsa içeriği gösterir', () => {
    renderGuarded(guarded, userWithPermissions(['employees.list.edit']));
    expect(screen.getByText('Korumalı içerik')).toBeInTheDocument();
  });

  it('joker izinle içeriği gösterir', () => {
    renderGuarded(guarded, userWithPermissions(['employees.*']));
    expect(screen.getByText('Korumalı içerik')).toBeInTheDocument();
  });

  it('izin yoksa erişim engeli ve gereken izni gösterir', () => {
    renderGuarded(guarded, userWithPermissions(['employees.list.view']));
    expect(screen.queryByText('Korumalı içerik')).not.toBeInTheDocument();
    expect(screen.getByText('Erişim Engeli')).toBeInTheDocument();
    expect(screen.getByText('employees.list.edit')).toBeInTheDocument();
  });

  it('şirket yöneticisi izin listesinden bağımsız erişir', () => {
    renderGuarded(guarded, companyAdminUser({ permissions: [] }));
    expect(screen.getByText('Korumalı içerik')).toBeInTheDocument();
  });

  it('redirectTo verilmişse oraya yönlendirir', () => {
    renderGuarded(
      <PermissionProtectedRoute module="employees" page="list" action="edit" redirectTo="/dashboard">
        <p>Korumalı içerik</p>
      </PermissionProtectedRoute>,
      userWithPermissions([])
    );
    expect(screen.getByText('Ana sayfa')).toBeInTheDocument();
  });
});

describe('ModuleProtectedRoute', () => {
  const guarded = (
    <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
      <p>İzin modülü</p>
    </ModuleProtectedRoute>
  );

  it('modül şirkette aktifse içeriği gösterir', () => {
    renderGuarded(guarded, userWithPermissions([], { company: makeCompany({ active_modules: [MODULE_KEYS.LEAVE_MANAGEMENT] }) }));
    expect(screen.getByText('İzin modülü')).toBeInTheDocument();
  });

  it('modül kapalıysa şirket yöneticisine de erişim engeli gösterir', () => {
    renderGuarded(guarded, companyAdminUser({ company: makeCompany({ active_modules: [MODULE_KEYS.TRAINING] }) }));
    expect(screen.queryByText('İzin modülü')).not.toBeInTheDocument();
    expect(screen.getByText('Modül Erişim Engeli')).toBeInTheDocument();
  });

  it('oturum yoksa ana sayfaya yönlendirir', () => {
    renderGuarded(guarded, null);
    expect(screen.getByText('Ana sayfa')).toBeInTheDocument();
  });
});

import React, { useEffect } from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { RootState } from '../store';

const SUPERADMIN_DASHBOARD_URL = 'http://localhost:3001/dashboard';

const AuthLayout: React.FC = () => {
  const { isAuthenticated, user } = useSelector((state: RootState) => state.auth);
  const isSuperAdmin = Boolean(isAuthenticated && user?.type === 'super_admin');

  // Harici SuperAdmin SPA — render sırasında window.location mutate etme
  useEffect(() => {
    if (isSuperAdmin) {
      window.location.assign(SUPERADMIN_DASHBOARD_URL);
    }
  }, [isSuperAdmin]);

  if (isAuthenticated && user) {
    if (user.type === 'super_admin') {
      return null;
    }
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <div className="company-portal">
      <Outlet />
    </div>
  );
};

export default AuthLayout;

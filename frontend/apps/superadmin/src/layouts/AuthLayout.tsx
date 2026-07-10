import React from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { RootState } from '../store';

const AuthLayout: React.FC = () => {
  const { isAuthenticated, user } = useSelector((state: RootState) => state.auth);

  // If already authenticated and is super_admin, redirect to dashboard
  if (isAuthenticated && user?.type === 'super_admin') {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <div className="auth-layout">
      <div className="auth-container">
        <div className="auth-brand">
          <h1 className="admin-brand">ALATAX</h1>
          <p>SuperAdmin Panel</p>
        </div>
        <Outlet />
      </div>
    </div>
  );
};

export default AuthLayout;


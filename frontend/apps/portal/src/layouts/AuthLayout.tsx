import React from 'react';
import { Outlet } from 'react-router-dom';

const AuthLayout: React.FC = () => {
  return (
    <div className="auth-layout">
      <div className="auth-container">
        <div className="auth-brand">
          <h1>ALATAX</h1>
          <p>Personel Self-Servis Portalı</p>
        </div>
        <Outlet />
      </div>
    </div>
  );
};

export default AuthLayout;


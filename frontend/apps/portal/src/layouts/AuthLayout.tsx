import React from 'react';
import { Outlet } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';

const AuthLayout: React.FC = () => {
  const { t } = useTranslation('auth');

  return (
    <div className="auth-layout">
      <div className="auth-container">
        <div className="auth-brand">
          <h1>{t('auth:brandName')}</h1>
          <p>Personel Self-Servis Portalı</p>
        </div>
        <Outlet />
      </div>
    </div>
  );
};

export default AuthLayout;

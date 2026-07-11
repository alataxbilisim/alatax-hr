import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { RootState } from '../../store';
import { ModuleKey, MODULE_LABELS } from '@shared/constants/modules';
import { COMPANY_ROUTES } from '@shared/constants/routes';
import { BsShieldExclamation } from 'react-icons/bs';

interface ModuleProtectedRouteProps {
  children: React.ReactNode;
  moduleKey: ModuleKey;
  fallback?: React.ReactNode;
}

/**
 * Modül erişim kontrolü yapan route wrapper
 * Kullanıcının firmasında ilgili modül aktif değilse erişimi engeller
 */
const ModuleProtectedRoute: React.FC<ModuleProtectedRouteProps> = ({
  children,
  moduleKey,
  fallback,
}) => {
  const location = useLocation();
  const { user, isAuthenticated, isLoading } = useSelector((state: RootState) => state.auth);
  
  // Auth: sadece ilk yüklemede loading; arka plan tazelemede unmount yok
  if (isLoading && !isAuthenticated) {
    return (
      <div className="page-loading">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  // Not authenticated - redirect to login
  if (!isAuthenticated || !user) {
    return <Navigate to={COMPANY_ROUTES.DASHBOARD} state={{ from: location }} replace />;
  }

  // SuperAdmin has access to everything
  if (user.type === 'super_admin') {
    return <>{children}</>;
  }

  // Company Admin has access to everything in their company
  if (user.type === 'company_admin') {
    // Check if module is active for the company
    const activeModules = user.company?.active_modules || [];
    if (activeModules.includes(moduleKey)) {
      return <>{children}</>;
    }
  }

  // Regular user - check company's active modules
  const activeModules = user.company?.active_modules || [];
  
  if (activeModules.includes(moduleKey)) {
    return <>{children}</>;
  }

  // Module not active - show fallback or access denied page
  if (fallback) {
    return <>{fallback}</>;
  }

  // Default access denied UI
  return <ModuleAccessDenied moduleKey={moduleKey} />;
};

/**
 * Modül erişim engeli UI
 */
interface ModuleAccessDeniedProps {
  moduleKey: ModuleKey;
}

const ModuleAccessDenied: React.FC<ModuleAccessDeniedProps> = ({ moduleKey }) => {
  const moduleName = MODULE_LABELS[moduleKey] || moduleKey;

  return (
    <div className="module-access-denied">
      <div className="access-denied-content">
        <div className="access-denied-icon">
          <BsShieldExclamation />
        </div>
        <h2>Modül Erişim Engeli</h2>
        <p>
          <strong>{moduleName}</strong> modülüne erişim yetkiniz bulunmamaktadır.
        </p>
        <p className="text-muted">
          Bu modül firmanız için aktif değil veya lisansınıza dahil değil.
          Detaylı bilgi için sistem yöneticinizle iletişime geçin.
        </p>
        <a href={COMPANY_ROUTES.DASHBOARD} className="btn btn-primary">
          Ana Sayfaya Dön
        </a>
      </div>

      <style>{`
        .module-access-denied {
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 60vh;
          padding: 2rem;
        }

        .access-denied-content {
          text-align: center;
          max-width: 400px;
        }

        .access-denied-icon {
          width: 80px;
          height: 80px;
          background: rgba(239, 68, 68, 0.1);
          color: #ef4444;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          margin: 0 auto 1.5rem;
          font-size: 2.5rem;
        }

        .access-denied-content h2 {
          font-size: 1.5rem;
          font-weight: 600;
          color: var(--text-primary);
          margin-bottom: 0.75rem;
        }

        .access-denied-content p {
          color: var(--text-secondary);
          margin-bottom: 0.75rem;
        }

        .access-denied-content .text-muted {
          font-size: 0.875rem;
          color: var(--text-tertiary);
          margin-bottom: 1.5rem;
        }
      `}</style>
    </div>
  );
};

export default ModuleProtectedRoute;

// Re-export for convenience
export { ModuleAccessDenied };


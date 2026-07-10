import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { usePermission } from '@shared/hooks';
import { AUTH_ROUTES, COMPANY_ROUTES } from '@shared/constants/routes';
import { BsShieldExclamation } from 'react-icons/bs';

interface PermissionProtectedRouteProps {
  children: React.ReactNode;
  module: string;
  page: string;
  action?: string;
  fallback?: React.ReactNode;
  redirectTo?: string;
}

/**
 * Yetki bazlı route koruması
 * 
 * Kullanım:
 * <PermissionProtectedRoute module="employees" page="list" action="view">
 *   <EmployeesPage />
 * </PermissionProtectedRoute>
 */
const PermissionProtectedRoute: React.FC<PermissionProtectedRouteProps> = ({
  children,
  module,
  page,
  action = 'view',
  fallback,
  redirectTo,
}) => {
  const location = useLocation();
  const { hasPermission, user, isAdmin } = usePermission();

  // Kullanıcı yok - login'e yönlendir
  if (!user) {
    return <Navigate to={AUTH_ROUTES.LOGIN} state={{ from: location }} replace />;
  }

  // Admin her şeye erişebilir
  if (isAdmin()) {
    return <>{children}</>;
  }

  // Yetki kontrolü
  if (hasPermission(module, page, action)) {
    return <>{children}</>;
  }

  // Yetki yok - fallback veya redirect
  if (fallback) {
    return <>{fallback}</>;
  }

  if (redirectTo) {
    return <Navigate to={redirectTo} replace />;
  }

  // Varsayılan erişim engeli UI
  return <PermissionDenied module={module} page={page} action={action} />;
};

/**
 * Sayfa erişim koruması (sadece view yetkisi)
 */
export const PageProtectedRoute: React.FC<{
  children: React.ReactNode;
  module: string;
  page: string;
  fallback?: React.ReactNode;
}> = ({ children, module, page, fallback }) => {
  return (
    <PermissionProtectedRoute module={module} page={page} action="view" fallback={fallback}>
      {children}
    </PermissionProtectedRoute>
  );
};

/**
 * Yetki engeli UI
 */
interface PermissionDeniedProps {
  module: string;
  page: string;
  action: string;
}

const PermissionDenied: React.FC<PermissionDeniedProps> = ({ module, page, action }) => {
  return (
    <div className="permission-denied">
      <div className="permission-denied-content">
        <div className="permission-denied-icon">
          <BsShieldExclamation />
        </div>
        <h2>Erişim Engeli</h2>
        <p>
          Bu sayfaya erişim yetkiniz bulunmamaktadır.
        </p>
        <p className="text-muted">
          Gerekli yetki: <code>{module}.{page}.{action}</code>
        </p>
        <p className="text-muted">
          Erişim için sistem yöneticinizle iletişime geçin.
        </p>
        <a href={COMPANY_ROUTES.DASHBOARD} className="btn btn-primary">
          Ana Sayfaya Dön
        </a>
      </div>

      <style>{`
        .permission-denied {
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 60vh;
          padding: 2rem;
        }

        .permission-denied-content {
          text-align: center;
          max-width: 400px;
        }

        .permission-denied-icon {
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

        .permission-denied-content h2 {
          font-size: 1.5rem;
          font-weight: 600;
          color: var(--text-primary);
          margin-bottom: 0.75rem;
        }

        .permission-denied-content p {
          color: var(--text-secondary);
          margin-bottom: 0.75rem;
        }

        .permission-denied-content .text-muted {
          font-size: 0.875rem;
          color: var(--text-tertiary);
          margin-bottom: 1rem;
        }

        .permission-denied-content code {
          background: var(--bg-secondary);
          padding: 0.25rem 0.5rem;
          border-radius: 4px;
          font-size: 0.8rem;
        }
      `}</style>
    </div>
  );
};

export default PermissionProtectedRoute;

// Re-export
export { PermissionDenied };


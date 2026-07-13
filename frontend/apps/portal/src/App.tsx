import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { Provider } from 'react-redux';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';
import { useSelector } from 'react-redux';
import { store, RootState } from './store';

// Layouts
import AuthLayout from './layouts/AuthLayout';
import PortalLayout from './layouts/PortalLayout';

// Pages
import LoginPage from './pages/LoginPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import { InviteAcceptPage, ForcedPasswordChangePage } from '@shared/components';
import DashboardPage from './pages/DashboardPage';
import ProfilePage from './pages/ProfilePage';
import LeavesPage from './pages/LeavesPage';
import DocumentsPage from './pages/DocumentsPage';
import PayslipsPage from './pages/PayslipsPage';
import AnnouncementsPage from './pages/AnnouncementsPage';
import RequestsPage from './pages/RequestsPage';
import TrainingPage from './pages/TrainingPage';
import PerformancePage from './pages/PerformancePage';
import SurveysPage from './pages/SurveysPage';
import TimesheetPage from './pages/TimesheetPage';
import ExpensesPage from './pages/ExpensesPage';

// Styles
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/portal.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 5 * 60 * 1000,
    },
  },
});

// Protected Route Component
const ProtectedRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated, isLoading, user } = useSelector((state: RootState) => state.auth);
  const location = useLocation();

  if (isLoading && !isAuthenticated) {
    return (
      <div className="page-loading" style={{ height: '100vh' }}>
        <div className="loading-spinner"></div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (user?.must_change_password && location.pathname !== '/force-password-change') {
    return <Navigate to="/force-password-change" replace />;
  }

  return <>{children}</>;
};

// Public Route Component (redirect if already logged in)
const PublicRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated, isLoading } = useSelector((state: RootState) => state.auth);

  if (isLoading && !isAuthenticated) {
    return (
      <div className="page-loading" style={{ height: '100vh' }}>
        <div className="loading-spinner"></div>
      </div>
    );
  }

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  return <>{children}</>;
};

const AppRoutes: React.FC = () => {
  return (
    <Routes>
      {/* Public Routes */}
      <Route element={<AuthLayout />}>
        <Route
          path="/login"
          element={
            <PublicRoute>
              <LoginPage />
            </PublicRoute>
          }
        />
        <Route
          path="/forgot-password"
          element={
            <PublicRoute>
              <ForgotPasswordPage />
            </PublicRoute>
          }
        />
        <Route
          path="/reset-password"
          element={
            <PublicRoute>
              <ResetPasswordPage />
            </PublicRoute>
          }
        />
        <Route path="/invite/:token" element={<InviteAcceptPage panelLabelKey="portalPanel" />} />
        <Route
          path="/force-password-change"
          element={
            <ProtectedRoute>
              <ForcedPasswordChangePage panelLabelKey="portalPanel" />
            </ProtectedRoute>
          }
        />
      </Route>

      {/* Protected Routes */}
      <Route
        element={
          <ProtectedRoute>
            <PortalLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/profile" element={<ProfilePage />} />
        <Route path="/leaves" element={<LeavesPage />} />
        <Route path="/documents" element={<DocumentsPage />} />
        <Route path="/payslips" element={<PayslipsPage />} />
        <Route path="/training" element={<TrainingPage />} />
        <Route path="/performance" element={<PerformancePage />} />
        <Route path="/surveys" element={<SurveysPage />} />
        <Route path="/timesheet" element={<TimesheetPage />} />
        <Route path="/expenses" element={<ExpensesPage />} />
        <Route path="/announcements" element={<AnnouncementsPage />} />
        <Route path="/requests" element={<RequestsPage />} />
      </Route>

      {/* Redirect root to dashboard */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />

      {/* 404 - Not Found */}
      <Route
        path="*"
        element={
          <div className="page-loading" style={{ height: '100vh' }}>
            <h2>404 - Sayfa Bulunamadı</h2>
          </div>
        }
      />
    </Routes>
  );
};

const App: React.FC = () => {
  return (
    <Provider store={store}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <AppRoutes />
          <Toaster
            position="top-right"
            toastOptions={{
              duration: 4000,
              style: {
                background: 'var(--portal-surface)',
                color: 'var(--portal-text)',
                border: '1px solid var(--portal-border)',
              },
            }}
          />
        </BrowserRouter>
      </QueryClientProvider>
    </Provider>
  );
};

export default App;

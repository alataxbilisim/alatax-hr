import React, { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { Provider, useDispatch, useSelector } from 'react-redux';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';
import { setTheme } from '@shared/store/slices/themeSlice';
import { store, AppDispatch, RootState } from './store';

/** Portal varsayılanı açık tema — yalnızca kayıtlı tercih yoksa. */
const PortalThemeInit: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();

  useEffect(() => {
    if (!localStorage.getItem('theme')) {
      dispatch(setTheme('light'));
    }
  }, [dispatch]);

  return null;
};

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
import LeaveFormEnginePage from './pages/LeaveFormEnginePage';
import DocumentsPage from './pages/DocumentsPage';
import PayslipsPage from './pages/PayslipsPage';
import SalaryPage from './pages/SalaryPage';
import AnnouncementsPage from './pages/AnnouncementsPage';
import RequestsPage from './pages/RequestsPage';
import TrainingPage from './pages/TrainingPage';
import PerformancePage from './pages/PerformancePage';
import SurveysPage from './pages/SurveysPage';
import TimesheetPage from './pages/TimesheetPage';
import PortalQrScanPage from './pages/PortalQrScanPage';
import ExpensesPage from './pages/ExpensesPage';
import ExpenseFormEnginePage from './pages/ExpenseFormEnginePage';

// Styles
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/portal.css';
import './styles/portal-theme.css';

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
        <Route path="/leaves/form-engine" element={<LeaveFormEnginePage />} />
        <Route path="/documents" element={<DocumentsPage />} />
        <Route path="/payslips" element={<PayslipsPage />} />
        <Route path="/salary" element={<SalaryPage />} />
        <Route path="/training" element={<TrainingPage />} />
        <Route path="/performance" element={<PerformancePage />} />
        <Route path="/surveys" element={<SurveysPage />} />
        <Route path="/timesheet" element={<TimesheetPage />} />
        <Route path="/timesheet/qr" element={<PortalQrScanPage />} />
        <Route path="/expenses" element={<ExpensesPage />} />
        <Route path="/expenses/form-engine" element={<ExpenseFormEnginePage />} />
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
          <PortalThemeInit />
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

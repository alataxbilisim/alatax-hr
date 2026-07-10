import React, { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Provider, useSelector } from 'react-redux';
import { Toaster } from 'react-hot-toast';
import { store, RootState } from './store';

// Styles
import './styles/theme.css';
import './styles/components.css';
import './styles/layout.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

// Layouts
import MainLayout from './components/layout/MainLayout';
import AuthLayout from './components/layout/AuthLayout';

// Auth Pages
import LoginPage from './pages/auth/LoginPage';
import RegisterPage from './pages/auth/RegisterPage';

// Dashboard Pages
import DashboardPage from './pages/dashboard/DashboardPage';

// Company Pages
import UsersPage from './pages/users/UsersPage';
import RolesPage from './pages/roles/RolesPage';
import CompanySettingsPage from './pages/company/CompanySettingsPage';

// Recruitment Module Pages
import JobPositionsPage from './pages/recruitment/JobPositionsPage';
import FormBuilderPage from './pages/recruitment/FormBuilderPage';
import ApplicationsPage from './pages/recruitment/ApplicationsPage';
import CvPoolPage from './pages/recruitment/CvPoolPage';

// Documents Module
import DocumentsPage from './pages/documents/DocumentsPage';

// Leaves Module
import LeavesPage from './pages/leaves/LeavesPage';

// Onboarding Module
import OnboardingPage from './pages/onboarding/OnboardingPage';

// Reports & Settings
import ReportsPage from './pages/reports/ReportsPage';
import ProfilePage from './pages/settings/ProfilePage';

// Public Pages
import PublicApplicationPage from './pages/public/PublicApplicationPage';

// Admin Pages
import AdminDashboard from './pages/admin/AdminDashboard';
import AdminCompaniesPage from './pages/admin/AdminCompaniesPage';
import AdminModulesPage from './pages/admin/AdminModulesPage';
import AdminUsersPage from './pages/admin/AdminUsersPage';
import AdminLogsPage from './pages/admin/AdminLogsPage';
import AdminLicensesPage from './pages/admin/AdminLicensesPage';
import AdminPackagesPage from './pages/admin/AdminPackagesPage';

// Protected Route Component
const ProtectedRoute: React.FC<{ 
  children: React.ReactNode; 
  requiredType?: 'super_admin' | 'company_admin' | 'user';
}> = ({ children, requiredType }) => {
  const { isAuthenticated, user } = useSelector((state: RootState) => state.auth);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (requiredType && user?.type !== requiredType) {
    // SuperAdmin her yere erişebilir
    if (user?.type !== 'super_admin') {
      return <Navigate to="/dashboard" replace />;
    }
  }

  return <>{children}</>;
};


// App Wrapper with Theme
const AppContent: React.FC = () => {
  const { mode } = useSelector((state: RootState) => state.theme);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', mode);
  }, [mode]);

  return (
    <>
      <Toaster
        position="top-right"
        toastOptions={{
          duration: 4000,
          style: {
            background: 'var(--surface-primary)',
            color: 'var(--text-primary)',
            border: '1px solid var(--border-primary)',
          },
          success: {
            iconTheme: {
              primary: 'var(--success)',
              secondary: 'white',
            },
          },
          error: {
            iconTheme: {
              primary: 'var(--danger)',
              secondary: 'white',
            },
          },
        }}
      />
      
      <Routes>
        {/* Public Routes (no auth required) */}
        <Route path="/apply/:slug" element={<PublicApplicationPage />} />

        {/* Auth Routes */}
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
        </Route>

        {/* Protected Routes */}
        <Route element={<MainLayout />}>
          {/* Dashboard */}
          <Route path="/dashboard" element={
            <ProtectedRoute>
              <DashboardPage />
            </ProtectedRoute>
          } />

          {/* Company Management Routes */}
          <Route path="/users" element={<ProtectedRoute><UsersPage /></ProtectedRoute>} />
          <Route path="/roles" element={<ProtectedRoute><RolesPage /></ProtectedRoute>} />
          <Route path="/company" element={<ProtectedRoute><CompanySettingsPage /></ProtectedRoute>} />

          {/* Recruitment Module Routes */}
          <Route path="/recruitment" element={<ProtectedRoute><JobPositionsPage /></ProtectedRoute>} />
          <Route path="/recruitment/positions" element={<ProtectedRoute><JobPositionsPage /></ProtectedRoute>} />
          <Route path="/recruitment/forms" element={<ProtectedRoute><FormBuilderPage /></ProtectedRoute>} />
          <Route path="/recruitment/applications" element={<ProtectedRoute><ApplicationsPage /></ProtectedRoute>} />
          <Route path="/recruitment/cv-pool" element={<ProtectedRoute><CvPoolPage /></ProtectedRoute>} />

          {/* Documents Module */}
          <Route path="/documents" element={<ProtectedRoute><DocumentsPage /></ProtectedRoute>} />

          {/* Onboarding Module */}
          <Route path="/onboarding" element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>} />
          <Route path="/onboarding/*" element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>} />

          {/* Leaves Module */}
          <Route path="/leaves" element={<ProtectedRoute><LeavesPage /></ProtectedRoute>} />
          <Route path="/leaves/*" element={<ProtectedRoute><LeavesPage /></ProtectedRoute>} />

          {/* Reports & Settings */}
          <Route path="/reports" element={<ProtectedRoute><ReportsPage /></ProtectedRoute>} />
          <Route path="/settings" element={<ProtectedRoute><ProfilePage /></ProtectedRoute>} />
          <Route path="/profile" element={<ProtectedRoute><ProfilePage /></ProtectedRoute>} />

          {/* Admin Routes */}
          <Route path="/admin" element={
            <ProtectedRoute requiredType="super_admin">
              <AdminDashboard />
            </ProtectedRoute>
          } />
          <Route path="/admin/companies" element={
            <ProtectedRoute requiredType="super_admin">
              <AdminCompaniesPage />
            </ProtectedRoute>
          } />
          <Route path="/admin/modules" element={
            <ProtectedRoute requiredType="super_admin">
              <AdminModulesPage />
            </ProtectedRoute>
          } />
          <Route path="/admin/users" element={
            <ProtectedRoute requiredType="super_admin">
              <AdminUsersPage />
            </ProtectedRoute>
          } />
          <Route path="/admin/logs" element={
            <ProtectedRoute requiredType="super_admin">
              <AdminLogsPage />
            </ProtectedRoute>
          } />
          <Route path="/admin/licenses" element={
            <ProtectedRoute requiredType="super_admin">
              <AdminLicensesPage />
            </ProtectedRoute>
          } />
          <Route path="/admin/packages" element={
            <ProtectedRoute requiredType="super_admin">
              <AdminPackagesPage />
            </ProtectedRoute>
          } />
        </Route>

        {/* Redirect */}
        <Route path="/" element={<Navigate to="/login" replace />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </>
  );
};

const App: React.FC = () => {
  return (
    <Provider store={store}>
      <BrowserRouter>
        <AppContent />
      </BrowserRouter>
    </Provider>
  );
};

export default App;

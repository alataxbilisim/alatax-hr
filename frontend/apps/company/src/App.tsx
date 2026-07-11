import React, { useEffect } from 'react';
import { Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { RootState, AppDispatch } from './store';
import { checkAuth } from '@shared/store/slices/authSlice';
import { MODULE_KEYS } from '@shared/constants/modules';

// Layouts
import MainLayout from './layouts/MainLayout';
import AuthLayout from './layouts/AuthLayout';

// Error Boundary
import ErrorBoundary from './components/ErrorBoundary';

// Routing
import ModuleProtectedRoute from './components/routing/ModuleProtectedRoute';

// Auth Pages
import LoginPage from './pages/auth/LoginPage';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage';
import ResetPasswordPage from './pages/auth/ResetPasswordPage';

// Dashboard
import DashboardPage from './pages/DashboardPage';

// Users & Roles
import UsersPage from './pages/users/UsersPage';
import UserDetailPage from './pages/users/UserDetailPage';
import RolesPage from './pages/users/RolesPage';
import RoleDetailPage from './pages/users/RoleDetailPage';

// Company Settings
import SettingsPage from './pages/settings/SettingsPage';
import WebhooksPage from './pages/settings/WebhooksPage';
import CustomFieldsPage from './pages/settings/CustomFieldsPage';
import BranchesPage from './pages/branches/BranchesPage';
import BranchDetailPage from './pages/branches/BranchDetailPage';

// Employees
import EmployeesPage from './pages/employees/EmployeesPage';
import EmployeeForm from './components/EmployeeForm';
import EmployeeDetailPage from './pages/employees/EmployeeDetailPage';
import EmployeeCustomFieldsPage from './pages/employees/EmployeeCustomFieldsPage';
import DepartmentsPage from './pages/employees/DepartmentsPage';
import OrganizationChartPage from './pages/employees/OrganizationChartPage';
import EmployeeReportsPage from './pages/employees/EmployeeReportsPage';

// Module Custom Fields Pages
import LeaveCustomFieldsPage from './pages/leaves/LeaveCustomFieldsPage';
import DocumentCustomFieldsPage from './pages/documents/DocumentCustomFieldsPage';
import RecruitmentCustomFieldsPage from './pages/recruitment/RecruitmentCustomFieldsPage';
import PerformanceCustomFieldsPage from './pages/performance/PerformanceCustomFieldsPage';
import TrainingCustomFieldsPage from './pages/training/TrainingCustomFieldsPage';
import AssetCustomFieldsPage from './pages/assets/AssetCustomFieldsPage';

// Recruitment Module
import JobPositionsPage from './pages/recruitment/JobPositionsPage';
import ApplicationsPage from './pages/recruitment/ApplicationsPage';
import CvPoolPage from './pages/recruitment/CvPoolPage';
import InterviewsPage from './pages/recruitment/InterviewsPage';
import RecruitmentReportsPage from './pages/recruitment/RecruitmentReportsPage';

// Documents Module
import DocumentsPage from './pages/documents/DocumentsPage';
import DocumentDetailPage from './pages/documents/DocumentDetailPage';
import DocumentReportsPage from './pages/documents/DocumentReportsPage';

// Leaves Module
import LeavesPage from './pages/leaves/LeavesPage';

// Onboarding Module
import OnboardingPage from './pages/onboarding/OnboardingPage';
import ProcessDetailPage from './pages/onboarding/ProcessDetailPage';

// Performance Module
import PerformancePage from './pages/performance/PerformancePage';
import ReviewDetailPage from './pages/performance/ReviewDetailPage';

// Training Module
import TrainingPage from './pages/training/TrainingPage';

// Assets Module
import AssetsPage from './pages/assets/AssetsPage';
import AssetDetailPage from './pages/assets/AssetDetailPage';

// Audit Logs
import ActivityLogsPage from './pages/audit/ActivityLogsPage';

// Surveys Module
import SurveysPage from './pages/surveys/SurveysPage';

// Analytics Module
import AnalyticsPage from './pages/analytics/AnalyticsPage';

// Protected Route Component
interface ProtectedRouteProps {
  children: React.ReactNode;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const { isAuthenticated, user, isLoading } = useSelector((state: RootState) => state.auth);
  const navigate = useNavigate();

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      navigate('/login');
    }
    // SuperAdmin ise admin paneline yönlendir
    if (!isLoading && isAuthenticated && user?.type === 'super_admin') {
      window.location.href = 'http://localhost:3001/dashboard';
    }
  }, [isAuthenticated, user, isLoading, navigate]);

  // İlk auth belirlenene kadar loading; arka plan tazelemede unmount yok
  if (isLoading && !isAuthenticated) {
    return (
      <div className="loading-screen">
        <div className="loading-spinner" style={{ width: 40, height: 40 }}></div>
        <p style={{ marginTop: '1rem', color: 'var(--text-secondary)' }}>Yükleniyor...</p>
      </div>
    );
  }

  if (!isAuthenticated) {
    return null;
  }

  // SuperAdmin kontrolü - redirect'e izin ver
  if (user?.type === 'super_admin') {
    return null;
  }

  return <>{children}</>;
};

const App: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const { mode, density } = useSelector((state: RootState) => state.theme);
  const { isAuthenticated: authIsAuthenticated } = useSelector((state: RootState) => state.auth);

  // İlk yüklemede tam checkAuth (izin dump dahil)
  useEffect(() => {
    dispatch(checkAuth());
  }, [dispatch]);

  // Pencere focus: sessiz profil tazeleme (izin dump yok, unmount yok)
  useEffect(() => {
    if (!authIsAuthenticated) return;

    const handleFocus = () => {
      dispatch(checkAuth({ silent: true }));
    };

    window.addEventListener('focus', handleFocus);
    return () => window.removeEventListener('focus', handleFocus);
  }, [dispatch, authIsAuthenticated]);

  // Periyodik sessiz yenileme — 5 dk (önceki 30 sn agresifti + remount döngüsü)
  useEffect(() => {
    if (!authIsAuthenticated) return;

    const interval = setInterval(() => {
      dispatch(checkAuth({ silent: true }));
    }, 5 * 60 * 1000);

    return () => clearInterval(interval);
  }, [dispatch, authIsAuthenticated]);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', mode);
  }, [mode]);

  useEffect(() => {
    document.documentElement.setAttribute('data-density', density);
  }, [density]);

  return (
    <ErrorBoundary>
      <div className="app company-portal">
        <Routes>
        {/* Auth Routes */}
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
        </Route>

        {/* Protected Routes */}
        <Route element={<MainLayout />}>
          <Route
            path="/dashboard"
            element={<ProtectedRoute><DashboardPage /></ProtectedRoute>}
          />
          
          {/* Users & Roles - No module restriction, company admin only */}
          <Route
            path="/users"
            element={<ProtectedRoute><UsersPage /></ProtectedRoute>}
          />
          <Route
            path="/users/:id"
            element={<ProtectedRoute><UserDetailPage /></ProtectedRoute>}
          />
          <Route
            path="/roles"
            element={<ProtectedRoute><RolesPage /></ProtectedRoute>}
          />
          <Route
            path="/roles/:id"
            element={<ProtectedRoute><RoleDetailPage /></ProtectedRoute>}
          />
          
          {/* Employees - No module restriction */}
          <Route
            path="/employees"
            element={<ProtectedRoute><EmployeesPage /></ProtectedRoute>}
          />
          <Route
            path="/employees/new"
            element={<ProtectedRoute><EmployeeForm /></ProtectedRoute>}
          />
          <Route
            path="/employees/custom-fields"
            element={<ProtectedRoute><EmployeeCustomFieldsPage /></ProtectedRoute>}
          />
          <Route
            path="/employees/departments"
            element={<ProtectedRoute><DepartmentsPage /></ProtectedRoute>}
          />
          <Route
            path="/employees/organization"
            element={<ProtectedRoute><OrganizationChartPage /></ProtectedRoute>}
          />
          <Route
            path="/employees/reports"
            element={<ProtectedRoute><EmployeeReportsPage /></ProtectedRoute>}
          />
          <Route
            path="/employees/:id/edit"
            element={<ProtectedRoute><EmployeeForm /></ProtectedRoute>}
          />
          <Route
            path="/employees/:id"
            element={<ProtectedRoute><EmployeeDetailPage /></ProtectedRoute>}
          />
          
          <Route
            path="/audit-logs"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.AUDIT_LOGS}>
                  <ActivityLogsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Recruitment Module */}
          <Route
            path="/recruitment/positions"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.JOB_APPLICATIONS}>
                  <JobPositionsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/recruitment/applications"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.JOB_APPLICATIONS}>
                  <ApplicationsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/recruitment/custom-fields"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.JOB_APPLICATIONS}>
                  <RecruitmentCustomFieldsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/recruitment/cv-pool"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.JOB_APPLICATIONS}>
                  <CvPoolPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/recruitment/interviews"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.JOB_APPLICATIONS}>
                  <InterviewsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/recruitment/reports"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.JOB_APPLICATIONS}>
                  <RecruitmentReportsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Documents Module */}
          <Route
            path="/documents"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.DOCUMENT_MANAGEMENT}>
                  <DocumentsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/documents/categories"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.DOCUMENT_MANAGEMENT}>
                  <DocumentsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/documents/reports"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.DOCUMENT_MANAGEMENT}>
                  <DocumentReportsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/documents/custom-fields"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.DOCUMENT_MANAGEMENT}>
                  <DocumentCustomFieldsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/documents/:id"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.DOCUMENT_MANAGEMENT}>
                  <DocumentDetailPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Leaves Module */}
          <Route
            path="/leaves"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeavesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/leaves/types"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeavesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/leaves/balances"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeavesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/leaves/calendar"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeavesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/leaves/holidays"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeavesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/leaves/policies"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeavesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/leaves/reports"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeavesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/leaves/custom-fields"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.LEAVE_MANAGEMENT}>
                  <LeaveCustomFieldsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Onboarding Module */}
          <Route
            path="/onboarding"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.ONBOARDING}>
                  <OnboardingPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/onboarding/processes/:id"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.ONBOARDING}>
                  <ProcessDetailPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Performance Module */}
          <Route
            path="/performance"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.PERFORMANCE}>
                  <PerformancePage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/performance/reviews/:id"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.PERFORMANCE}>
                  <ReviewDetailPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/performance/custom-fields"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.PERFORMANCE}>
                  <PerformanceCustomFieldsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Training Module */}
          <Route
            path="/training"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.TRAINING}>
                  <TrainingPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/training/custom-fields"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.TRAINING}>
                  <TrainingCustomFieldsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Assets Module */}
          <Route
            path="/assets"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.ASSET_MANAGEMENT}>
                  <AssetsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/assets/:id"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.ASSET_MANAGEMENT}>
                  <AssetDetailPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/assets/custom-fields"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.ASSET_MANAGEMENT}>
                  <AssetCustomFieldsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Surveys Module */}
          <Route
            path="/surveys"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.SURVEYS}>
                  <SurveysPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Analytics Module */}
          <Route
            path="/analytics"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.HR_ANALYTICS}>
                  <AnalyticsPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Settings - No module restriction */}
          <Route
            path="/settings"
            element={<ProtectedRoute><SettingsPage /></ProtectedRoute>}
          />
          <Route
            path="/webhooks"
            element={<ProtectedRoute><WebhooksPage /></ProtectedRoute>}
          />
          <Route
            path="/settings/custom-fields"
            element={<ProtectedRoute><CustomFieldsPage /></ProtectedRoute>}
          />
          <Route
            path="/branches"
            element={<ProtectedRoute><BranchesPage /></ProtectedRoute>}
          />
          <Route
            path="/branches/:id"
            element={<ProtectedRoute><BranchDetailPage /></ProtectedRoute>}
          />
        </Route>

        {/* Redirects */}
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
      </div>
    </ErrorBoundary>
  );
};

export default App;

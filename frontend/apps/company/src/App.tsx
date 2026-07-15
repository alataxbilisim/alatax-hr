import React, { useEffect } from 'react';
import { Routes, Route, Navigate, useNavigate, useLocation } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { RootState, AppDispatch } from './store';
import { checkAuth, logout } from '@shared/store/slices/authSlice';
import { MODULE_KEYS } from '@shared/constants/modules';
import { hasPanelAccess } from '@shared/constants/permissions';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';

// Layouts
import MainLayout from './layouts/MainLayout';
import AuthLayout from './layouts/AuthLayout';

// Error Boundary
import ErrorBoundary from './components/ErrorBoundary';

// Routing
import ModuleProtectedRoute from './components/routing/ModuleProtectedRoute';
import PermissionProtectedRoute from './components/routing/PermissionProtectedRoute';

const PORTAL_LOGIN_URL =
  (import.meta.env.VITE_PORTAL_URL as string | undefined)?.replace(/\/$/, '') ||
  'http://localhost:3003';

/** Auth + panel erişimi (portal-only personel engeli) */
const ProtectedRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated, user, isLoading } = useSelector((state: RootState) => state.auth);
  const navigate = useNavigate();
  const location = useLocation();
  const dispatch = useDispatch<AppDispatch>();
  const { t } = useTranslation('auth');

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      navigate('/login');
    }
    if (!isLoading && isAuthenticated && user?.type === 'super_admin') {
      window.location.href = 'http://localhost:3001/dashboard';
    }
    if (!isLoading && isAuthenticated && user && user.type !== 'super_admin' && !hasPanelAccess(user)) {
      toast.error(t('login.panelAccessDenied'));
      void dispatch(logout());
      window.location.href = `${PORTAL_LOGIN_URL}/login`;
    }
    if (
      !isLoading &&
      isAuthenticated &&
      user?.must_change_password &&
      location.pathname !== '/force-password-change'
    ) {
      navigate('/force-password-change', { replace: true });
    }
  }, [isAuthenticated, user, isLoading, navigate, dispatch, t, location.pathname]);

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

  if (user?.type === 'super_admin') {
    return null;
  }

  if (user && !hasPanelAccess(user)) {
    return null;
  }

  if (user?.must_change_password && location.pathname !== '/force-password-change') {
    return <Navigate to="/force-password-change" replace />;
  }

  return <>{children}</>;
};

const withPermission = (
  page: React.ReactNode,
  module: string,
  pageKey: string,
  action = 'view'
) => (
  <ProtectedRoute>
    <PermissionProtectedRoute module={module} page={pageKey} action={action}>
      {page}
    </PermissionProtectedRoute>
  </ProtectedRoute>
);

// Auth Pages
import LoginPage from './pages/auth/LoginPage';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage';
import ResetPasswordPage from './pages/auth/ResetPasswordPage';
import { InviteAcceptPage, ForcedPasswordChangePage } from '@shared/components';
import PublicCareerApplyPage from './pages/recruitment/PublicCareerApplyPage';

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
import CustomFieldsIndexPage from './pages/settings/CustomFieldsIndexPage';
import LookupsPage from './pages/lookups/LookupsPage';
import AccountProfilePage from './pages/account/AccountProfilePage';
import AccountSecurityPage from './pages/account/AccountSecurityPage';
import AccountPreferencesPage from './pages/account/AccountPreferencesPage';
import BranchesPage from './pages/branches/BranchesPage';
import BranchDetailPage from './pages/branches/BranchDetailPage';

// Employees
import EmployeesPage from './pages/employees/EmployeesPage';
import EmployeeForm from './components/EmployeeForm';
import EmployeeFormEnginePage from './pages/employees/EmployeeFormEnginePage';
import LeaveRequestFormEnginePage from './pages/leaves/LeaveRequestFormEnginePage';
import FormLayoutEditorPage from './pages/settings/FormLayoutEditorPage';
import EmployeeDetailPage from './pages/employees/EmployeeDetailPage';
import EmployeeCustomFieldsPage from './pages/employees/EmployeeCustomFieldsPage';
import DepartmentsPage from './pages/employees/DepartmentsPage';
import PositionsPage from './pages/employees/PositionsPage';
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

// Expenses Module
import ExpensesPage from './pages/expenses/ExpensesPage';

// Attendance / Timesheet
import AttendancePage from './pages/attendance/AttendancePage';

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
          <Route path="/invite/:token" element={<InviteAcceptPage panelLabelKey="companyPanel" />} />
          <Route
            path="/force-password-change"
            element={
              <ProtectedRoute>
                <ForcedPasswordChangePage panelLabelKey="companyPanel" />
              </ProtectedRoute>
            }
          />
        </Route>

        {/* Public kariyer başvurusu (auth yok) */}
        <Route path="/careers/:companySlug/:positionSlug" element={<PublicCareerApplyPage />} />

        {/* Protected Routes */}
        <Route element={<MainLayout />}>
          <Route
            path="/dashboard"
            element={<ProtectedRoute><DashboardPage /></ProtectedRoute>}
          />
          
          {/* Users & Roles */}
          <Route
            path="/users"
            element={withPermission(<UsersPage />, 'management', 'users', 'view')}
          />
          <Route
            path="/users/:id"
            element={withPermission(<UserDetailPage />, 'management', 'users', 'view')}
          />
          <Route
            path="/roles"
            element={withPermission(<RolesPage />, 'management', 'roles', 'view')}
          />
          <Route
            path="/roles/:id"
            element={withPermission(<RoleDetailPage />, 'management', 'roles', 'view')}
          />
          
          {/* Employees */}
          <Route
            path="/employees"
            element={withPermission(<EmployeesPage />, 'employees', 'list', 'view')}
          />
          <Route
            path="/employees/new"
            element={withPermission(<EmployeeForm />, 'employees', 'list', 'create')}
          />
          <Route
            path="/employees/form-engine/new"
            element={withPermission(<EmployeeFormEnginePage />, 'employees', 'list', 'create')}
          />
          <Route
            path="/employees/form-engine/:id/edit"
            element={withPermission(<EmployeeFormEnginePage />, 'employees', 'list', 'edit')}
          />
          <Route
            path="/employees/custom-fields"
            element={withPermission(<EmployeeCustomFieldsPage />, 'employees', 'custom_fields', 'view')}
          />
          <Route
            path="/employees/departments"
            element={withPermission(<DepartmentsPage />, 'employees', 'departments', 'view')}
          />
          <Route
            path="/employees/positions"
            element={withPermission(<PositionsPage />, 'employees', 'positions', 'view')}
          />
          <Route
            path="/employees/organization"
            element={withPermission(<OrganizationChartPage />, 'employees', 'organization', 'view')}
          />
          <Route
            path="/employees/reports"
            element={withPermission(<EmployeeReportsPage />, 'employees', 'reports', 'view')}
          />
          <Route
            path="/employees/:id/edit"
            element={withPermission(<EmployeeForm />, 'employees', 'list', 'edit')}
          />
          <Route
            path="/employees/:id"
            element={withPermission(<EmployeeDetailPage />, 'employees', 'list', 'view')}
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
            path="/leaves/form-engine/new"
            element={withPermission(<LeaveRequestFormEnginePage />, 'leaves', 'requests', 'create')}
          />
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

          {/* Expenses Module */}
          <Route
            path="/expenses"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.EXPENSE_MANAGEMENT}>
                  <ExpensesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/expenses/all"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.EXPENSE_MANAGEMENT}>
                  <ExpensesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/expenses/categories"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.EXPENSE_MANAGEMENT}>
                  <ExpensesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />

          {/* Timesheet / Attendance */}
          <Route
            path="/attendance"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.TIMESHEET}>
                  <AttendancePage />
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
            path="/onboarding/templates"
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
            path="/performance/periods"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.PERFORMANCE}>
                  <PerformancePage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/performance/criteria"
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
            path="/training/sessions"
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
          
          {/* Assets Module — static paths before :id */}
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
            path="/assets/categories"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.ASSET_MANAGEMENT}>
                  <AssetsPage />
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
            path="/lookups"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="lookups" action="view">
                  <LookupsPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/webhooks"
            element={<ProtectedRoute><WebhooksPage /></ProtectedRoute>}
          />
          <Route
            path="/settings/custom-fields"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="custom_fields" action="view">
                  <CustomFieldsIndexPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings/forms/:entityType"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="forms" action="view">
                  <FormLayoutEditorPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/branches"
            element={<ProtectedRoute><BranchesPage /></ProtectedRoute>}
          />
          <Route
            path="/branches/:id"
            element={<ProtectedRoute><BranchDetailPage /></ProtectedRoute>}
          />

          {/* Kişisel hesap — permission gerekmez */}
          <Route path="/account" element={<ProtectedRoute><Navigate to="/account/profile" replace /></ProtectedRoute>} />
          <Route
            path="/account/profile"
            element={<ProtectedRoute><AccountProfilePage /></ProtectedRoute>}
          />
          <Route
            path="/account/security"
            element={<ProtectedRoute><AccountSecurityPage /></ProtectedRoute>}
          />
          <Route
            path="/account/preferences"
            element={<ProtectedRoute><AccountPreferencesPage /></ProtectedRoute>}
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

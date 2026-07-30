import React, { useEffect } from 'react';
import { Routes, Route, Navigate, useNavigate, useLocation, useParams } from 'react-router-dom';
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
import NotFoundPage from './pages/NotFoundPage';

const PORTAL_LOGIN_URL =
  (import.meta.env.VITE_PORTAL_URL as string | undefined)?.replace(/\/$/, '') ||
  'http://localhost:3003';

/** Auth + panel eriÅŸimi (portal-only personel engeli) */
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
        <p style={{ marginTop: '1rem', color: 'var(--text-secondary)' }}>YÃ¼kleniyor...</p>
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
      <React.Suspense fallback={<div className="page-loading">â€¦</div>}>{page}</React.Suspense>
    </PermissionProtectedRoute>
  </ProtectedRoute>
);

/** E0 — parametreli legacy path → kanonik path */
const LegacyParamRedirect: React.FC<{ to: string }> = ({ to }) => {
  const params = useParams();
  let target = to;
  Object.entries(params).forEach(([k, v]) => {
    target = target.replace(`:${k}`, v ?? '');
  });
  return <Navigate to={target} replace />;
};

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
import ReportPrivacySettingsPage from './pages/settings/ReportPrivacySettingsPage';
import ReportAccessLogsPage from './pages/audit/ReportAccessLogsPage';
import WebhooksPage from './pages/settings/WebhooksPage';
import CustomFieldsIndexPage from './pages/settings/CustomFieldsIndexPage';
import LookupsPage from './pages/lookups/LookupsPage';
import SettingsRegistryPage from './pages/settings/SettingsRegistryPage';
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
import FormsIndexPage from './pages/settings/FormsIndexPage';
import WorkflowsListPage from './pages/settings/WorkflowsListPage';
import NotificationTemplatesPage from './pages/settings/NotificationTemplatesPage';
import AssetFormEnginePage from './pages/assets/AssetFormEnginePage';
import EmployeeDetailPage from './pages/employees/EmployeeDetailPage';
import EmployeeCustomFieldsPage from './pages/employees/EmployeeCustomFieldsPage';
import DepartmentsPage from './pages/employees/DepartmentsPage';
import PositionsPage from './pages/employees/PositionsPage';
import SalaryBandsPage from './pages/employees/SalaryBandsPage';
import SalaryReviewPeriodsPage from './pages/employees/SalaryReviewPeriodsPage';
import SalaryReviewDetailPage from './pages/employees/SalaryReviewDetailPage';

// Module Custom Fields Pages
import LeaveCustomFieldsPage from './pages/leaves/LeaveCustomFieldsPage';
import DocumentCustomFieldsPage from './pages/documents/DocumentCustomFieldsPage';
import RecruitmentCustomFieldsPage from './pages/recruitment/RecruitmentCustomFieldsPage';
import PerformanceCustomFieldsPage from './pages/performance/PerformanceCustomFieldsPage';
import TrainingCustomFieldsPage from './pages/training/TrainingCustomFieldsPage';
import AssetCustomFieldsPage from './pages/assets/AssetCustomFieldsPage';

// Recruitment Module
import JobPositionsPage from './pages/recruitment/JobPositionsPage';
import CvPoolPage from './pages/recruitment/CvPoolPage';
import InterviewsPage from './pages/recruitment/InterviewsPage';
import RecruitmentReportsPage from './pages/recruitment/RecruitmentReportsPage';

// Documents Module
import DocumentsPage from './pages/documents/DocumentsPage';
import DocumentDetailPage from './pages/documents/DocumentDetailPage';

// Leaves Module
import LeavesPage from './pages/leaves/LeavesPage';

// Expenses Module
import ExpensesPage from './pages/expenses/ExpensesPage';

// Attendance / Timesheet
import AttendancePage from './pages/attendance/AttendancePage';
import PdksKioskPage from './pages/attendance/PdksKioskPage';
import ShiftsPage from './pages/attendance/ShiftsPage';
import ShiftAssignmentsPage from './pages/attendance/ShiftAssignmentsPage';
import AttendanceReportsPage from './pages/attendance/AttendanceReportsPage';

// Onboarding Module
import OnboardingPage from './pages/onboarding/OnboardingPage';
import ProcessDetailPage from './pages/onboarding/ProcessDetailPage';

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
import AnnouncementsPage from './pages/announcements/AnnouncementsPage';
import PayslipsAdminPage from './pages/payroll/PayslipsAdminPage';

/** D1b/D3 â€” aÄŸÄ±r sayfalar lazy (Nivo/org/kanban/form builder/analytics/kvkk) */
const ReportsListPage = React.lazy(() => import('./pages/reports/ReportsListPage'));
const ReportBuilderPage = React.lazy(() => import('./pages/reports/ReportBuilderPage'));
const MeasuresLibraryPage = React.lazy(() => import('./pages/reports/MeasuresLibraryPage'));
const ReportSchedulesPage = React.lazy(() => import('./pages/reports/ReportSchedulesPage'));
const DashboardsListPage = React.lazy(() => import('./pages/dashboards/DashboardsListPage'));
const DashboardViewPage = React.lazy(() => import('./pages/dashboards/DashboardViewPage'));
const EmployeeReportsPage = React.lazy(() => import('./pages/employees/EmployeeReportsPage'));
const OrganizationChartPage = React.lazy(() => import('./pages/employees/OrganizationChartPage'));
const DocumentReportsPage = React.lazy(() => import('./pages/documents/DocumentReportsPage'));
const ApplicationsPage = React.lazy(() => import('./pages/recruitment/ApplicationsPage'));
const PerformancePage = React.lazy(() => import('./pages/performance/PerformancePage'));
const AnalyticsPage = React.lazy(() => import('./pages/analytics/AnalyticsPage'));
const KvkkPage = React.lazy(() => import('./pages/kvkk/KvkkPage'));
const FormLayoutEditorPage = React.lazy(() => import('./pages/settings/FormLayoutEditorPage'));
const WorkflowEditorPage = React.lazy(() => import('./pages/settings/WorkflowEditorPage'));

const PageFallback = () => <div className="page-loading">â€¦</div>;

const withSuspense = (node: React.ReactNode): React.ReactElement => (
  <React.Suspense fallback={<PageFallback />}>{node}</React.Suspense>
);

const App: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const { mode, density } = useSelector((state: RootState) => state.theme);
  const { isAuthenticated: authIsAuthenticated } = useSelector((state: RootState) => state.auth);

  // Ä°lk yÃ¼klemede tam checkAuth (izin dump dahil)
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

  // Periyodik sessiz yenileme â€” 5 dk (Ã¶nceki 30 sn agresifti + remount dÃ¶ngÃ¼sÃ¼)
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

        {/* Public kariyer baÅŸvurusu (auth yok) */}
        <Route path="/careers/:companySlug/:positionSlug" element={<PublicCareerApplyPage />} />

        {/* PDKS kiosk â€” tam ekran (MainLayout dÄ±ÅŸÄ±nda) */}
        <Route
          path="/pdks/kiosk"
          element={
            <ProtectedRoute>
              <ModuleProtectedRoute moduleKey={MODULE_KEYS.TIMESHEET}>
                <PermissionProtectedRoute module="timesheet" page="kiosk" action="view">
                  <PdksKioskPage />
                </PermissionProtectedRoute>
              </ModuleProtectedRoute>
            </ProtectedRoute>
          }
        />
        <Route path="/attendance/kiosk" element={<Navigate to="/pdks/kiosk" replace />} />

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
          {/* Organization (E0 canonical) */}
          <Route
            path="/organization/branches"
            element={<ProtectedRoute><BranchesPage /></ProtectedRoute>}
          />
          <Route
            path="/organization/branches/:id"
            element={<ProtectedRoute><BranchDetailPage /></ProtectedRoute>}
          />
          <Route
            path="/organization/departments"
            element={withPermission(<DepartmentsPage />, 'employees', 'departments', 'view')}
          />
          <Route
            path="/organization/positions"
            element={withPermission(<PositionsPage />, 'employees', 'positions', 'view')}
          />
          <Route
            path="/organization/chart"
            element={withPermission(<OrganizationChartPage />, 'employees', 'organization', 'view')}
          />
          {/* Payroll (E0 canonical) — salary */}
          <Route
            path="/payroll/salary-bands"
            element={withPermission(<SalaryBandsPage />, 'employees', 'salary', 'view')}
          />
          <Route
            path="/payroll/salary-reviews"
            element={withPermission(<SalaryReviewPeriodsPage />, 'employees', 'salary', 'view')}
          />
          <Route
            path="/payroll/salary-reviews/:id"
            element={withPermission(<SalaryReviewDetailPage />, 'employees', 'salary', 'view')}
          />
          {/* Legacy redirects — organization / payroll salary */}
          <Route path="/employees/departments" element={<Navigate to="/organization/departments" replace />} />
          <Route path="/employees/positions" element={<Navigate to="/organization/positions" replace />} />
          <Route path="/employees/salary-bands" element={<Navigate to="/payroll/salary-bands" replace />} />
          <Route path="/employees/salary-reviews" element={<Navigate to="/payroll/salary-reviews" replace />} />
          <Route
            path="/employees/salary-reviews/:id"
            element={<LegacyParamRedirect to="/payroll/salary-reviews/:id" />}
          />
          <Route path="/employees/organization" element={<Navigate to="/organization/chart" replace />} />
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
          <Route
            path="/audit-logs/report-access"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="audit_logs" action="view">
                  <ReportAccessLogsPage />
                </PermissionProtectedRoute>
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
                  {withSuspense(<ApplicationsPage />)}
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
                  {withSuspense(<DocumentReportsPage />)}
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

          {/* Expenses / Payroll expenses (E0) */}
          <Route
            path="/payroll/expenses"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.EXPENSE_MANAGEMENT}>
                  <ExpensesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/payroll/expenses/all"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.EXPENSE_MANAGEMENT}>
                  <ExpensesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/payroll/expenses/categories"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.EXPENSE_MANAGEMENT}>
                  <ExpensesPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route path="/expenses" element={<Navigate to="/payroll/expenses" replace />} />
          <Route path="/expenses/all" element={<Navigate to="/payroll/expenses/all" replace />} />
          <Route path="/expenses/categories" element={<Navigate to="/payroll/expenses/categories" replace />} />

          {/* PDKS (E0 canonical) */}
          <Route
            path="/pdks"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.TIMESHEET}>
                  <AttendancePage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/pdks/shifts"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.TIMESHEET}>
                  <PermissionProtectedRoute module="timesheet" page="shifts" action="view">
                    <ShiftsPage />
                  </PermissionProtectedRoute>
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/pdks/shift-assignments"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.TIMESHEET}>
                  <PermissionProtectedRoute module="timesheet" page="shifts" action="view">
                    <ShiftAssignmentsPage />
                  </PermissionProtectedRoute>
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/pdks/reports"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.TIMESHEET}>
                  <PermissionProtectedRoute module="timesheet" page="attendance" action="view">
                    <AttendanceReportsPage />
                  </PermissionProtectedRoute>
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          {/* Legacy redirects — attendance → pdks */}
          <Route path="/attendance" element={<Navigate to="/pdks" replace />} />
          <Route path="/attendance/shifts" element={<Navigate to="/pdks/shifts" replace />} />
          <Route path="/attendance/shift-assignments" element={<Navigate to="/pdks/shift-assignments" replace />} />
          <Route path="/attendance/reports" element={<Navigate to="/pdks/reports" replace />} />
          
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
            element={withPermission(<OnboardingPage />, 'onboarding', 'templates')}
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
                  {withSuspense(<PerformancePage />)}
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/performance/periods"
            element={withPermission(<PerformancePage />, 'performance', 'periods')}
          />
          <Route
            path="/performance/criteria"
            element={withPermission(<PerformancePage />, 'performance', 'criteria')}
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
            element={withPermission(<TrainingPage />, 'training', 'sessions')}
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
          
          {/* Assets Module â€” static paths before :id */}
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
            path="/assets/form-engine/new"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.ASSET_MANAGEMENT}>
                  <AssetFormEnginePage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/assets/categories"
            element={withPermission(<AssetsPage />, 'assets', 'categories')}
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
          
          {/* Communication (E0 canonical) */}
          <Route
            path="/communication/surveys"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.SURVEYS}>
                  <SurveysPage />
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/communication/announcements"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="announcements" page="list" action="view">
                  <AnnouncementsPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          {/* Payroll payslips (E0 canonical) */}
          <Route
            path="/payroll/payslips"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="payroll" page="payslips" action="view">
                  <PayslipsAdminPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          {/* Legacy redirects — surveys / announcements / payslips */}
          <Route path="/surveys" element={<Navigate to="/communication/surveys" replace />} />
          <Route path="/announcements" element={<Navigate to="/communication/announcements" replace />} />
          <Route path="/payslips" element={<Navigate to="/payroll/payslips" replace />} />
          
          {/* Analytics Module */}
          <Route
            path="/analytics"
            element={
              <ProtectedRoute>
                <ModuleProtectedRoute moduleKey={MODULE_KEYS.HR_ANALYTICS}>
                  {withSuspense(<AnalyticsPage />)}
                </ModuleProtectedRoute>
              </ProtectedRoute>
            }
          />

          {/* Rapor Motoru (D1b) â€” lazy + permission */}
          <Route
            path="/reports/schedules"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="schedules" action="view">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <ReportSchedulesPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/reports/measures"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="measures" action="view">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <MeasuresLibraryPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/reports"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="definitions" action="view">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <ReportsListPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/reports/new"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="definitions" action="create">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <ReportBuilderPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/reports/:id/edit"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="definitions" action="edit">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <ReportBuilderPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/reports/:id"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="definitions" action="view">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <ReportBuilderPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />

          {/* Dashboard v2 (D1d) */}
          <Route
            path="/dashboards"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="dashboards" action="view">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <DashboardsListPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/dashboards/:id"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="reports" page="dashboards" action="view">
                  <React.Suspense fallback={<div className="page-loading">â€¦</div>}>
                    <DashboardViewPage />
                  </React.Suspense>
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          
          {/* Settings - No module restriction */}
          <Route
            path="/settings"
            element={<ProtectedRoute><SettingsPage /></ProtectedRoute>}
          />
          <Route
            path="/settings/registry"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="settings" page="values" action="view">
                  <SettingsRegistryPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings/report-privacy"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="settings" action="edit">
                  <ReportPrivacySettingsPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
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
            path="/kvkk"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="kvkk" action="view">
                  {withSuspense(<KvkkPage />)}
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
            path="/settings/forms"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="forms" action="view">
                  <FormsIndexPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings/forms/:entityType"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="forms" action="view">
                  {withSuspense(<FormLayoutEditorPage />)}
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings/workflows"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="workflows" action="view">
                  <WorkflowsListPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings/workflows/:id"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="workflows" action="view">
                  {withSuspense(<WorkflowEditorPage />)}
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings/notification-templates"
            element={
              <ProtectedRoute>
                <PermissionProtectedRoute module="management" page="notifications" action="view">
                  <NotificationTemplatesPage />
                </PermissionProtectedRoute>
              </ProtectedRoute>
            }
          />
          <Route path="/branches" element={<Navigate to="/organization/branches" replace />} />
          <Route
            path="/branches/:id"
            element={<LegacyParamRedirect to="/organization/branches/:id" />}
          />

          {/* KiÅŸisel hesap â€” permission gerekmez */}
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

          {/* E0 — bilinmeyen rota */}
          <Route
            path="*"
            element={
              <ProtectedRoute>
                <NotFoundPage />
              </ProtectedRoute>
            }
          />
        </Route>

        {/* Redirects */}
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
      </Routes>
      </div>
    </ErrorBoundary>
  );
};

export default App;

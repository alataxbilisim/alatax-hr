<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Version 1
Route::prefix('v1')->group(function () {

    // Public routes (authentication required olmayan) — brute-force: 10/dk per IP
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'resetPassword']);
    });

    // Protected routes (authentication required)
    Route::middleware(['auth:sanctum', 'company.active'])->group(function () {

        // Auth endpoints
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
            Route::get('/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
            Route::put('/profile', [\App\Http\Controllers\Api\V1\AuthController::class, 'updateProfile']);
            Route::put('/password', [\App\Http\Controllers\Api\V1\AuthController::class, 'updatePassword']);
        });

        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\DashboardController::class, 'index']);

        // Users — company_admin (soft) + management.users.*
        Route::middleware('company_admin')->group(function () {
            Route::middleware('throttle:exports')->group(function () {
                Route::get('/users/export', [\App\Http\Controllers\Api\V1\UserController::class, 'export'])
                    ->middleware('permission:management.users.export');
                Route::post('/users/import', [\App\Http\Controllers\Api\V1\UserController::class, 'import'])
                    ->middleware('permission:management.users.import');
            });
            Route::post('/users/invite', [\App\Http\Controllers\Api\V1\UserController::class, 'invite'])
                ->middleware('permission:management.users.create');
            Route::post('/users/bulk-update', [\App\Http\Controllers\Api\V1\UserController::class, 'bulkUpdate'])
                ->middleware('permission:management.users.edit');

            Route::get('users', [\App\Http\Controllers\Api\V1\UserController::class, 'index'])
                ->middleware('permission:management.users.view');
            Route::post('users', [\App\Http\Controllers\Api\V1\UserController::class, 'store'])
                ->middleware('permission:management.users.create');
            Route::get('users/{user}', [\App\Http\Controllers\Api\V1\UserController::class, 'show'])
                ->middleware('permission:management.users.view');
            Route::put('users/{user}', [\App\Http\Controllers\Api\V1\UserController::class, 'update'])
                ->middleware('permission:management.users.edit');
            Route::patch('users/{user}', [\App\Http\Controllers\Api\V1\UserController::class, 'update'])
                ->middleware('permission:management.users.edit');
            Route::delete('users/{user}', [\App\Http\Controllers\Api\V1\UserController::class, 'destroy'])
                ->middleware('permission:management.users.delete');

            Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Api\V1\UserController::class, 'resetPassword'])
                ->middleware('permission:management.users.edit');
            Route::post('/users/{user}/toggle-status', [\App\Http\Controllers\Api\V1\UserController::class, 'toggleStatus'])
                ->middleware('permission:management.users.edit');
            Route::post('/users/{user}/avatar', [\App\Http\Controllers\Api\V1\UserController::class, 'uploadAvatar'])
                ->middleware('permission:management.users.edit');
            Route::delete('/users/{user}/avatar', [\App\Http\Controllers\Api\V1\UserController::class, 'deleteAvatar'])
                ->middleware('permission:management.users.edit');
            Route::post('/users/{user}/2fa/enable', [\App\Http\Controllers\Api\V1\UserController::class, 'enable2FA'])
                ->middleware('permission:management.users.edit');
            Route::post('/users/{user}/2fa/verify', [\App\Http\Controllers\Api\V1\UserController::class, 'verify2FA'])
                ->middleware('permission:management.users.edit');
            Route::post('/users/{user}/2fa/disable', [\App\Http\Controllers\Api\V1\UserController::class, 'disable2FA'])
                ->middleware('permission:management.users.edit');
            Route::get('/users/{user}/2fa/recovery-codes', [\App\Http\Controllers\Api\V1\UserController::class, 'getRecoveryCodes'])
                ->middleware('permission:management.users.view');
            Route::post('/users/{user}/2fa/recovery-codes/regenerate', [\App\Http\Controllers\Api\V1\UserController::class, 'regenerateRecoveryCodes'])
                ->middleware('permission:management.users.edit');
            Route::get('/users/{user}/sessions', [\App\Http\Controllers\Api\V1\UserController::class, 'sessions'])
                ->middleware('permission:management.users.view');
            Route::delete('/users/{user}/sessions/{tokenId}', [\App\Http\Controllers\Api\V1\UserController::class, 'revokeSession'])
                ->middleware('permission:management.users.edit');
            Route::delete('/users/{user}/sessions', [\App\Http\Controllers\Api\V1\UserController::class, 'revokeAllSessions'])
                ->middleware('permission:management.users.edit');
        });

        // Roles & Permissions
        Route::middleware('company_admin')->group(function () {
            Route::get('roles', [\App\Http\Controllers\Api\V1\RoleController::class, 'index'])
                ->middleware('permission:management.roles.view');
            Route::post('roles', [\App\Http\Controllers\Api\V1\RoleController::class, 'store'])
                ->middleware('permission:management.roles.create');
            Route::get('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'show'])
                ->middleware('permission:management.roles.view');
            Route::put('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'update'])
                ->middleware('permission:management.roles.edit');
            Route::patch('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'update'])
                ->middleware('permission:management.roles.edit');
            Route::delete('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'destroy'])
                ->middleware('permission:management.roles.delete');
            Route::get('/permissions', [\App\Http\Controllers\Api\V1\RoleController::class, 'permissions'])
                ->middleware('permission:management.roles.view');
        });

        // Webhooks
        Route::middleware('company_admin')->prefix('webhooks')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\WebhookController::class, 'index'])
                ->middleware('permission:management.webhooks.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\WebhookController::class, 'store'])
                ->middleware('permission:management.webhooks.create');
            Route::get('/{webhook}', [\App\Http\Controllers\Api\V1\WebhookController::class, 'show'])
                ->middleware('permission:management.webhooks.view');
            Route::put('/{webhook}', [\App\Http\Controllers\Api\V1\WebhookController::class, 'update'])
                ->middleware('permission:management.webhooks.edit');
            Route::patch('/{webhook}', [\App\Http\Controllers\Api\V1\WebhookController::class, 'update'])
                ->middleware('permission:management.webhooks.edit');
            Route::delete('/{webhook}', [\App\Http\Controllers\Api\V1\WebhookController::class, 'destroy'])
                ->middleware('permission:management.webhooks.delete');
            Route::get('/{webhook}/logs', [\App\Http\Controllers\Api\V1\WebhookController::class, 'logs'])
                ->middleware('permission:management.webhooks.view');
            Route::post('/{webhook}/test', [\App\Http\Controllers\Api\V1\WebhookController::class, 'test'])
                ->middleware('permission:management.webhooks.edit');
            Route::post('/{webhook}/regenerate-secret', [\App\Http\Controllers\Api\V1\WebhookController::class, 'regenerateSecret'])
                ->middleware('permission:management.webhooks.edit');
        });

        // Company Settings
        Route::middleware('company_admin')->prefix('company')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\CompanyController::class, 'show'])
                ->middleware('permission:management.company.view');
            Route::put('/', [\App\Http\Controllers\Api\V1\CompanyController::class, 'update'])
                ->middleware('permission:management.company.edit');
            Route::get('/modules', [\App\Http\Controllers\Api\V1\CompanyController::class, 'modules'])
                ->middleware('permission:management.company.view');
            Route::post('/logo', [\App\Http\Controllers\Api\V1\CompanyController::class, 'uploadLogo'])
                ->middleware('permission:management.company.edit');
            Route::delete('/logo', [\App\Http\Controllers\Api\V1\CompanyController::class, 'deleteLogo'])
                ->middleware('permission:management.company.edit');

            Route::get('/settings', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'index'])
                ->middleware('permission:management.settings.view');
            Route::put('/settings', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'update'])
                ->middleware('permission:management.settings.edit');
            Route::post('/settings/smtp/test', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'testSmtp'])
                ->middleware('permission:management.settings.edit');
            Route::post('/settings/sms/test', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'testSms'])
                ->middleware('permission:management.settings.edit');
        });

        // API Keys
        Route::middleware('company_admin')->prefix('api-keys')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'index'])
                ->middleware('permission:management.api_keys.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'store'])
                ->middleware('permission:management.api_keys.create');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'show'])
                ->middleware('permission:management.api_keys.view');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'update'])
                ->middleware('permission:management.api_keys.edit');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'destroy'])
                ->middleware('permission:management.api_keys.delete');
            Route::post('/{id}/regenerate', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'regenerate'])
                ->middleware('permission:management.api_keys.edit');
        });

        // Branches
        Route::middleware('company_admin')->prefix('branches')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\BranchController::class, 'index'])
                ->middleware('permission:management.branches.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\BranchController::class, 'store'])
                ->middleware('permission:management.branches.create');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\BranchController::class, 'show'])
                ->middleware('permission:management.branches.view');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\BranchController::class, 'update'])
                ->middleware('permission:management.branches.edit');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\BranchController::class, 'destroy'])
                ->middleware('permission:management.branches.delete');
            Route::post('/{id}/set-headquarters', [\App\Http\Controllers\Api\V1\BranchController::class, 'setHeadquarters'])
                ->middleware('permission:management.branches.edit');
            Route::get('/{id}/employees', [\App\Http\Controllers\Api\V1\BranchController::class, 'employees'])
                ->middleware('permission:management.branches.view');
        });

        // Employees
        Route::middleware('company_admin')->prefix('employees')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'index'])
                ->middleware('permission:employees.list.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'store'])
                ->middleware('permission:employees.list.create');
            Route::middleware('throttle:exports')->group(function () {
                Route::get('/export', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'export'])
                    ->middleware('permission:employees.list.export');
                Route::post('/import', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'import'])
                    ->middleware('permission:employees.list.import');
                Route::get('/import/template', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'importTemplate'])
                    ->middleware('permission:employees.list.import');
            });
            Route::post('/bulk-update', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'bulkUpdate'])
                ->middleware('permission:employees.list.edit');
            Route::post('/bulk-delete', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'bulkDelete'])
                ->middleware('permission:employees.list.delete');
            Route::get('/departments', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'departments'])
                ->middleware('permission:employees.departments.view');
            Route::get('/managers', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'managers'])
                ->middleware('permission:employees.list.view');
            Route::get('/custom-fields', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getCustomFields'])
                ->middleware('permission:employees.custom_fields.view');
            Route::get('/organization-chart', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getOrganizationChart'])
                ->middleware('permission:employees.organization.view');
            Route::get('/stats', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getStats'])
                ->middleware('permission:employees.list.view');

            Route::prefix('reports')->group(function () {
                Route::get('/metadata', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'metadata'])
                    ->middleware('permission:employees.reports.view');
                Route::post('/data', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'getData'])
                    ->middleware(['permission:employees.reports.view', 'throttle:exports']);
                Route::get('/saved', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'savedReports'])
                    ->middleware('permission:employees.reports.view');
                Route::post('/saved', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'saveReport'])
                    ->middleware('permission:employees.reports.view');
                Route::put('/saved/{id}', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'updateReport'])
                    ->middleware('permission:employees.reports.view');
                Route::delete('/saved/{id}', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'deleteReport'])
                    ->middleware('permission:employees.reports.view');
                Route::post('/saved/{id}/favorite', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'toggleFavorite'])
                    ->middleware('permission:employees.reports.view');
                Route::post('/export/excel', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'exportExcel'])
                    ->middleware(['permission:employees.reports.export', 'throttle:exports']);
            });

            Route::prefix('dashboards')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'index'])
                    ->middleware('permission:employees.reports.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'store'])
                    ->middleware('permission:employees.reports.view');
                Route::post('/widget-data', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'getWidgetData'])
                    ->middleware('permission:employees.reports.view');
                Route::get('/{dashboardId}', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'show'])
                    ->middleware('permission:employees.reports.view');
                Route::put('/{dashboardId}', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'update'])
                    ->middleware('permission:employees.reports.view');
                Route::delete('/{dashboardId}', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'destroy'])
                    ->middleware('permission:employees.reports.view');
                Route::post('/{dashboardId}/favorite', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'toggleFavorite'])
                    ->middleware('permission:employees.reports.view');
                Route::get('/{dashboardId}/export/excel', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'exportExcel'])
                    ->middleware(['permission:employees.reports.export', 'throttle:exports']);
            });

            Route::get('/{id}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'show'])
                ->middleware('permission:employees.list.view');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'update'])
                ->middleware('permission:employees.list.edit');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'destroy'])
                ->middleware('permission:employees.list.delete');
            Route::post('/{id}/portal-access', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'createPortalAccess'])
                ->middleware('permission:employees.list.edit');
            Route::delete('/{id}/portal-access', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'revokePortalAccess'])
                ->middleware('permission:employees.list.edit');

            Route::get('/{id}/leaves', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getLeaves'])
                ->middleware('permission:employees.list.view');
            Route::get('/{id}/trainings', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainings'])
                ->middleware('permission:employees.list.view');
            Route::get('/{id}/assets', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getAssets'])
                ->middleware('permission:employees.list.view');
            Route::get('/{id}/performance', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getPerformance'])
                ->middleware('permission:employees.list.view');
            Route::get('/{id}/activity', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getActivity'])
                ->middleware('permission:employees.list.view');

            Route::get('/{id}/documents', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'index'])
                ->middleware('permission:employees.documents.view');
            Route::post('/{id}/documents', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'store'])
                ->middleware('permission:employees.documents.create');
            Route::get('/{id}/documents/{docId}', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'show'])
                ->middleware('permission:employees.documents.view');
            Route::put('/{id}/documents/{docId}', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'update'])
                ->middleware('permission:employees.documents.edit');
            Route::delete('/{id}/documents/{docId}', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'destroy'])
                ->middleware('permission:employees.documents.delete');
            Route::get('/{id}/documents/{docId}/download', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'download'])
                ->middleware('permission:employees.documents.view');
        });

        Route::middleware('company_admin')->prefix('employee-documents')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'categories'])
                ->middleware('permission:employees.documents.view');
            Route::get('/expiring-soon', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'expiringSoon'])
                ->middleware('permission:employees.documents.view');
        });

        // Departments
        Route::middleware('company_admin')->prefix('departments')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'index'])
                ->middleware('permission:employees.departments.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'store'])
                ->middleware('permission:employees.departments.create');
            Route::get('/managers', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'getManagers'])
                ->middleware('permission:employees.departments.view');
            Route::get('/hierarchy', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'getHierarchy'])
                ->middleware('permission:employees.departments.view');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'show'])
                ->middleware('permission:employees.departments.view');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'update'])
                ->middleware('permission:employees.departments.edit');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'destroy'])
                ->middleware('permission:employees.departments.delete');
        });

        // Custom Fields (global admin UI)
        Route::middleware('company_admin')->prefix('custom-fields')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'index'])
                ->middleware('permission:management.custom_fields.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'store'])
                ->middleware('permission:management.custom_fields.create');
            Route::get('/field-types', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'getFieldTypes'])
                ->middleware('permission:management.custom_fields.view');
            Route::get('/entity-types', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'getEntityTypes'])
                ->middleware('permission:management.custom_fields.view');
            Route::post('/reorder', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'reorder'])
                ->middleware('permission:management.custom_fields.edit');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'show'])
                ->middleware('permission:management.custom_fields.view');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'update'])
                ->middleware('permission:management.custom_fields.edit');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'destroy'])
                ->middleware('permission:management.custom_fields.delete');
        });

        // Activity Logs — management.audit_logs (PermissionSeeder: underscore)
        // /export MUST be before /{id}
        Route::get('/activity-logs', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'index'])
            ->middleware('permission:management.audit_logs.view');
        Route::get('/activity-logs/export', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'export'])
            ->middleware(['permission:management.audit_logs.export', 'throttle:exports']);
        Route::get('/activity-logs/{id}', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'show'])
            ->middleware('permission:management.audit_logs.view');

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
            Route::post('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
        });

        // ===========================================
        // MODÜL BAZLI ROUTES
        // ===========================================

        // İş Başvuru Modülü (public/jobs dokunulmaz — aşağıda)
        Route::middleware('module.access:job-applications')->prefix('recruitment')->group(function () {
            // Pozisyonlar
            Route::get('positions', [\App\Http\Controllers\Api\V1\Recruitment\JobPositionController::class, 'index'])
                ->middleware('permission:recruitment.positions.view');
            Route::post('positions', [\App\Http\Controllers\Api\V1\Recruitment\JobPositionController::class, 'store'])
                ->middleware('permission:recruitment.positions.create');
            Route::get('positions/{position}', [\App\Http\Controllers\Api\V1\Recruitment\JobPositionController::class, 'show'])
                ->middleware('permission:recruitment.positions.view');
            Route::put('positions/{position}', [\App\Http\Controllers\Api\V1\Recruitment\JobPositionController::class, 'update'])
                ->middleware('permission:recruitment.positions.edit');
            Route::patch('positions/{position}', [\App\Http\Controllers\Api\V1\Recruitment\JobPositionController::class, 'update'])
                ->middleware('permission:recruitment.positions.edit');
            Route::delete('positions/{position}', [\App\Http\Controllers\Api\V1\Recruitment\JobPositionController::class, 'destroy'])
                ->middleware('permission:recruitment.positions.delete');

            // Başvurular
            Route::get('/applications', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'index'])
                ->middleware('permission:recruitment.applications.view');
            Route::get('/applications/{id}', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'show'])
                ->middleware('permission:recruitment.applications.view');
            Route::put('/applications/{id}/status', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'updateStatus'])
                ->middleware('permission:recruitment.applications.approve');
            Route::put('/applications/{id}/notes', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'updateNotes'])
                ->middleware('permission:recruitment.applications.edit');
            Route::put('/applications/{id}/rate', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'rate'])
                ->middleware('permission:recruitment.applications.edit');

            // CV Havuzu
            Route::get('/cv-pool', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'index'])
                ->middleware('permission:recruitment.cv_pool.view');
            Route::post('/cv-pool/bulk-tag', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'bulkTag'])
                ->middleware('permission:recruitment.cv_pool.edit');
            Route::delete('/cv-pool/{id}/tag', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'removeTag'])
                ->middleware('permission:recruitment.cv_pool.edit');
            Route::put('/cv-pool/{id}/rate', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'rate'])
                ->middleware('permission:recruitment.cv_pool.edit');

            // Raporlar
            Route::prefix('reports')->middleware('permission:recruitment.reports.view')->group(function () {
                Route::get('/summary', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'summary']);
                Route::get('/by-position', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'byPosition']);
                Route::get('/by-source', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'bySource']);
                Route::get('/trends', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'trends']);
                Route::get('/time-to-hire', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'timeToHire']);
            });

            // Mülakatlar
            Route::prefix('interviews')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'index'])
                    ->middleware('permission:recruitment.interviews.view');
                Route::get('/types', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'getTypes'])
                    ->middleware('permission:recruitment.interviews.view');
                Route::get('/calendar', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'calendar'])
                    ->middleware('permission:recruitment.interviews.view');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'show'])
                    ->middleware('permission:recruitment.interviews.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'store'])
                    ->middleware('permission:recruitment.interviews.create');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'update'])
                    ->middleware('permission:recruitment.interviews.edit');
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'destroy'])
                    ->middleware('permission:recruitment.interviews.delete');
                Route::post('/{id}/complete', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'complete'])
                    ->middleware('permission:recruitment.interviews.edit');
                Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'cancel'])
                    ->middleware('permission:recruitment.interviews.edit');
            });

            // Form Builder
            Route::get('forms', [\App\Http\Controllers\Api\V1\Recruitment\FormBuilderController::class, 'index'])
                ->middleware('permission:recruitment.forms.view');
            Route::post('forms', [\App\Http\Controllers\Api\V1\Recruitment\FormBuilderController::class, 'store'])
                ->middleware('permission:recruitment.forms.create');
            Route::get('forms/{id}', [\App\Http\Controllers\Api\V1\Recruitment\FormBuilderController::class, 'show'])
                ->middleware('permission:recruitment.forms.view');
            Route::put('forms/{id}', [\App\Http\Controllers\Api\V1\Recruitment\FormBuilderController::class, 'update'])
                ->middleware('permission:recruitment.forms.edit');
            Route::patch('forms/{id}', [\App\Http\Controllers\Api\V1\Recruitment\FormBuilderController::class, 'update'])
                ->middleware('permission:recruitment.forms.edit');
            Route::delete('forms/{id}', [\App\Http\Controllers\Api\V1\Recruitment\FormBuilderController::class, 'destroy'])
                ->middleware('permission:recruitment.forms.delete');
        });

        // Evrak Yönetimi Modülü
        Route::middleware('module.access:document-management')->prefix('documents')->group(function () {
            // Kategoriler
            Route::get('/categories', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'index'])
                ->middleware('permission:documents.categories.view');
            Route::post('/categories', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'store'])
                ->middleware('permission:documents.categories.create');
            Route::put('/categories/{id}', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'update'])
                ->middleware('permission:documents.categories.edit');
            Route::delete('/categories/{id}', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'destroy'])
                ->middleware('permission:documents.categories.delete');

            // Raporlar (MUST be before /{id} routes)
            Route::prefix('reports')->middleware('permission:documents.reports.view')->group(function () {
                Route::get('/metadata', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'metadata']);
                Route::post('/widget-data', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'getWidgetData']);
                Route::post('/kpi-data', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'getKpiData']);
                Route::get('/summary', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'summary']);
            });

            // İstatistikler
            Route::get('/stats', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'stats'])
                ->middleware('permission:documents.list.view');
        });

        Route::middleware('module.access:document-management')->group(function () {
            Route::get('/documents', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'index'])
                ->middleware('permission:documents.list.view');
            Route::post('/documents', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'store'])
                ->middleware('permission:documents.list.create');
            Route::get('/documents/{id}', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'show'])
                ->middleware('permission:documents.list.view');
            Route::put('/documents/{id}', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'update'])
                ->middleware('permission:documents.list.edit');
            Route::delete('/documents/{id}', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'destroy'])
                ->middleware('permission:documents.list.delete');
            Route::get('/documents/{id}/download', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'download'])
                ->middleware('permission:documents.list.view');
            Route::get('/documents/{id}/versions', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'versions'])
                ->middleware('permission:documents.list.view');
            Route::get('/documents/{id}/versions/{versionId}/download', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'downloadVersion'])
                ->middleware('permission:documents.list.view');
        });
        // Onboarding Modülü
        Route::middleware('module.access:onboarding')->prefix('onboarding')->group(function () {
            Route::get('templates', [\App\Http\Controllers\Api\V1\Onboarding\TemplateController::class, 'index'])
                ->middleware('permission:onboarding.templates.view');
            Route::post('templates', [\App\Http\Controllers\Api\V1\Onboarding\TemplateController::class, 'store'])
                ->middleware('permission:onboarding.templates.create');
            Route::get('templates/{template}', [\App\Http\Controllers\Api\V1\Onboarding\TemplateController::class, 'show'])
                ->middleware('permission:onboarding.templates.view');
            Route::put('templates/{template}', [\App\Http\Controllers\Api\V1\Onboarding\TemplateController::class, 'update'])
                ->middleware('permission:onboarding.templates.edit');
            Route::patch('templates/{template}', [\App\Http\Controllers\Api\V1\Onboarding\TemplateController::class, 'update'])
                ->middleware('permission:onboarding.templates.edit');
            Route::delete('templates/{template}', [\App\Http\Controllers\Api\V1\Onboarding\TemplateController::class, 'destroy'])
                ->middleware('permission:onboarding.templates.delete');

            Route::get('processes', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'index'])
                ->middleware('permission:onboarding.processes.view');
            Route::post('processes', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'store'])
                ->middleware('permission:onboarding.processes.create');
            Route::get('processes/{process}', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'show'])
                ->middleware('permission:onboarding.processes.view');
            Route::put('processes/{process}', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'update'])
                ->middleware('permission:onboarding.processes.edit');
            Route::patch('processes/{process}', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'update'])
                ->middleware('permission:onboarding.processes.edit');
            Route::delete('processes/{process}', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'destroy'])
                ->middleware('permission:onboarding.processes.delete');
            Route::post('/processes/{process}/tasks/{task}/complete', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'completeTask'])
                ->middleware('permission:onboarding.processes.edit');
        });

        // İzin Yönetimi Modülü
        Route::middleware('module.access:leave-management')->prefix('leaves')->group(function () {
            // İzin tipleri
            Route::get('types', [\App\Http\Controllers\Api\V1\Leaves\LeaveTypeController::class, 'index'])
                ->middleware('permission:leaves.types.view');
            Route::post('types', [\App\Http\Controllers\Api\V1\Leaves\LeaveTypeController::class, 'store'])
                ->middleware('permission:leaves.types.create');
            Route::get('types/{leave_type}', [\App\Http\Controllers\Api\V1\Leaves\LeaveTypeController::class, 'show'])
                ->middleware('permission:leaves.types.view');
            Route::put('types/{leave_type}', [\App\Http\Controllers\Api\V1\Leaves\LeaveTypeController::class, 'update'])
                ->middleware('permission:leaves.types.edit');
            Route::patch('types/{leave_type}', [\App\Http\Controllers\Api\V1\Leaves\LeaveTypeController::class, 'update'])
                ->middleware('permission:leaves.types.edit');
            Route::delete('types/{leave_type}', [\App\Http\Controllers\Api\V1\Leaves\LeaveTypeController::class, 'destroy'])
                ->middleware('permission:leaves.types.delete');

            // İzin talepleri
            Route::get('requests', [\App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class, 'index'])
                ->middleware('permission:leaves.requests.view');
            Route::post('requests', [\App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class, 'store'])
                ->middleware('permission:leaves.requests.create');
            Route::get('requests/{leave_request}', [\App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class, 'show'])
                ->middleware('permission:leaves.requests.view');
            // update/destroy controller'da yok — apiResource kalıntısı eklenmedi
            Route::post('/requests/{leave_request}/approve', [\App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class, 'approve'])
                ->middleware('permission:leaves.requests.approve');
            Route::post('/requests/{leave_request}/reject', [\App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class, 'reject'])
                ->middleware('permission:leaves.requests.approve');

            Route::get('/calendar', [\App\Http\Controllers\Api\V1\Leaves\LeaveCalendarController::class, 'index'])
                ->middleware('permission:leaves.calendar.view');
            Route::get('/balance', [\App\Http\Controllers\Api\V1\Leaves\LeaveBalanceController::class, 'index'])
                ->middleware('permission:leaves.balances.view');

            // Tatil Takvimi
            Route::prefix('holidays')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'index'])
                    ->middleware('permission:leaves.holidays.view');
                Route::get('/types', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'getTypes'])
                    ->middleware('permission:leaves.holidays.view');
                Route::get('/range', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'getHolidaysInRange'])
                    ->middleware('permission:leaves.holidays.view');
                Route::post('/check-date', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'checkDate'])
                    ->middleware('permission:leaves.holidays.view');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'show'])
                    ->middleware('permission:leaves.holidays.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'store'])
                    ->middleware('permission:leaves.holidays.create');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'update'])
                    ->middleware('permission:leaves.holidays.edit');
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'destroy'])
                    ->middleware('permission:leaves.holidays.delete');
            });

            // Hakediş Politikaları
            Route::prefix('accrual-policies')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'index'])
                    ->middleware('permission:leaves.accrual_policies.view');
                Route::get('/types', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'getAccrualTypes'])
                    ->middleware('permission:leaves.accrual_policies.view');
                Route::get('/log-types', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'getLogTypes'])
                    ->middleware('permission:leaves.accrual_policies.view');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'show'])
                    ->middleware('permission:leaves.accrual_policies.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'store'])
                    ->middleware('permission:leaves.accrual_policies.create');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'update'])
                    ->middleware('permission:leaves.accrual_policies.edit');
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'destroy'])
                    ->middleware('permission:leaves.accrual_policies.delete');
                Route::get('/user/{userId}/logs', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'getUserAccrualLogs'])
                    ->middleware('permission:leaves.accrual_policies.view');
                Route::post('/process-monthly', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'processMonthlyAccruals'])
                    ->middleware('permission:leaves.accrual_policies.edit');
                Route::post('/process-carryover', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'processYearEndCarryover'])
                    ->middleware('permission:leaves.accrual_policies.edit');
            });
        });
        // Onay İş Akışları (Workflow Engine)
        Route::middleware('company_admin')->prefix('workflows')->group(function () {
            Route::get('/entity-types', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'getEntityTypes'])
                ->middleware('permission:management.workflows.view');
            Route::get('/approver-types', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'getApproverTypes'])
                ->middleware('permission:management.workflows.view');
            Route::get('/by-entity/{entityType}', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'getByEntityType'])
                ->middleware('permission:management.workflows.view');
            Route::get('/', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'index'])
                ->middleware('permission:management.workflows.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'store'])
                ->middleware('permission:management.workflows.create');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'show'])
                ->middleware('permission:management.workflows.view');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'update'])
                ->middleware('permission:management.workflows.edit');
            Route::patch('/{id}', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'update'])
                ->middleware('permission:management.workflows.edit');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'destroy'])
                ->middleware('permission:management.workflows.delete');
        });

        // Onay İşlemleri
        Route::prefix('approvals')->group(function () {
            Route::get('/pending', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'pendingApprovals']);
            Route::get('/history', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'myApprovalHistory']);
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'approve']);
            Route::post('/{id}/reject', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'reject']);
            Route::post('/{id}/skip', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'skip']);
            Route::get('/record-history', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'getApprovalHistory']);

            // Vekaletler
            Route::prefix('delegations')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'delegations']);
                Route::get('/my', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'myDelegations']);
                Route::post('/', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'createDelegation']);
                Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Workflow\ApprovalController::class, 'cancelDelegation']);
            });
        });

        // Performans Değerlendirme Modülü
        Route::middleware('module.access:performance')->prefix('performance')->group(function () {
            // Periods
            Route::get('periods', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'index'])
                ->middleware('permission:performance.periods.view');
            Route::post('periods', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'store'])
                ->middleware('permission:performance.periods.create');
            Route::get('periods/{id}', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'show'])
                ->middleware('permission:performance.periods.view');
            Route::put('periods/{id}', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'update'])
                ->middleware('permission:performance.periods.edit');
            Route::patch('periods/{id}', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'update'])
                ->middleware('permission:performance.periods.edit');
            Route::delete('periods/{id}', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'destroy'])
                ->middleware('permission:performance.periods.delete');
            Route::post('/periods/{id}/activate', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'activate'])
                ->middleware('permission:performance.periods.edit');
            Route::post('/periods/{id}/close', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'close'])
                ->middleware('permission:performance.periods.edit');

            // Criteria
            Route::get('criteria', [\App\Http\Controllers\Api\V1\Performance\CriteriaController::class, 'index'])
                ->middleware('permission:performance.criteria.view');
            Route::post('criteria', [\App\Http\Controllers\Api\V1\Performance\CriteriaController::class, 'store'])
                ->middleware('permission:performance.criteria.create');
            Route::get('criteria/{id}', [\App\Http\Controllers\Api\V1\Performance\CriteriaController::class, 'show'])
                ->middleware('permission:performance.criteria.view');
            Route::put('criteria/{id}', [\App\Http\Controllers\Api\V1\Performance\CriteriaController::class, 'update'])
                ->middleware('permission:performance.criteria.edit');
            Route::patch('criteria/{id}', [\App\Http\Controllers\Api\V1\Performance\CriteriaController::class, 'update'])
                ->middleware('permission:performance.criteria.edit');
            Route::delete('criteria/{id}', [\App\Http\Controllers\Api\V1\Performance\CriteriaController::class, 'destroy'])
                ->middleware('permission:performance.criteria.delete');

            // Reviews
            Route::get('reviews', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'index'])
                ->middleware('permission:performance.reviews.view');
            Route::post('reviews', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'store'])
                ->middleware('permission:performance.reviews.create');
            Route::get('reviews/{id}', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'show'])
                ->middleware('permission:performance.reviews.view');
            Route::put('reviews/{id}', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'update'])
                ->middleware('permission:performance.reviews.edit');
            Route::patch('reviews/{id}', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'update'])
                ->middleware('permission:performance.reviews.edit');
            Route::delete('reviews/{id}', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'destroy'])
                ->middleware('permission:performance.reviews.delete');
            Route::post('/reviews/{id}/submit', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'submit'])
                ->middleware('permission:performance.reviews.edit');
            Route::post('/reviews/{id}/approve', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'approve'])
                ->middleware('permission:performance.reviews.approve');
            Route::post('/reviews/{id}/reject', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'reject'])
                ->middleware('permission:performance.reviews.approve');

            // OKR
            Route::prefix('okr')->group(function () {
                Route::get('/labels', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'getLabels'])
                    ->middleware('permission:performance.okr.view');
                Route::get('/objectives', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'index'])
                    ->middleware('permission:performance.okr.view');
                Route::get('/objectives/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'show'])
                    ->middleware('permission:performance.okr.view');
                Route::post('/objectives', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'store'])
                    ->middleware('permission:performance.okr.create');
                Route::put('/objectives/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'update'])
                    ->middleware('permission:performance.okr.edit');
                Route::delete('/objectives/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'destroy'])
                    ->middleware('permission:performance.okr.delete');
                Route::post('/objectives/{id}/activate', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'activate'])
                    ->middleware('permission:performance.okr.edit');
                Route::post('/objectives/{id}/key-results', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'addKeyResult'])
                    ->middleware('permission:performance.okr.create');
                Route::put('/key-results/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'updateKeyResult'])
                    ->middleware('permission:performance.okr.edit');
                Route::delete('/key-results/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'deleteKeyResult'])
                    ->middleware('permission:performance.okr.delete');
            });

            // 360 / continuous feedback
            Route::prefix('feedback')->group(function () {
                Route::get('/types', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'getFeedbackTypes'])
                    ->middleware('permission:performance.feedback.view');
                Route::get('/pending', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'pendingFeedbacks'])
                    ->middleware('permission:performance.feedback.view');
                Route::get('/form/{providerId}', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'getFeedbackForm'])
                    ->middleware('permission:performance.feedback.view');
                Route::post('/submit/{providerId}', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'submitFeedback'])
                    ->middleware('permission:performance.feedback.create');
                Route::post('/decline/{providerId}', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'declineFeedback'])
                    ->middleware('permission:performance.feedback.edit');
                Route::post('/reviews/{reviewId}/providers', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'addProviders'])
                    ->middleware('permission:performance.feedback.edit');
                Route::get('/reviews/{reviewId}/results', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'getReviewFeedbacks'])
                    ->middleware('permission:performance.feedback.view');
                Route::get('/continuous', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'continuousFeedbacks'])
                    ->middleware('permission:performance.feedback.view');
                Route::post('/continuous', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'sendContinuousFeedback'])
                    ->middleware('permission:performance.feedback.create');
            });

            // Yetkinlikler
            Route::prefix('competencies')->group(function () {
                Route::get('/categories', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getCategories'])
                    ->middleware('permission:performance.competencies.view');
                Route::get('/', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'index'])
                    ->middleware('permission:performance.competencies.view');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'show'])
                    ->middleware('permission:performance.competencies.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'store'])
                    ->middleware('permission:performance.competencies.create');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'update'])
                    ->middleware('permission:performance.competencies.edit');
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'destroy'])
                    ->middleware('permission:performance.competencies.delete');
                Route::get('/user/{userId}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getUserCompetencies'])
                    ->middleware('permission:performance.competencies.view');
                Route::post('/user/{userId}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'setUserCompetency'])
                    ->middleware('permission:performance.competencies.edit');
                Route::get('/position/{positionName}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getPositionCompetencies'])
                    ->middleware('permission:performance.competencies.view');
                Route::post('/position', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'setPositionCompetency'])
                    ->middleware('permission:performance.competencies.edit');
                Route::delete('/position', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'removePositionCompetency'])
                    ->middleware('permission:performance.competencies.delete');
                Route::get('/skill-gap/{userId}/{positionName}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getSkillGapAnalysis'])
                    ->middleware('permission:performance.competencies.view');
            });

            // 1-on-1
            Route::prefix('one-on-one')->group(function () {
                Route::get('/labels', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'getLabels'])
                    ->middleware('permission:performance.one_on_one.view');
                Route::get('/upcoming', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'upcoming'])
                    ->middleware('permission:performance.one_on_one.view');
                Route::get('/', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'index'])
                    ->middleware('permission:performance.one_on_one.view');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'show'])
                    ->middleware('permission:performance.one_on_one.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'store'])
                    ->middleware('permission:performance.one_on_one.create');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'update'])
                    ->middleware('permission:performance.one_on_one.edit');
                Route::post('/{id}/complete', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'complete'])
                    ->middleware('permission:performance.one_on_one.edit');
                Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'cancel'])
                    ->middleware('permission:performance.one_on_one.edit');
                Route::post('/{id}/reschedule', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'reschedule'])
                    ->middleware('permission:performance.one_on_one.edit');
            });
        });

        // Eğitim Yönetimi Modülü
        Route::middleware('module.access:training')->prefix('training')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'categories'])
                ->middleware('permission:training.list.view');

            Route::get('trainings', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'index'])
                ->middleware('permission:training.list.view');
            Route::post('trainings', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'store'])
                ->middleware('permission:training.list.create');
            Route::get('trainings/{id}', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'show'])
                ->middleware('permission:training.list.view');
            Route::put('trainings/{id}', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'update'])
                ->middleware('permission:training.list.edit');
            Route::patch('trainings/{id}', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'update'])
                ->middleware('permission:training.list.edit');
            Route::delete('trainings/{id}', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'destroy'])
                ->middleware('permission:training.list.delete');

            Route::get('sessions', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'index'])
                ->middleware('permission:training.sessions.view');
            Route::post('sessions', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'store'])
                ->middleware('permission:training.sessions.create');
            Route::get('sessions/{id}', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'show'])
                ->middleware('permission:training.sessions.view');
            Route::put('sessions/{id}', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'update'])
                ->middleware('permission:training.sessions.edit');
            Route::patch('sessions/{id}', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'update'])
                ->middleware('permission:training.sessions.edit');
            Route::delete('sessions/{id}', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'destroy'])
                ->middleware('permission:training.sessions.delete');
            Route::post('/sessions/{id}/participants', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'addParticipant'])
                ->middleware('permission:training.sessions.edit');
            Route::delete('/sessions/{id}/participants/{userId}', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'removeParticipant'])
                ->middleware('permission:training.sessions.edit');
            Route::post('/sessions/{id}/attendance', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'updateAttendance'])
                ->middleware('permission:training.sessions.edit');
        });

        // Varlık Yönetimi Modülü
        Route::middleware('module.access:asset-management')->prefix('assets')->group(function () {
            Route::get('categories', [\App\Http\Controllers\Api\V1\Assets\CategoryController::class, 'index'])
                ->middleware('permission:assets.categories.view');
            Route::post('categories', [\App\Http\Controllers\Api\V1\Assets\CategoryController::class, 'store'])
                ->middleware('permission:assets.categories.create');
            Route::get('categories/{id}', [\App\Http\Controllers\Api\V1\Assets\CategoryController::class, 'show'])
                ->middleware('permission:assets.categories.view');
            Route::put('categories/{id}', [\App\Http\Controllers\Api\V1\Assets\CategoryController::class, 'update'])
                ->middleware('permission:assets.categories.edit');
            Route::patch('categories/{id}', [\App\Http\Controllers\Api\V1\Assets\CategoryController::class, 'update'])
                ->middleware('permission:assets.categories.edit');
            Route::delete('categories/{id}', [\App\Http\Controllers\Api\V1\Assets\CategoryController::class, 'destroy'])
                ->middleware('permission:assets.categories.delete');

            Route::get('items', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'index'])
                ->middleware('permission:assets.list.view');
            Route::post('items', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'store'])
                ->middleware('permission:assets.list.create');
            Route::get('items/{id}', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'show'])
                ->middleware('permission:assets.list.view');
            Route::put('items/{id}', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'update'])
                ->middleware('permission:assets.list.edit');
            Route::patch('items/{id}', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'update'])
                ->middleware('permission:assets.list.edit');
            Route::delete('items/{id}', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'destroy'])
                ->middleware('permission:assets.list.delete');
            Route::post('/items/{id}/assign', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'assign'])
                ->middleware('permission:assets.assignments.create');
            Route::post('/items/{id}/return', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'returnAsset'])
                ->middleware('permission:assets.assignments.edit');

            Route::get('maintenance', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'index'])
                ->middleware('permission:assets.maintenance.view');
            Route::post('maintenance', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'store'])
                ->middleware('permission:assets.maintenance.create');
            Route::get('maintenance/{id}', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'show'])
                ->middleware('permission:assets.maintenance.view');
            Route::put('maintenance/{id}', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'update'])
                ->middleware('permission:assets.maintenance.edit');
            Route::patch('maintenance/{id}', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'update'])
                ->middleware('permission:assets.maintenance.edit');
            Route::delete('maintenance/{id}', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'destroy'])
                ->middleware('permission:assets.maintenance.delete');
            Route::post('/maintenance/{id}/complete', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'complete'])
                ->middleware('permission:assets.maintenance.edit');
        });

        // Anket & Geri Bildirim Modülü
        Route::middleware('module.access:surveys')->prefix('surveys')->group(function () {
            Route::get('/types', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'getTypes'])
                ->middleware('permission:surveys.list.view');
            Route::get('/', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'index'])
                ->middleware('permission:surveys.list.view');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'show'])
                ->middleware('permission:surveys.list.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'store'])
                ->middleware('permission:surveys.list.create');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'update'])
                ->middleware('permission:surveys.list.edit');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'destroy'])
                ->middleware('permission:surveys.list.delete');
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'submit'])
                ->middleware('permission:surveys.list.create');
            Route::get('/{id}/results', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'results'])
                ->middleware('permission:surveys.list.view');
        });

        // HR Analytics Modülü
        Route::middleware('module.access:hr-analytics')->prefix('analytics')->group(function () {
            Route::get('/summary', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'summary'])
                ->middleware('permission:analytics.reports.view');
            Route::get('/workforce', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'workforce'])
                ->middleware('permission:analytics.reports.view');
            Route::get('/turnover', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'turnover'])
                ->middleware('permission:analytics.reports.view');
            Route::get('/recruitment', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'recruitment'])
                ->middleware('permission:analytics.reports.view');
            Route::get('/leaves', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'leaves'])
                ->middleware('permission:analytics.reports.view');
            Route::get('/training', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'training'])
                ->middleware('permission:analytics.reports.view');
        });

        // Timesheet / Attendance (HR) — timesheet.attendance.*
        // Statik path'ler {id}'den önce
        Route::prefix('attendance')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'index'])
                ->middleware('permission:timesheet.attendance.view');
            Route::get('/daily-summary', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'dailySummary'])
                ->middleware('permission:timesheet.attendance.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'store'])
                ->middleware('permission:timesheet.attendance.create');
            Route::post('/bulk-approve', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'bulkApprove'])
                ->middleware('permission:timesheet.attendance.approve');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'show'])
                ->middleware('permission:timesheet.attendance.view');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'update'])
                ->middleware('permission:timesheet.attendance.edit');
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'approve'])
                ->middleware('permission:timesheet.attendance.approve');
        });
    });

    // ===========================================
    // SUPERADMIN ROUTES
    // ===========================================
    Route::middleware(['auth:sanctum', 'super_admin'])->prefix('admin')->group(function () {
        // Companies
        Route::apiResource('companies', \App\Http\Controllers\Api\V1\Admin\CompanyController::class);
        Route::post('/companies/{company}/toggle-status', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'toggleStatus']);
        Route::post('/companies/{company}/modules', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'syncModules']);
        Route::post('/companies/{company}/assign-package', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'assignPackage']);
        Route::post('/companies/{company}/extend-license', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'extendLicense']);

        // Company Ledger (Cari Hesap)
        Route::get('/companies/{company}/ledger', [\App\Http\Controllers\Api\V1\Admin\CompanyLedgerController::class, 'index']);
        Route::post('/companies/{company}/ledger/debit', [\App\Http\Controllers\Api\V1\Admin\CompanyLedgerController::class, 'addDebit']);
        Route::post('/companies/{company}/ledger/credit', [\App\Http\Controllers\Api\V1\Admin\CompanyLedgerController::class, 'addCredit']);
        Route::get('/companies/{company}/ledger/{transaction}', [\App\Http\Controllers\Api\V1\Admin\CompanyLedgerController::class, 'showTransaction']);

        // Ledger Summary (Tüm firmalar)
        Route::get('/ledger/summary', [\App\Http\Controllers\Api\V1\Admin\CompanyLedgerController::class, 'summary']);

        // License Packages (Lisans Paketleri)
        Route::apiResource('license-packages', \App\Http\Controllers\Api\V1\Admin\LicensePackageController::class);
        Route::post('/license-packages/{package}/modules', [\App\Http\Controllers\Api\V1\Admin\LicensePackageController::class, 'syncModules']);
        Route::post('/license-packages/{package}/duplicate', [\App\Http\Controllers\Api\V1\Admin\LicensePackageController::class, 'duplicate']);
        Route::get('/available-modules', [\App\Http\Controllers\Api\V1\Admin\LicensePackageController::class, 'availableModules']);

        // Modules
        Route::apiResource('modules', \App\Http\Controllers\Api\V1\Admin\ModuleController::class);

        // All Users
        Route::get('/users', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'index']);
        Route::get('/users/{user}', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'show']);

        // System Logs
        Route::get('/logs', [\App\Http\Controllers\Api\V1\Admin\LogController::class, 'index']);

        // Dashboard Stats
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Admin\DashboardController::class, 'index']);
    });

    // ===========================================
    // PORTAL ROUTES (Personel Self-Servis)
    // ===========================================
    Route::middleware(['auth:sanctum', 'company.active', 'portal.access'])->prefix('portal')->group(function () {
        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Portal\PortalDashboardController::class, 'index']);

        // Profil
        Route::get('/profile', [\App\Http\Controllers\Api\V1\Portal\PortalProfileController::class, 'show']);
        Route::put('/profile', [\App\Http\Controllers\Api\V1\Portal\PortalProfileController::class, 'update']);
        Route::put('/profile/password', [\App\Http\Controllers\Api\V1\Portal\PortalProfileController::class, 'updatePassword']);
        Route::post('/profile/avatar', [\App\Http\Controllers\Api\V1\Portal\PortalProfileController::class, 'updateAvatar']);

        // İzinler
        Route::prefix('leaves')->group(function () {
            Route::get('/types', [\App\Http\Controllers\Api\V1\Portal\PortalLeaveController::class, 'types']);
            Route::get('/balances', [\App\Http\Controllers\Api\V1\Portal\PortalLeaveController::class, 'balances']);
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalLeaveController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Portal\PortalLeaveController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalLeaveController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalLeaveController::class, 'update']);
            Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Portal\PortalLeaveController::class, 'cancel']);
        });

        // Belgeler
        Route::prefix('documents')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\V1\Portal\PortalDocumentController::class, 'categories']);
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalDocumentController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalDocumentController::class, 'show']);
            Route::get('/{id}/download', [\App\Http\Controllers\Api\V1\Portal\PortalDocumentController::class, 'download']);
        });

        // Bordrolar
        Route::prefix('payslips')->group(function () {
            Route::get('/years', [\App\Http\Controllers\Api\V1\Portal\PortalPayslipController::class, 'years']);
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalPayslipController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalPayslipController::class, 'show']);
            Route::get('/{id}/download', [\App\Http\Controllers\Api\V1\Portal\PortalPayslipController::class, 'download']);
        });

        // Duyurular
        Route::prefix('announcements')->group(function () {
            Route::get('/unread-count', [\App\Http\Controllers\Api\V1\Portal\PortalAnnouncementController::class, 'unreadCount']);
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalAnnouncementController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalAnnouncementController::class, 'show']);
            Route::post('/{id}/acknowledge', [\App\Http\Controllers\Api\V1\Portal\PortalAnnouncementController::class, 'acknowledge']);
        });

        // Talepler
        Route::prefix('requests')->group(function () {
            Route::get('/types', [\App\Http\Controllers\Api\V1\Portal\PortalRequestController::class, 'types']);
            Route::get('/pending-count', [\App\Http\Controllers\Api\V1\Portal\PortalRequestController::class, 'pendingCount']);
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalRequestController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Portal\PortalRequestController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalRequestController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalRequestController::class, 'update']);
            Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Portal\PortalRequestController::class, 'cancel']);
        });

        // Eğitimler (Training Module)
        Route::middleware('module.access:training')->prefix('training')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalTrainingController::class, 'index']);
            Route::get('/available', [\App\Http\Controllers\Api\V1\Portal\PortalTrainingController::class, 'available']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalTrainingController::class, 'show']);
            Route::get('/certificates/list', [\App\Http\Controllers\Api\V1\Portal\PortalTrainingController::class, 'certificates']);
            Route::get('/certificates/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalTrainingController::class, 'certificate']);
        });

        // Performans (Performance Module)
        Route::middleware('module.access:performance')->prefix('performance')->group(function () {
            Route::get('/reviews', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'reviews']);
            Route::get('/reviews/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'review']);
            Route::post('/reviews/{id}/comment', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'addComment']);
            Route::get('/okrs', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'okrs']);
            Route::get('/okrs/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'okr']);
            Route::put('/key-results/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'updateKeyResult']);
            Route::get('/feedbacks', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'feedbacks']);
            Route::post('/feedbacks', [\App\Http\Controllers\Api\V1\Portal\PortalPerformanceController::class, 'giveFeedback']);
        });

        // Anketler (Survey Module)
        Route::middleware('module.access:surveys')->prefix('surveys')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalSurveyController::class, 'index']);
            Route::get('/completed', [\App\Http\Controllers\Api\V1\Portal\PortalSurveyController::class, 'completed']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalSurveyController::class, 'show']);
            Route::post('/{id}/start', [\App\Http\Controllers\Api\V1\Portal\PortalSurveyController::class, 'start']);
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\V1\Portal\PortalSurveyController::class, 'submit']);
        });

        // Puantaj / Timesheet
        Route::prefix('timesheet')->group(function () {
            Route::get('/today', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'todayStatus']);
            Route::post('/clock-in', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'clockIn']);
            Route::post('/clock-out', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'clockOut']);
            Route::post('/break/start', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'startBreak']);
            Route::post('/break/end', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'endBreak']);
            Route::get('/weekly', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'weeklyRecords']);
            Route::get('/monthly', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'monthlyRecords']);
            Route::get('/shifts', [\App\Http\Controllers\Api\V1\Portal\PortalTimesheetController::class, 'shifts']);
        });

        // Masraf Yönetimi
        Route::prefix('expenses')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'categories']);
            Route::get('/summary', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'summary']);
            Route::get('/', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'update']);
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'submit']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'cancel']);
            Route::post('/items/{itemId}/receipt', [\App\Http\Controllers\Api\V1\Portal\PortalExpenseController::class, 'uploadReceipt']);
        });
    });

    // ===========================================
    // PUBLIC ROUTES (Başvuru formları vb.) — 20/dk per IP
    // ===========================================
    Route::prefix('public')->middleware('throttle:public')->group(function () {
        // Firma bazlı açık pozisyonlar
        Route::get('/companies/{companySlug}/jobs', [\App\Http\Controllers\Api\V1\Public\JobController::class, 'index']);

        // Pozisyon detayı (slug ile)
        Route::get('/jobs/{positionSlug}', [\App\Http\Controllers\Api\V1\Public\JobController::class, 'show']);

        // Başvuru gönder
        Route::post('/jobs/{positionSlug}/apply', [\App\Http\Controllers\Api\V1\Public\ApplicationController::class, 'store']);
    });
});

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

        // Users (Company Admin only)
        Route::middleware('company_admin')->group(function () {
            // Export/Import routes (must be before apiResource to avoid conflicts) — 20/dk
            Route::middleware('throttle:exports')->group(function () {
                Route::get('/users/export', [\App\Http\Controllers\Api\V1\UserController::class, 'export']);
                Route::post('/users/import', [\App\Http\Controllers\Api\V1\UserController::class, 'import']);
            });
            Route::post('/users/invite', [\App\Http\Controllers\Api\V1\UserController::class, 'invite']);
            Route::post('/users/bulk-update', [\App\Http\Controllers\Api\V1\UserController::class, 'bulkUpdate']);

            Route::apiResource('users', \App\Http\Controllers\Api\V1\UserController::class);
            Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Api\V1\UserController::class, 'resetPassword']);
            Route::post('/users/{user}/toggle-status', [\App\Http\Controllers\Api\V1\UserController::class, 'toggleStatus']);
            Route::post('/users/{user}/avatar', [\App\Http\Controllers\Api\V1\UserController::class, 'uploadAvatar']);
            Route::delete('/users/{user}/avatar', [\App\Http\Controllers\Api\V1\UserController::class, 'deleteAvatar']);
            Route::post('/users/{user}/2fa/enable', [\App\Http\Controllers\Api\V1\UserController::class, 'enable2FA']);
            Route::post('/users/{user}/2fa/verify', [\App\Http\Controllers\Api\V1\UserController::class, 'verify2FA']);
            Route::post('/users/{user}/2fa/disable', [\App\Http\Controllers\Api\V1\UserController::class, 'disable2FA']);
            Route::get('/users/{user}/2fa/recovery-codes', [\App\Http\Controllers\Api\V1\UserController::class, 'getRecoveryCodes']);
            Route::post('/users/{user}/2fa/recovery-codes/regenerate', [\App\Http\Controllers\Api\V1\UserController::class, 'regenerateRecoveryCodes']);
            Route::get('/users/{user}/sessions', [\App\Http\Controllers\Api\V1\UserController::class, 'sessions']);
            Route::delete('/users/{user}/sessions/{tokenId}', [\App\Http\Controllers\Api\V1\UserController::class, 'revokeSession']);
            Route::delete('/users/{user}/sessions', [\App\Http\Controllers\Api\V1\UserController::class, 'revokeAllSessions']);
        });

        // Roles & Permissions
        Route::middleware('company_admin')->group(function () {
            Route::apiResource('roles', \App\Http\Controllers\Api\V1\RoleController::class);
            Route::get('/permissions', [\App\Http\Controllers\Api\V1\RoleController::class, 'permissions']);
        });

        // Webhooks (Company Admin only)
        Route::middleware('company_admin')->prefix('webhooks')->group(function () {
            Route::apiResource('', \App\Http\Controllers\Api\V1\WebhookController::class)->parameters(['' => 'webhook']);
            Route::get('/{webhook}/logs', [\App\Http\Controllers\Api\V1\WebhookController::class, 'logs']);
            Route::post('/{webhook}/test', [\App\Http\Controllers\Api\V1\WebhookController::class, 'test']);
            Route::post('/{webhook}/regenerate-secret', [\App\Http\Controllers\Api\V1\WebhookController::class, 'regenerateSecret']);
        });

        // Company Settings (Company Admin only)
        Route::middleware('company_admin')->prefix('company')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\CompanyController::class, 'show']);
            Route::put('/', [\App\Http\Controllers\Api\V1\CompanyController::class, 'update']);
            Route::get('/modules', [\App\Http\Controllers\Api\V1\CompanyController::class, 'modules']);
            Route::post('/logo', [\App\Http\Controllers\Api\V1\CompanyController::class, 'uploadLogo']);
            Route::delete('/logo', [\App\Http\Controllers\Api\V1\CompanyController::class, 'deleteLogo']);

            // Settings
            Route::get('/settings', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'index']);
            Route::put('/settings', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'update']);
            Route::post('/settings/smtp/test', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'testSmtp']);
            Route::post('/settings/sms/test', [\App\Http\Controllers\Api\V1\CompanySettingsController::class, 'testSms']);
        });

        // API Keys
        Route::middleware('company_admin')->prefix('api-keys')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'destroy']);
            Route::post('/{id}/regenerate', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'regenerate']);
        });

        // Branches (Company Admin only)
        Route::middleware('company_admin')->prefix('branches')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\BranchController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\BranchController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\BranchController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\BranchController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\BranchController::class, 'destroy']);
            Route::post('/{id}/set-headquarters', [\App\Http\Controllers\Api\V1\BranchController::class, 'setHeadquarters']);
            Route::get('/{id}/employees', [\App\Http\Controllers\Api\V1\BranchController::class, 'employees']);
        });

        // Employees (Company Admin only)
        Route::middleware('company_admin')->prefix('employees')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'store']);
            Route::middleware('throttle:exports')->group(function () {
                Route::get('/export', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'export']);
                Route::post('/import', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'import']);
                Route::get('/import/template', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'importTemplate']);
            });
            Route::post('/bulk-update', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'bulkUpdate']);
            Route::post('/bulk-delete', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'bulkDelete']);
            Route::get('/departments', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'departments']);
            Route::get('/managers', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'managers']);
            Route::get('/custom-fields', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getCustomFields']);
            Route::get('/organization-chart', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getOrganizationChart']);
            Route::get('/stats', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getStats']);

            // BI Raporlama (MUST be before /{id} routes)
            Route::prefix('reports')->group(function () {
                Route::get('/metadata', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'metadata']);
                Route::post('/data', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'getData'])
                    ->middleware('throttle:exports');
                Route::get('/saved', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'savedReports']);
                Route::post('/saved', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'saveReport']);
                Route::put('/saved/{id}', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'updateReport']);
                Route::delete('/saved/{id}', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'deleteReport']);
                Route::post('/saved/{id}/favorite', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'toggleFavorite']);
                Route::post('/export/excel', [\App\Http\Controllers\Api\V1\EmployeeReportController::class, 'exportExcel'])
                    ->middleware('throttle:exports');
            });

            // Dashboard (Çoklu Widget BI Dashboard - MUST be before /{id} routes)
            Route::prefix('dashboards')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'store']);
                Route::post('/widget-data', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'getWidgetData']);
                Route::get('/{dashboardId}', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'show']);
                Route::put('/{dashboardId}', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'update']);
                Route::delete('/{dashboardId}', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'destroy']);
                Route::post('/{dashboardId}/favorite', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'toggleFavorite']);
                Route::get('/{dashboardId}/export/excel', [\App\Http\Controllers\Api\V1\EmployeeDashboardController::class, 'exportExcel'])
                    ->middleware('throttle:exports');
            });

            // Employee CRUD (/{id} routes MUST be AFTER specific routes)
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'destroy']);
            Route::post('/{id}/portal-access', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'createPortalAccess']);
            Route::delete('/{id}/portal-access', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'revokePortalAccess']);

            // Personel alt verileri
            Route::get('/{id}/leaves', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getLeaves']);
            Route::get('/{id}/trainings', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainings']);
            Route::get('/{id}/assets', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getAssets']);
            Route::get('/{id}/performance', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getPerformance']);
            Route::get('/{id}/activity', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'getActivity']);

            // Personel belgeleri
            Route::get('/{id}/documents', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'index']);
            Route::post('/{id}/documents', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'store']);
            Route::get('/{id}/documents/{docId}', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'show']);
            Route::put('/{id}/documents/{docId}', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'update']);
            Route::delete('/{id}/documents/{docId}', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'destroy']);
            Route::get('/{id}/documents/{docId}/download', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'download']);
        });

        // Personel belge kategorileri ve süresi dolacak belgeler
        Route::middleware('company_admin')->prefix('employee-documents')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'categories']);
            Route::get('/expiring-soon', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'expiringSoon']);
        });

        // Departments (Departman Yönetimi)
        Route::middleware('company_admin')->prefix('departments')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'store']);
            Route::get('/managers', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'getManagers']);
            Route::get('/hierarchy', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'getHierarchy']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'destroy']);
        });

        // Custom Fields (Company Admin only)
        Route::middleware('company_admin')->prefix('custom-fields')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'store']);
            Route::get('/field-types', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'getFieldTypes']);
            Route::get('/entity-types', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'getEntityTypes']);
            Route::post('/reorder', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'reorder']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'destroy']);
        });

        // Activity Logs
        Route::get('/activity-logs', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'index']);
        Route::get('/activity-logs/export', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'export'])
            ->middleware('throttle:exports');
        Route::get('/activity-logs/{id}', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'show']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
            Route::post('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
        });

        // ===========================================
        // MODÜL BAZLI ROUTES
        // ===========================================

        // İş Başvuru Modülü
        Route::middleware('module.access:job-applications')->prefix('recruitment')->group(function () {
            Route::apiResource('positions', \App\Http\Controllers\Api\V1\Recruitment\JobPositionController::class);

            // Başvurular
            Route::get('/applications', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'index']);
            Route::get('/applications/{id}', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'show']);
            Route::put('/applications/{id}/status', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'updateStatus']);
            Route::put('/applications/{id}/notes', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'updateNotes']);
            Route::put('/applications/{id}/rate', [\App\Http\Controllers\Api\V1\Recruitment\ApplicationController::class, 'rate']);

            // CV Havuzu
            Route::get('/cv-pool', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'index']);
            Route::post('/cv-pool/bulk-tag', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'bulkTag']);
            Route::delete('/cv-pool/{id}/tag', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'removeTag']);
            Route::put('/cv-pool/{id}/rate', [\App\Http\Controllers\Api\V1\Recruitment\CvPoolController::class, 'rate']);

            // Raporlar
            Route::prefix('reports')->group(function () {
                Route::get('/summary', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'summary']);
                Route::get('/by-position', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'byPosition']);
                Route::get('/by-source', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'bySource']);
                Route::get('/trends', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'trends']);
                Route::get('/time-to-hire', [\App\Http\Controllers\Api\V1\Recruitment\ReportController::class, 'timeToHire']);
            });

            // Mülakatlar
            Route::prefix('interviews')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'index']);
                Route::get('/types', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'getTypes']);
                Route::get('/calendar', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'calendar']);
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'destroy']);
                Route::post('/{id}/complete', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'complete']);
                Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Recruitment\InterviewController::class, 'cancel']);
            });

            // Form Builder
            Route::apiResource('forms', \App\Http\Controllers\Api\V1\Recruitment\FormBuilderController::class);
        });

        // Evrak Yönetimi Modülü
        Route::middleware('module.access:document-management')->prefix('documents')->group(function () {
            // Kategoriler
            Route::get('/categories', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'index']);
            Route::post('/categories', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'store']);
            Route::put('/categories/{id}', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [\App\Http\Controllers\Api\V1\Documents\CategoryController::class, 'destroy']);

            // Raporlar (MUST be before /{id} routes)
            Route::prefix('reports')->group(function () {
                Route::get('/metadata', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'metadata']);
                Route::post('/widget-data', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'getWidgetData']);
                Route::post('/kpi-data', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'getKpiData']);
                Route::get('/summary', [\App\Http\Controllers\Api\V1\Documents\ReportController::class, 'summary']);
            });

            // İstatistikler
            Route::get('/stats', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'stats']);
        });

        Route::middleware('module.access:document-management')->group(function () {
            Route::get('/documents', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'index']);
            Route::post('/documents', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'store']);
            Route::get('/documents/{id}', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'show']);
            Route::put('/documents/{id}', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'update']);
            Route::delete('/documents/{id}', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'destroy']);
            Route::get('/documents/{id}/download', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'download']);
            Route::get('/documents/{id}/versions', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'versions']);
            Route::get('/documents/{id}/versions/{versionId}/download', [\App\Http\Controllers\Api\V1\Documents\DocumentController::class, 'downloadVersion']);
        });

        // Onboarding Modülü
        Route::middleware('module.access:onboarding')->prefix('onboarding')->group(function () {
            Route::apiResource('templates', \App\Http\Controllers\Api\V1\Onboarding\TemplateController::class);
            Route::apiResource('processes', \App\Http\Controllers\Api\V1\Onboarding\ProcessController::class);
            Route::post('/processes/{id}/tasks/{taskId}/complete', [\App\Http\Controllers\Api\V1\Onboarding\ProcessController::class, 'completeTask']);
        });

        // İzin Yönetimi Modülü
        Route::middleware('module.access:leave-management')->prefix('leaves')->group(function () {
            Route::apiResource('types', \App\Http\Controllers\Api\V1\Leaves\LeaveTypeController::class);
            Route::apiResource('requests', \App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class);
            Route::post('/requests/{id}/approve', [\App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class, 'approve']);
            Route::post('/requests/{id}/reject', [\App\Http\Controllers\Api\V1\Leaves\LeaveRequestController::class, 'reject']);
            Route::get('/calendar', [\App\Http\Controllers\Api\V1\Leaves\LeaveCalendarController::class, 'index']);
            Route::get('/balance', [\App\Http\Controllers\Api\V1\Leaves\LeaveBalanceController::class, 'index']);

            // Tatil Takvimi
            Route::prefix('holidays')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'index']);
                Route::get('/types', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'getTypes']);
                Route::get('/range', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'getHolidaysInRange']);
                Route::post('/check-date', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'checkDate']);
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Leaves\HolidayController::class, 'destroy']);
            });

            // Hakediş Politikaları
            Route::prefix('accrual-policies')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'index']);
                Route::get('/types', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'getAccrualTypes']);
                Route::get('/log-types', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'getLogTypes']);
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'destroy']);
                Route::get('/user/{userId}/logs', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'getUserAccrualLogs']);
                Route::post('/process-monthly', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'processMonthlyAccruals']);
                Route::post('/process-carryover', [\App\Http\Controllers\Api\V1\Leaves\AccrualPolicyController::class, 'processYearEndCarryover']);
            });
        });

        // Onay İş Akışları (Workflow Engine)
        Route::middleware('company_admin')->prefix('workflows')->group(function () {
            Route::get('/entity-types', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'getEntityTypes']);
            Route::get('/approver-types', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'getApproverTypes']);
            Route::get('/by-entity/{entityType}', [\App\Http\Controllers\Api\V1\Workflow\WorkflowController::class, 'getByEntityType']);
            Route::apiResource('/', \App\Http\Controllers\Api\V1\Workflow\WorkflowController::class)->parameters(['' => 'workflow']);
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
            Route::apiResource('periods', \App\Http\Controllers\Api\V1\Performance\PeriodController::class);
            Route::post('/periods/{id}/activate', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'activate']);
            Route::post('/periods/{id}/close', [\App\Http\Controllers\Api\V1\Performance\PeriodController::class, 'close']);

            Route::apiResource('criteria', \App\Http\Controllers\Api\V1\Performance\CriteriaController::class);

            Route::apiResource('reviews', \App\Http\Controllers\Api\V1\Performance\ReviewController::class);
            Route::post('/reviews/{id}/submit', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'submit']);
            Route::post('/reviews/{id}/approve', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'approve']);
            Route::post('/reviews/{id}/reject', [\App\Http\Controllers\Api\V1\Performance\ReviewController::class, 'reject']);

            // OKR (Objectives & Key Results)
            Route::prefix('okr')->group(function () {
                Route::get('/labels', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'getLabels']);
                Route::get('/objectives', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'index']);
                Route::get('/objectives/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'show']);
                Route::post('/objectives', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'store']);
                Route::put('/objectives/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'update']);
                Route::delete('/objectives/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'destroy']);
                Route::post('/objectives/{id}/activate', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'activate']);
                Route::post('/objectives/{id}/key-results', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'addKeyResult']);
                Route::put('/key-results/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'updateKeyResult']);
                Route::delete('/key-results/{id}', [\App\Http\Controllers\Api\V1\Performance\OkrController::class, 'deleteKeyResult']);
            });

            // 360 Derece Geri Bildirim
            Route::prefix('feedback')->group(function () {
                Route::get('/types', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'getFeedbackTypes']);
                Route::get('/pending', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'pendingFeedbacks']);
                Route::get('/form/{providerId}', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'getFeedbackForm']);
                Route::post('/submit/{providerId}', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'submitFeedback']);
                Route::post('/decline/{providerId}', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'declineFeedback']);
                Route::post('/reviews/{reviewId}/providers', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'addProviders']);
                Route::get('/reviews/{reviewId}/results', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'getReviewFeedbacks']);

                // Sürekli Geri Bildirim
                Route::get('/continuous', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'continuousFeedbacks']);
                Route::post('/continuous', [\App\Http\Controllers\Api\V1\Performance\FeedbackController::class, 'sendContinuousFeedback']);
            });

            // Yetkinlikler
            Route::prefix('competencies')->group(function () {
                Route::get('/categories', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getCategories']);
                Route::get('/', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'index']);
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'destroy']);

                // Kullanıcı Yetkinlikleri
                Route::get('/user/{userId}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getUserCompetencies']);
                Route::post('/user/{userId}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'setUserCompetency']);

                // Pozisyon Yetkinlikleri
                Route::get('/position/{positionName}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getPositionCompetencies']);
                Route::post('/position', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'setPositionCompetency']);
                Route::delete('/position', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'removePositionCompetency']);

                // Skill Gap Analizi
                Route::get('/skill-gap/{userId}/{positionName}', [\App\Http\Controllers\Api\V1\Performance\CompetencyController::class, 'getSkillGapAnalysis']);
            });

            // 1-on-1 Görüşmeleri
            Route::prefix('one-on-one')->group(function () {
                Route::get('/labels', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'getLabels']);
                Route::get('/upcoming', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'upcoming']);
                Route::get('/', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'index']);
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'update']);
                Route::post('/{id}/complete', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'complete']);
                Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'cancel']);
                Route::post('/{id}/reschedule', [\App\Http\Controllers\Api\V1\Performance\OneOnOneController::class, 'reschedule']);
            });
        });

        // Eğitim Yönetimi Modülü
        Route::middleware('module.access:training')->prefix('training')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\V1\Training\TrainingController::class, 'categories']);
            Route::apiResource('trainings', \App\Http\Controllers\Api\V1\Training\TrainingController::class);

            Route::apiResource('sessions', \App\Http\Controllers\Api\V1\Training\SessionController::class);
            Route::post('/sessions/{id}/participants', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'addParticipant']);
            Route::delete('/sessions/{id}/participants/{userId}', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'removeParticipant']);
            Route::post('/sessions/{id}/attendance', [\App\Http\Controllers\Api\V1\Training\SessionController::class, 'updateAttendance']);
        });

        // Varlık Yönetimi Modülü
        Route::middleware('module.access:asset-management')->prefix('assets')->group(function () {
            Route::apiResource('categories', \App\Http\Controllers\Api\V1\Assets\CategoryController::class);

            Route::apiResource('items', \App\Http\Controllers\Api\V1\Assets\AssetController::class);
            Route::post('/items/{id}/assign', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'assign']);
            Route::post('/items/{id}/return', [\App\Http\Controllers\Api\V1\Assets\AssetController::class, 'returnAsset']);

            Route::apiResource('maintenance', \App\Http\Controllers\Api\V1\Assets\MaintenanceController::class);
            Route::post('/maintenance/{id}/complete', [\App\Http\Controllers\Api\V1\Assets\MaintenanceController::class, 'complete']);
        });

        // Anket & Geri Bildirim Modülü
        Route::middleware('module.access:surveys')->prefix('surveys')->group(function () {
            Route::get('/types', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'getTypes']);
            Route::get('/', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'destroy']);
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'submit']);
            Route::get('/{id}/results', [\App\Http\Controllers\Api\V1\Surveys\SurveyController::class, 'results']);
        });

        // HR Analytics Modülü
        Route::middleware('module.access:hr-analytics')->prefix('analytics')->group(function () {
            Route::get('/summary', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'summary']);
            Route::get('/workforce', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'workforce']);
            Route::get('/turnover', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'turnover']);
            Route::get('/recruitment', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'recruitment']);
            Route::get('/leaves', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'leaves']);
            Route::get('/training', [\App\Http\Controllers\Api\V1\Analytics\HrAnalyticsController::class, 'training']);
        });

        // Timesheet / Attendance (Company Admin)
        Route::prefix('attendance')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'index']);
            Route::get('/daily-summary', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'dailySummary']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'update']);
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'approve']);
            Route::post('/bulk-approve', [\App\Http\Controllers\Api\V1\Timesheet\AttendanceController::class, 'bulkApprove']);
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

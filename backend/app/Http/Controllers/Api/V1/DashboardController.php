<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\JobPosition;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseController
{
    /**
     * Dashboard verileri
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // SuperAdmin için özel response
        if ($user->isSuperAdmin()) {
            return $this->superAdminDashboard($user);
        }
        
        // Company yoksa hata döndürme, boş dashboard göster
        if (!$user->company) {
            return $this->success([
                'welcome_message' => 'Hoş geldiniz, ' . $user->name,
                'company' => null,
                'stats' => [],
                'modules' => [],
                'recent_activities' => [],
                'quick_actions' => [],
            ]);
        }

        $company = $user->company;
        $activeModules = $company->activeModules()->pluck('slug')->toArray();
        
        // Temel istatistikler
        $stats = [
            'total_users' => User::where('company_id', $company->id)->where('is_active', true)->count(),
            'active_modules' => count($activeModules),
        ];
        
        // Modüle göre ek istatistikler
        if (in_array('leave-management', $activeModules)) {
            $stats['pending_leaves'] = LeaveRequest::where('company_id', $company->id)
                ->where('status', 'pending')
                ->count();
        }
        
        if (in_array('job-applications', $activeModules)) {
            $stats['open_positions'] = JobPosition::where('company_id', $company->id)
                ->where('status', 'published')
                ->count();
        }
        
        if (in_array('document-management', $activeModules)) {
            $stats['total_documents'] = Document::where('company_id', $company->id)->count();
        }
        
        $data = [
            'welcome_message' => 'Hoş geldiniz, ' . $user->name,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'status' => $company->status,
                'package_type' => $company->package_type,
                'trial_ends_at' => $company->trial_ends_at?->format('d.m.Y'),
                'license_end_date' => $company->license_end_date?->format('d.m.Y'),
            ],
            'stats' => $stats,
            'modules' => $activeModules,
            'recent_activities' => [],
            'quick_actions' => $this->getQuickActions($activeModules, $user),
        ];

        return $this->success($data);
    }
    
    /**
     * SuperAdmin dashboard
     */
    private function superAdminDashboard($user): JsonResponse
    {
        return $this->success([
            'welcome_message' => 'Hoş geldiniz, ' . $user->name,
            'is_super_admin' => true,
            'company' => null,
            'stats' => [
                'total_companies' => Company::count(),
                'active_companies' => Company::where('status', 'active')->count(),
                'total_users' => User::where('type', '!=', 'super_admin')->count(),
            ],
            'modules' => [],
            'recent_activities' => [],
            'quick_actions' => [
                [
                    'label' => 'Firma Ekle',
                    'icon' => 'building',
                    'route' => '/admin/companies/create',
                ],
                [
                    'label' => 'Paket Oluştur',
                    'icon' => 'package',
                    'route' => '/admin/packages/create',
                ],
            ],
            'redirect_to_admin' => true,
        ]);
    }

    /**
     * Hızlı aksiyonlar
     */
    private function getQuickActions(array $activeModules, $user): array
    {
        $actions = [];

        if ($user->isCompanyAdmin() || $user->isSuperAdmin()) {
            $actions[] = [
                'label' => 'Kullanıcı Ekle',
                'icon' => 'bi-person-plus',
                'route' => '/users/create',
            ];
        }

        if (in_array('job-applications', $activeModules)) {
            $actions[] = [
                'label' => 'İlan Oluştur',
                'icon' => 'bi-briefcase',
                'route' => '/recruitment/positions/create',
            ];
            $actions[] = [
                'label' => 'Başvuruları Gör',
                'icon' => 'bi-person-badge',
                'route' => '/recruitment/applications',
            ];
        }

        if (in_array('leave-management', $activeModules)) {
            $actions[] = [
                'label' => 'İzin Talebi',
                'icon' => 'bi-calendar-plus',
                'route' => '/leaves/requests/create',
            ];
        }

        if (in_array('document-management', $activeModules)) {
            $actions[] = [
                'label' => 'Evrak Yükle',
                'icon' => 'bi-file-earmark-arrow-up',
                'route' => '/documents/upload',
            ];
        }

        return $actions;
    }
}


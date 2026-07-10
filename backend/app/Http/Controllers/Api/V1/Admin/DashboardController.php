<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    /**
     * SuperAdmin dashboard verileri
     */
    public function index(Request $request): JsonResponse
    {
        // Genel istatistikler
        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'trial_companies' => Company::where('status', 'trial')->count(),
            'suspended_companies' => Company::where('status', 'suspended')->count(),
            'total_users' => User::where('type', '!=', 'super_admin')->count(),
            'active_users' => User::where('type', '!=', 'super_admin')->where('is_active', true)->count(),
            'total_modules' => Module::where('is_active', true)->count(),
        ];

        // Son 30 gün içinde oluşturulan firmalar
        $stats['new_companies_this_month'] = Company::where('created_at', '>=', now()->subDays(30))->count();

        // Paket dağılımı
        $packageDistribution = Company::selectRaw('package_type, count(*) as count')
            ->groupBy('package_type')
            ->pluck('count', 'package_type');

        // En son kaydolan firmalar
        $recentCompanies = Company::orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'name', 'status', 'package_type', 'created_at']);

        // Son aktiviteler
        $recentActivities = ActivityLog::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get(['id', 'user_id', 'user_name', 'action', 'description', 'created_at']);

        // Deneme süresi yakında bitecek firmalar
        $expiringTrials = Company::where('status', 'trial')
            ->where('trial_ends_at', '<=', now()->addDays(7))
            ->where('trial_ends_at', '>=', now())
            ->get(['id', 'name', 'trial_ends_at']);

        // Lisansı yakında bitecek firmalar
        $expiringLicenses = Company::where('status', 'active')
            ->whereNotNull('license_end_date')
            ->where('license_end_date', '<=', now()->addDays(30))
            ->where('license_end_date', '>=', now())
            ->get(['id', 'name', 'license_end_date']);

        return $this->success([
            'stats' => $stats,
            'package_distribution' => $packageDistribution,
            'recent_companies' => $recentCompanies,
            'recent_activities' => $recentActivities,
            'expiring_trials' => $expiringTrials,
            'expiring_licenses' => $expiringLicenses,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\User;
use App\Models\LeaveRequest;
use App\Models\JobApplication;
use App\Models\PerformanceReview;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\OnboardingProcess;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HrAnalyticsController extends BaseController
{
    /**
     * Workforce Analytics - İş gücü istatistikleri
     */
    public function workforce(): JsonResponse
    {
        $companyId = $this->getCompanyId();
        
        // Toplam çalışan sayısı
        $totalEmployees = User::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        // Departman dağılımı
        $departmentDistribution = User::where('company_id', $companyId)
            ->where('is_active', true)
            ->select('department_id', DB::raw('count(*) as count'))
            ->groupBy('department_id')
            ->with('department:id,name')
            ->get();

        // Son 12 ay çalışan sayısı trendi
        $employeeTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = User::where('company_id', $companyId)
                ->whereDate('created_at', '<=', $date->endOfMonth())
                ->where(function ($q) use ($date) {
                    $q->whereNull('deleted_at')
                        ->orWhereDate('deleted_at', '>', $date->endOfMonth());
                })
                ->count();
            
            $employeeTrend[] = [
                'month' => $date->format('Y-m'),
                'count' => $count,
            ];
        }

        return $this->success([
            'total_employees' => $totalEmployees,
            'department_distribution' => $departmentDistribution,
            'employee_trend' => $employeeTrend,
        ], 'İş gücü analitikleri');
    }

    /**
     * Turnover Analytics - İşten ayrılma istatistikleri
     */
    public function turnover(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $year = $request->get('year', now()->year);
        
        // Ayrılan çalışanlar (soft delete)
        $leavers = User::where('company_id', $companyId)
            ->onlyTrashed()
            ->whereYear('deleted_at', $year)
            ->count();

        // Ortalama çalışan sayısı (basit hesaplama)
        $avgEmployees = User::where('company_id', $companyId)->count();
        
        // Turnover oranı
        $turnoverRate = $avgEmployees > 0 ? ($leavers / $avgEmployees) * 100 : 0;

        // Aylık ayrılma trendi
        $monthlyTurnover = [];
        for ($month = 1; $month <= 12; $month++) {
            $count = User::where('company_id', $companyId)
                ->onlyTrashed()
                ->whereYear('deleted_at', $year)
                ->whereMonth('deleted_at', $month)
                ->count();
            
            $monthlyTurnover[] = [
                'month' => $month,
                'count' => $count,
            ];
        }

        return $this->success([
            'year' => $year,
            'total_leavers' => $leavers,
            'turnover_rate' => round($turnoverRate, 2),
            'monthly_turnover' => $monthlyTurnover,
        ], 'Turnover analitikleri');
    }

    /**
     * Recruitment Analytics - İşe alım istatistikleri
     */
    public function recruitment(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $dateFrom = $request->get('date_from', now()->subMonths(12)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        // Toplam başvuru
        $totalApplications = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        // Durum dağılımı
        $statusDistribution = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // İşe alınan sayısı
        $hired = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'hired')
            ->count();

        // Conversion rate
        $conversionRate = $totalApplications > 0 ? ($hired / $totalApplications) * 100 : 0;

        // Kaynak bazlı başvurular
        $sourceDistribution = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('source_id', DB::raw('count(*) as count'))
            ->groupBy('source_id')
            ->with('source:id,name')
            ->get();

        // Pozisyon bazlı başvurular
        $positionDistribution = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('job_position_id', DB::raw('count(*) as count'))
            ->groupBy('job_position_id')
            ->with('position:id,title')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return $this->success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_applications' => $totalApplications,
            'hired' => $hired,
            'conversion_rate' => round($conversionRate, 2),
            'status_distribution' => $statusDistribution,
            'source_distribution' => $sourceDistribution,
            'top_positions' => $positionDistribution,
        ], 'İşe alım analitikleri');
    }

    /**
     * Leave Analytics - İzin istatistikleri
     */
    public function leaves(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $year = $request->get('year', now()->year);

        // Toplam izin gün sayısı
        $totalDays = LeaveRequest::where('company_id', $companyId)
            ->whereYear('start_date', $year)
            ->where('status', 'approved')
            ->sum('total_days');

        // İzin türü dağılımı
        $typeDistribution = LeaveRequest::where('company_id', $companyId)
            ->whereYear('start_date', $year)
            ->where('status', 'approved')
            ->select('leave_type_id', DB::raw('sum(total_days) as total_days'), DB::raw('count(*) as count'))
            ->groupBy('leave_type_id')
            ->with('leaveType:id,name')
            ->get();

        // Aylık izin trendi
        $monthlyTrend = LeaveRequest::where('company_id', $companyId)
            ->whereYear('start_date', $year)
            ->where('status', 'approved')
            ->select(
                DB::raw('MONTH(start_date) as month'),
                DB::raw('sum(total_days) as total_days')
            )
            ->groupBy(DB::raw('MONTH(start_date)'))
            ->get();

        // Departman bazlı
        $departmentUsage = User::where('company_id', $companyId)
            ->whereHas('leaveRequests', function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                    ->where('status', 'approved');
            })
            ->with(['department:id,name'])
            ->withSum(['leaveRequests' => function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                    ->where('status', 'approved');
            }], 'total_days')
            ->get()
            ->groupBy('department_id')
            ->map(function ($users) {
                return [
                    'department' => $users->first()->department?->name ?? 'Belirtilmemiş',
                    'total_days' => $users->sum('leave_requests_sum_total_days'),
                    'employee_count' => $users->count(),
                ];
            });

        return $this->success([
            'year' => $year,
            'total_leave_days' => $totalDays,
            'type_distribution' => $typeDistribution,
            'monthly_trend' => $monthlyTrend,
            'department_usage' => $departmentUsage->values(),
        ], 'İzin analitikleri');
    }

    /**
     * Training Analytics - Eğitim istatistikleri
     */
    public function training(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $year = $request->get('year', now()->year);

        // Toplam eğitim sayısı
        $totalTrainings = Training::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->count();

        // Toplam katılımcı
        $totalParticipants = TrainingParticipant::whereHas('session.training', function ($q) use ($companyId, $year) {
            $q->where('company_id', $companyId)
                ->whereYear('created_at', $year);
        })->count();

        // Tamamlanma oranı
        $completedParticipants = TrainingParticipant::whereHas('session.training', function ($q) use ($companyId, $year) {
            $q->where('company_id', $companyId)
                ->whereYear('created_at', $year);
        })->where('status', 'completed')->count();

        $completionRate = $totalParticipants > 0 ? ($completedParticipants / $totalParticipants) * 100 : 0;

        // Kategori bazlı dağılım
        $categoryDistribution = Training::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->get();

        return $this->success([
            'year' => $year,
            'total_trainings' => $totalTrainings,
            'total_participants' => $totalParticipants,
            'completion_rate' => round($completionRate, 2),
            'category_distribution' => $categoryDistribution,
        ], 'Eğitim analitikleri');
    }

    /**
     * Dashboard özet
     */
    public function summary(): JsonResponse
    {
        $companyId = $this->getCompanyId();

        // Temel metrikler
        $totalEmployees = User::where('company_id', $companyId)->where('is_active', true)->count();
        
        $pendingLeaves = LeaveRequest::where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        $activeOnboarding = OnboardingProcess::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $openPositions = \App\Models\JobPosition::where('company_id', $companyId)
            ->where('status', 'open')
            ->count();

        $newApplicationsThisWeek = JobApplication::where('company_id', $companyId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $pendingReviews = PerformanceReview::where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        return $this->success([
            'total_employees' => $totalEmployees,
            'pending_leaves' => $pendingLeaves,
            'active_onboarding' => $activeOnboarding,
            'open_positions' => $openPositions,
            'new_applications_this_week' => $newApplicationsThisWeek,
            'pending_reviews' => $pendingReviews,
        ], 'Dashboard özeti');
    }
}



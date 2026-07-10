<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\JobPosition;
use App\Models\JobApplication;
use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends BaseController
{
    /**
     * Recruitment özet istatistikleri
     */
    public function summary(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $startDate = $request->get('start_date', Carbon::now()->subMonths(3)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        // Toplam pozisyon sayısı
        $totalPositions = JobPosition::where('company_id', $companyId)->count();
        $activePositions = JobPosition::where('company_id', $companyId)->where('status', 'active')->count();

        // Toplam başvuru sayısı
        $totalApplications = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Durum bazlı başvuru dağılımı
        $applicationsByStatus = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // İşe alınan sayısı
        $hired = $applicationsByStatus['hired'] ?? 0;

        // Reddedilen sayısı
        $rejected = $applicationsByStatus['rejected'] ?? 0;

        // Mülakat sayısı
        $totalInterviews = Interview::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Dönüşüm oranları
        $conversionRate = $totalApplications > 0 ? round(($hired / $totalApplications) * 100, 2) : 0;
        $interviewRate = $totalApplications > 0 ? round(($totalInterviews / $totalApplications) * 100, 2) : 0;

        return $this->success([
            'total_positions' => $totalPositions,
            'active_positions' => $activePositions,
            'total_applications' => $totalApplications,
            'total_interviews' => $totalInterviews,
            'hired' => $hired,
            'rejected' => $rejected,
            'conversion_rate' => $conversionRate,
            'interview_rate' => $interviewRate,
            'applications_by_status' => $applicationsByStatus,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Pozisyon bazlı başvuru raporu
     */
    public function byPosition(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $startDate = $request->get('start_date', Carbon::now()->subMonths(3)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        $positions = JobPosition::where('company_id', $companyId)
            ->withCount([
                'applications' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                },
                'applications as new_count' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->where('status', 'new');
                },
                'applications as reviewing_count' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->where('status', 'reviewing');
                },
                'applications as interview_count' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->whereIn('status', ['interview_scheduled', 'interviewed']);
                },
                'applications as hired_count' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->where('status', 'hired');
                },
                'applications as rejected_count' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->where('status', 'rejected');
                },
            ])
            ->orderBy('applications_count', 'desc')
            ->get()
            ->map(function ($position) {
                return [
                    'id' => $position->id,
                    'title' => $position->title,
                    'department' => $position->department,
                    'status' => $position->status,
                    'total_applications' => $position->applications_count,
                    'new_count' => $position->new_count,
                    'reviewing_count' => $position->reviewing_count,
                    'interview_count' => $position->interview_count,
                    'hired_count' => $position->hired_count,
                    'rejected_count' => $position->rejected_count,
                    'conversion_rate' => $position->applications_count > 0 
                        ? round(($position->hired_count / $position->applications_count) * 100, 2) 
                        : 0,
                ];
            });

        return $this->success($positions);
    }

    /**
     * Kaynak bazlı başvuru raporu
     */
    public function bySource(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $startDate = $request->get('start_date', Carbon::now()->subMonths(3)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        $sources = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('source')
            ->select(
                'source',
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired"),
                DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
                DB::raw("AVG(rating) as avg_rating")
            )
            ->groupBy('source')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($source) {
                return [
                    'source' => $source->source,
                    'total' => $source->total,
                    'hired' => (int) $source->hired,
                    'rejected' => (int) $source->rejected,
                    'avg_rating' => $source->avg_rating ? round($source->avg_rating, 2) : null,
                    'conversion_rate' => $source->total > 0 
                        ? round(($source->hired / $source->total) * 100, 2) 
                        : 0,
                ];
            });

        return $this->success($sources);
    }

    /**
     * Zaman bazlı trend raporu
     */
    public function trends(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $startDate = $request->get('start_date', Carbon::now()->subMonths(6)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
        $interval = $request->get('interval', 'month'); // day, week, month

        $dateFormat = match($interval) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m',
        };

        $trends = JobApplication::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired"),
                DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->success($trends);
    }

    /**
     * İşe alım süresi raporu (Time to Hire)
     */
    public function timeToHire(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $startDate = $request->get('start_date', Carbon::now()->subMonths(6)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        // İşe alınan adayların ortalama süreleri
        $hiredApplications = JobApplication::where('company_id', $companyId)
            ->where('status', 'hired')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('id', 'job_position_id', 'created_at', 'updated_at')
            ->with('position:id,title,department')
            ->get();

        $totalDays = 0;
        $count = 0;
        $byPosition = [];

        foreach ($hiredApplications as $app) {
            $days = Carbon::parse($app->created_at)->diffInDays(Carbon::parse($app->updated_at));
            $totalDays += $days;
            $count++;

            $positionTitle = $app->position->title ?? 'Bilinmeyen';
            if (!isset($byPosition[$positionTitle])) {
                $byPosition[$positionTitle] = ['total_days' => 0, 'count' => 0];
            }
            $byPosition[$positionTitle]['total_days'] += $days;
            $byPosition[$positionTitle]['count']++;
        }

        $avgTimeToHire = $count > 0 ? round($totalDays / $count, 1) : 0;

        $byPositionAvg = [];
        foreach ($byPosition as $title => $data) {
            $byPositionAvg[] = [
                'position' => $title,
                'avg_days' => round($data['total_days'] / $data['count'], 1),
                'hired_count' => $data['count'],
            ];
        }

        usort($byPositionAvg, fn($a, $b) => $b['hired_count'] - $a['hired_count']);

        return $this->success([
            'overall_avg_days' => $avgTimeToHire,
            'total_hired' => $count,
            'by_position' => $byPositionAvg,
        ]);
    }
}


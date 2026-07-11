<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Payslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalPayslipController extends BaseController
{
    /**
     * Bordrolarımı listele
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $query = Payslip::where('employee_id', $employee->id)
            ->published()
            ->orderByDesc('period');

        // Yıl filtresi
        if ($request->has('year')) {
            $query->ofYear($request->year);
        }

        $payslips = $query->paginate($request->get('per_page', 12));

        // Hassas verileri gizle (sadece net maaş göster)
        $payslips->getCollection()->transform(function ($payslip) {
            return [
                'id' => $payslip->id,
                'period' => $payslip->period,
                'period_label' => $payslip->period_label,
                'year' => $payslip->year,
                'month' => $payslip->month,
                'net_salary' => $payslip->net_salary,
                'is_viewed' => $payslip->is_viewed,
                'published_at' => $payslip->published_at,
                'has_file' => ! empty($payslip->file_path),
            ];
        });

        return $this->paginated($payslips);
    }

    /**
     * Bordro detayı
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $payslip = Payslip::where('employee_id', $employee->id)
            ->where('id', $id)
            ->published()
            ->first();

        if (! $payslip) {
            return $this->error('Bordro bulunamadı', null, 404);
        }

        // Okundu olarak işaretle
        $payslip->markAsViewed();

        ActivityLog::log('view_sensitive', $payslip, 'bordro görüntülendi');

        // Tüm detayları göster
        return $this->success([
            'id' => $payslip->id,
            'period' => $payslip->period,
            'period_label' => $payslip->period_label,
            'year' => $payslip->year,
            'month' => $payslip->month,
            'gross_salary' => $payslip->gross_salary,
            'net_salary' => $payslip->net_salary,
            'deductions' => $payslip->deductions,
            'bonuses' => $payslip->bonuses,
            'total_deductions' => $payslip->total_deductions,
            'total_bonuses' => $payslip->total_bonuses,
            'worked_days' => $payslip->worked_days,
            'overtime_hours' => $payslip->overtime_hours,
            'notes' => $payslip->notes,
            'published_at' => $payslip->published_at,
            'has_file' => ! empty($payslip->file_path),
        ]);
    }

    /**
     * Bordro PDF indir
     */
    public function download(Request $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $payslip = Payslip::where('employee_id', $employee->id)
            ->where('id', $id)
            ->published()
            ->first();

        if (! $payslip) {
            return $this->error('Bordro bulunamadı', null, 404);
        }

        if (! $payslip->file_path || ! Storage::disk('public')->exists($payslip->file_path)) {
            return $this->error('Bordro dosyası bulunamadı', null, 404);
        }

        ActivityLog::log('export', $payslip, 'bordro export edildi');

        $fileName = "Bordro_{$payslip->year}_{$payslip->month}.pdf";

        return Storage::disk('public')->download($payslip->file_path, $fileName);
    }

    /**
     * Yıl listesi (bordro olan yıllar)
     */
    public function years(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $years = Payslip::where('employee_id', $employee->id)
            ->published()
            ->selectRaw('DISTINCT year')
            ->orderByDesc('year')
            ->pluck('year');

        return $this->success($years);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Timesheet;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
        ]);

        $rows = $this->buildRows($request->user(), $validated);

        $totals = [
            'late_minutes' => (int) collect($rows)->sum('late_minutes'),
            'early_leave_minutes' => (int) collect($rows)->sum('early_leave_minutes'),
            'overtime_hours' => round((float) collect($rows)->sum('overtime_hours'), 2),
            'missing_minutes' => (int) collect($rows)->sum('missing_minutes'),
            'absent_days' => (int) collect($rows)->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'late_days' => (int) collect($rows)->where('status', AttendanceRecord::STATUS_LATE)->count(),
            'early_leave_days' => (int) collect($rows)->where('status', AttendanceRecord::STATUS_EARLY_LEAVE)->count(),
            'record_count' => count($rows),
        ];

        return $this->success([
            'filters' => $validated,
            'totals' => $totals,
            'rows' => $rows,
        ], 'Puantaj raporu');
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
        ]);

        $rows = $this->buildRows($request->user(), $validated);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Puantaj');
        $headers = [
            'Personel', 'Tarih', 'Giriş', 'Çıkış', 'Durum',
            'Geç (dk)', 'Erken (dk)', 'Eksik (dk)', 'Mesai (sa)', 'Toplam (sa)',
        ];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }

        $r = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue([1, $r], $row['user_name']);
            $sheet->setCellValue([2, $r], $row['date']);
            $sheet->setCellValue([3, $r], $row['clock_in']);
            $sheet->setCellValue([4, $r], $row['clock_out']);
            $sheet->setCellValue([5, $r], $row['status']);
            $sheet->setCellValue([6, $r], $row['late_minutes']);
            $sheet->setCellValue([7, $r], $row['early_leave_minutes']);
            $sheet->setCellValue([8, $r], $row['missing_minutes']);
            $sheet->setCellValue([9, $r], $row['overtime_hours']);
            $sheet->setCellValue([10, $r], $row['total_hours']);
            $r++;
        }

        ActivityLog::log('attendance_report_export', null, 'Puantaj raporu Excel export');

        $filename = 'puantaj_rapor_'.$validated['start_date'].'_'.$validated['end_date'].'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{start_date: string, end_date: string, user_id?: int, department_id?: int, branch_id?: int}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildRows(\App\Models\User $actor, array $filters): array
    {
        $query = AttendanceRecord::query()
            ->with(['user:id,name'])
            ->whereBetween('date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('date')
            ->orderBy('user_id');

        $this->dataScope->scopeForUser($query, $actor);

        if (! empty($filters['user_id'])) {
            $uid = (int) $filters['user_id'];
            if (! $this->dataScope->allowsUserId($actor, $uid)) {
                return [];
            }
            $query->where('user_id', $uid);
        }

        if (! empty($filters['department_id']) || ! empty($filters['branch_id'])) {
            $empQuery = Employee::query()
                ->where('company_id', $actor->company_id)
                ->whereNotNull('user_id');

            if (! empty($filters['department_id'])) {
                $empQuery->where('department_id', (int) $filters['department_id']);
            }
            if (! empty($filters['branch_id'])) {
                $empQuery->where('branch_id', (int) $filters['branch_id']);
            }

            $userIds = $empQuery->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $query->whereIn('user_id', $userIds);
        }

        return $query->get()->map(function (AttendanceRecord $r) {
            return [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'user_name' => $r->user?->name,
                'date' => $r->date?->toDateString(),
                'clock_in' => $this->hm($r->clock_in),
                'clock_out' => $this->hm($r->clock_out),
                'status' => $r->status,
                'late_minutes' => (int) ($r->late_minutes ?? 0),
                'early_leave_minutes' => (int) ($r->early_leave_minutes ?? 0),
                'missing_minutes' => (int) ($r->missing_minutes ?? 0),
                'overtime_hours' => (float) ($r->overtime_hours ?? 0),
                'total_hours' => $r->total_hours !== null ? (float) $r->total_hours : null,
            ];
        })->all();
    }

    protected function hm(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}

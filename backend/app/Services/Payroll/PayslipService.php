<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Bordro PDF yükleme / yayınlama (C5) — private disk.
 */
class PayslipService
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * @param  array{
     *   employee_id: int,
     *   period: string,
     *   gross_salary?: float|int|string|null,
     *   net_salary?: float|int|string|null,
     *   notes?: string|null,
     *   publish?: bool,
     * }  $data
     */
    public function createOrReplace(
        int $companyId,
        array $data,
        ?UploadedFile $file,
        ?int $actorId,
    ): Payslip {
        $period = (string) $data['period'];
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('period YYYY-MM formatında olmalıdır');
        }

        [$year, $month] = array_map('intval', explode('-', $period));

        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $data['employee_id'])
            ->first();

        if ($employee === null) {
            throw new InvalidArgumentException('Personel bulunamadı');
        }

        $payslip = Payslip::query()->firstOrNew([
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'period' => $period,
        ]);

        $payslip->year = $year;
        $payslip->month = $month;
        $payslip->gross_salary = $data['gross_salary'] ?? $payslip->gross_salary ?? 0;
        $payslip->net_salary = $data['net_salary'] ?? $payslip->net_salary ?? 0;
        $payslip->notes = $data['notes'] ?? $payslip->notes;
        $payslip->updated_by = $actorId;
        if (! $payslip->exists) {
            $payslip->created_by = $actorId;
            $payslip->is_published = false;
        }

        if ($file !== null) {
            if ($payslip->file_path && Storage::disk('private')->exists($payslip->file_path)) {
                Storage::disk('private')->delete($payslip->file_path);
            }
            $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $path = $file->storeAs(
                "payslips/{$companyId}/{$period}",
                ($safeName !== '' ? $safeName : 'payslip').'_'.$employee->id.'.pdf',
                'private'
            );
            $payslip->file_path = $path;
        }

        $payslip->save();

        if (! empty($data['publish'])) {
            $this->publish($payslip, $actorId);
        }

        return $payslip->fresh() ?? $payslip;
    }

    public function publish(Payslip $payslip, ?int $actorId = null): Payslip
    {
        $payslip->update([
            'is_published' => true,
            'published_at' => now(),
            'published_by' => $actorId ?? auth()->id(),
            'updated_by' => $actorId ?? auth()->id(),
        ]);

        $this->notifyPublished($payslip->fresh() ?? $payslip);

        return $payslip->fresh() ?? $payslip;
    }

    public function notifyPublished(Payslip $payslip): void
    {
        $employee = $payslip->employee;
        $user = $employee?->user;
        if (! $user instanceof User) {
            return;
        }
        if ((int) $user->company_id !== (int) $payslip->company_id) {
            return;
        }

        $this->notifications->notify($user, 'payslip.published', [
            'company_id' => (int) $payslip->company_id,
            'entity' => $payslip->period_label,
            'title' => $payslip->period_label,
            'user' => $user->name,
            'date' => now()->toDateString(),
            'panel' => 'portal',
            'path' => '/payslips',
        ]);
    }

    /**
     * Dosya adı eşleştirme: TCKN veya sicil (employee_code).
     */
    public function resolveEmployeeFromFilename(int $companyId, string $filename): ?Employee
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $token = preg_replace('/[^0-9A-Za-z_-]/', '', $base) ?? '';
        if ($token === '') {
            return null;
        }

        // TCKN (11 hane)
        if (preg_match('/(\d{11})/', $token, $m)) {
            $byTckn = Employee::query()
                ->where('company_id', $companyId)
                ->where('national_id', $m[1])
                ->first();
            if ($byTckn) {
                return $byTckn;
            }
        }

        return Employee::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($token) {
                $q->where('employee_code', $token)
                    ->orWhere('employee_code', strtoupper($token));
            })
            ->first();
    }
}

<?php

namespace App\Services\Documents;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\DocumentExpiryAlert;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Süreli evrak uyarıları — 30 / 7 gün kala, eşik başına bir kez.
 */
class DocumentExpiryAlertService
{
    /** @var list<int> */
    public const THRESHOLDS = [30, 7];

    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * @return array{checked: int, notified: int, skipped: int, failed: int}
     */
    public function processAllActiveCompanies(?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $summary = [
            'checked' => 0,
            'notified' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $companies = Company::query()
            ->where('status', CompanyStatus::Active)
            ->orderBy('id')
            ->pluck('id');

        foreach ($companies as $companyId) {
            try {
                $result = $this->processCompany((int) $companyId, $today);
                $summary['checked'] += $result['checked'];
                $summary['notified'] += $result['notified'];
                $summary['skipped'] += $result['skipped'];
            } catch (Throwable $e) {
                $summary['failed']++;
                Log::error('scheduler.document_expiry.company_failed', [
                    'company_id' => $companyId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('scheduler.document_expiry.finished', $summary);

        return $summary;
    }

    /**
     * @return array{checked: int, notified: int, skipped: int}
     */
    public function processCompany(int $companyId, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $checked = 0;
        $notified = 0;
        $skipped = 0;

        foreach (self::THRESHOLDS as $threshold) {
            $targetDate = $today->copy()->addDays($threshold)->toDateString();

            $documents = EmployeeDocument::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', $targetDate)
                ->with(['employee.user'])
                ->get();

            foreach ($documents as $document) {
                $checked++;

                $already = DocumentExpiryAlert::query()
                    ->withoutGlobalScopes()
                    ->where('employee_document_id', $document->id)
                    ->where('threshold_days', $threshold)
                    ->exists();

                if ($already) {
                    $skipped++;

                    continue;
                }

                $this->notifyForDocument($document, $threshold);
                $notified++;
            }
        }

        return compact('checked', 'notified', 'skipped');
    }

    protected function notifyForDocument(EmployeeDocument $document, int $threshold): void
    {
        DB::transaction(function () use ($document, $threshold): void {
            // Yarış: unique constraint + firstOrCreate
            $alert = DocumentExpiryAlert::query()->firstOrCreate(
                [
                    'employee_document_id' => $document->id,
                    'threshold_days' => $threshold,
                ],
                [
                    'company_id' => $document->company_id,
                    'notified_at' => now(),
                ]
            );

            // Başka worker oluşturduysa bildirim gönderme
            if (! $alert->wasRecentlyCreated) {
                return;
            }

            $title = $document->title;
            $expiry = $document->expiry_date?->toDateString() ?? '';
            $payloadBase = [
                'company_id' => (int) $document->company_id,
                'entity' => $title,
                'document_id' => $document->id,
                'threshold_days' => $threshold,
                'date' => $expiry,
                'days' => (string) $threshold,
            ];

            // Personel (portal)
            $employeeUser = $document->employee?->user;
            if ($employeeUser instanceof User
                && (int) $employeeUser->company_id === (int) $document->company_id
                && $document->is_visible_to_employee) {
                $this->notifications->notify($employeeUser, 'document.expiring', array_merge($payloadBase, [
                    'panel' => 'portal',
                    'path' => '/documents',
                    'user' => $employeeUser->name,
                ]));
            }

            // İK / yönetim
            foreach ($this->resolveHrRecipients((int) $document->company_id) as $hrUser) {
                if ($employeeUser && (int) $hrUser->id === (int) $employeeUser->id) {
                    continue;
                }

                $this->notifications->notify($hrUser, 'document.expiring', array_merge($payloadBase, [
                    'panel' => 'company',
                    'path' => '/documents',
                    'user' => $hrUser->name,
                ]));
            }
        });
    }

    /**
     * @return list<User>
     */
    protected function resolveHrRecipients(int $companyId): array
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('type', UserType::CompanyAdmin)
                    ->orWhereHas('roles', function ($r): void {
                        $r->whereIn('name', ['admin', 'hr_manager']);
                    });
            })
            ->orderBy('id')
            ->get()
            ->all();
    }
}

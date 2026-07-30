<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Jobs\BuildDataSubjectExportPackageJob;
use App\Models\DataSubjectExportPackage;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Services\Kvkk\DataSubjectRequestService;
use App\Services\Kvkk\PersonalDataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal — Verilerim / kendi KVKK talepleri.
 */
class PortalDataSubjectRequestController extends BaseController
{
    public function __construct(
        protected DataSubjectRequestService $requests,
        protected PersonalDataExportService $exports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $subject = $this->requests->resolveSubjectFromPortalUser($user);

        $rows = DataSubjectRequest::query()
            ->where('company_id', $user->company_id)
            ->where(function ($q) use ($subject, $user) {
                $q->where(function ($q2) use ($subject) {
                    $q2->where('subject_type', $subject['subject_type'])
                        ->where('subject_id', $subject['subject_id']);
                })->orWhere('contact', $user->email);
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (DataSubjectRequest $r) => [
                'id' => $r->id,
                'request_types' => $r->request_types,
                'status' => $r->status->value,
                'due_date' => $r->due_date?->toDateString(),
                'days_remaining' => $r->daysRemaining(),
                'identity_verified' => $r->identity_verified,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return $this->success($rows->all(), 'Taleplerim');
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'request_types' => 'required|array|min:1',
            'request_types.*' => 'string',
            'description' => 'nullable|string|max:5000',
        ]);
        $subject = $this->requests->resolveSubjectFromPortalUser($user);
        $row = $this->requests->create((int) $user->company_id, array_merge($validated, $subject, [
            'channel' => 'portal',
        ]), $user);

        return $this->success([
            'id' => $row->id,
            'status' => $row->status->value,
            'due_date' => $row->due_date?->toDateString(),
            'identity_verified' => $row->identity_verified,
        ], 'Talep oluşturuldu', 201);
    }

    public function requestExport(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $row = $this->findOwn($user, $id);
        $package = $this->exports->createPendingPackage($row, $user);
        BuildDataSubjectExportPackageJob::dispatchSync($package->id);

        return $this->success([
            'uuid' => $package->fresh()->uuid,
            'status' => $package->fresh()->status,
            'expires_at' => $package->fresh()->expires_at?->toIso8601String(),
        ], 'Paket hazır');
    }

    public function downloadExport(Request $request, int $id, string $uuid): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $row = $this->findOwn($user, $id);
        $package = DataSubjectExportPackage::query()
            ->where('data_subject_request_id', $row->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $package->isDownloadable()) {
            return $this->error('Paket süresi dolmuş veya mevcut değil', 410);
        }

        $this->exports->logDownload($package, $user);

        return Storage::disk('local')->download((string) $package->storage_path, 'kvkk-paket.zip');
    }

    private function findOwn(User $user, int $id): DataSubjectRequest
    {
        $subject = $this->requests->resolveSubjectFromPortalUser($user);

        return DataSubjectRequest::query()
            ->where('company_id', $user->company_id)
            ->where('id', $id)
            ->where(function ($q) use ($subject, $user) {
                $q->where(function ($q2) use ($subject) {
                    $q2->where('subject_type', $subject['subject_type'])
                        ->where('subject_id', $subject['subject_id']);
                })->orWhere('contact', $user->email);
            })
            ->firstOrFail();
    }
}

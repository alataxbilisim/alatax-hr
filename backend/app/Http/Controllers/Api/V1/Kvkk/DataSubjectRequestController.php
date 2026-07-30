<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Http\Controllers\Api\V1\BaseController;
use App\Jobs\BuildDataSubjectExportPackageJob;
use App\Models\DataSubjectExportPackage;
use App\Models\DataSubjectRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\Kvkk\DataSubjectRequestService;
use App\Services\Kvkk\PersonalDataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataSubjectRequestController extends BaseController
{
    public function __construct(
        protected DataSubjectRequestService $requests,
        protected PersonalDataExportService $exports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $this->getCompanyId();
        $q = DataSubjectRequest::query()
            ->where('company_id', $companyId)
            ->with(['assignee:id,name', 'verifier:id,name'])
            ->orderByRaw("CASE WHEN due_date < CURRENT_DATE AND status NOT IN ('completed','rejected') THEN 0 ELSE 1 END")
            ->orderBy('due_date');

        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        if ($request->boolean('overdue')) {
            $q->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', ['completed', 'rejected']);
        }

        $paginator = $q->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return $this->paginated(
            $paginator->through(fn (DataSubjectRequest $r) => $this->serialize($r)),
            'Veri sahibi talepleri'
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $companyId = (int) $this->getCompanyId();
        $open = DataSubjectRequest::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['completed', 'rejected'])
            ->count();
        $dueSoon = DataSubjectRequest::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['completed', 'rejected'])
            ->whereDate('due_date', '<=', now()->addDays(5)->toDateString())
            ->count();
        $overdue = DataSubjectRequest::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['completed', 'rejected'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
        $avgDays = DataSubjectRequest::query()
            ->where('company_id', $companyId)
            ->whereNotNull('responded_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (responded_at - created_at))/86400.0) as avg_days')
            ->value('avg_days');

        return $this->success([
            'open_count' => $open,
            'due_soon_count' => $dueSoon,
            'overdue_count' => $overdue,
            'avg_response_days' => $avgDays !== null ? round((float) $avgDays, 1) : null,
        ], 'Talep panosu');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => 'required|in:employee,candidate,former_employee,visitor,other',
            'subject_id' => 'nullable|integer|min:1',
            'applicant_name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'request_types' => 'required|array|min:1',
            'request_types.*' => 'string',
            'description' => 'nullable|string|max:5000',
            'channel' => 'required|in:portal,public_form,email,written,kep',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'identity_verified' => 'sometimes|boolean',
            'verification_method' => 'nullable|string|max:64',
        ]);

        /** @var User $user */
        $user = $request->user();
        $row = $this->requests->create((int) $this->getCompanyId(), $validated, $user);

        return $this->success($this->serialize($row), 'Talep oluşturuldu', 201);
    }

    public function show(int $id): JsonResponse
    {
        $row = $this->findOwned($id);

        return $this->success($this->serialize($row, true), 'Talep detayı');
    }

    public function verifyIdentity(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'verification_method' => 'required|string|max:64',
        ]);
        $row = $this->findOwned($id);
        /** @var User $user */
        $user = $request->user();
        $row = $this->requests->verifyIdentity($row, $user, $validated['verification_method']);

        return $this->success($this->serialize($row), 'Kimlik doğrulandı');
    }

    public function buildExport(Request $request, int $id): JsonResponse
    {
        $row = $this->findOwned($id);
        /** @var User $user */
        $user = $request->user();
        $package = $this->exports->createPendingPackage($row, $user);
        BuildDataSubjectExportPackageJob::dispatchSync($package->id);

        return $this->success([
            'package' => $this->serializePackage($package->fresh()),
        ], 'İhraç paketi hazır');
    }

    public function downloadExport(Request $request, int $id, string $uuid): StreamedResponse|JsonResponse
    {
        $row = $this->findOwned($id);
        $package = DataSubjectExportPackage::query()
            ->where('data_subject_request_id', $row->id)
            ->where('uuid', $uuid)
            ->where('company_id', $row->company_id)
            ->firstOrFail();

        /** @var User $user */
        $user = $request->user();
        if (! $this->canDownload($user, $row)) {
            return $this->error('Yetkisiz', 403);
        }

        if (! $package->isDownloadable()) {
            return $this->error('Paket süresi dolmuş veya mevcut değil', 410);
        }

        $this->exports->logDownload($package, $user);
        $disk = Storage::disk('local');

        return $disk->download((string) $package->storage_path, 'kvkk-paket-'.$package->uuid.'.zip');
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'template' => 'required|in:accept,partial,reject',
            'body' => 'required|string|max:20000',
            'rejection_reason' => 'nullable|string|max:5000',
        ]);
        $row = $this->findOwned($id);
        /** @var User $user */
        $user = $request->user();

        $body = $validated['body'];
        if (in_array('silme', $row->request_types ?? [], true) && in_array($validated['template'], ['accept', 'partial'], true)) {
            $scope = $this->requests->buildDestructionScope((int) $row->company_id);
            $body .= "\n\n---\nYasal saklama nedeniyle silinemeyenler:\n";
            foreach ($scope['retained'] as $item) {
                $body .= sprintf(
                    "- %s (%s, %s ay)\n",
                    $item['name'],
                    $item['legal_basis'],
                    $item['retention_period_months']
                );
            }
            $body .= "\nSilinebilecek veriler 'imha bekliyor' olarak işaretlendi (D2c).\n";
        }

        $row = $this->requests->respond(
            $row,
            $user,
            $validated['template'],
            $body,
            $validated['rejection_reason'] ?? null,
        );

        // Cevap dosyası (insan okunur HTML) — aynı güvenlik
        $path = 'kvkk-responses/'.$row->company_id.'/'.$row->id.'_'.uniqid('', true).'.html';
        Storage::disk('local')->put($path, '<!DOCTYPE html><html lang="tr"><body><pre>'.e($body).'</pre></body></html>');
        $row->forceFill(['response_file_path' => $path])->save();

        return $this->success($this->serialize($row->fresh()), 'Cevap kaydedildi');
    }

    private function findOwned(int $id): DataSubjectRequest
    {
        return DataSubjectRequest::query()
            ->where('company_id', (int) $this->getCompanyId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function canDownload(User $user, DataSubjectRequest $row): bool
    {
        if ($user->can('management.kvkk.requests.view') || $user->can('management.kvkk_requests.view') || $user->isSuperAdmin()) {
            return true;
        }
        // Talep sahibi (portal)
        $emp = Employee::query()->where('user_id', $user->id)->where('company_id', $user->company_id)->first();
        if ($emp && (int) $row->subject_id === (int) $emp->id) {
            return true;
        }

        return (int) $row->subject_id === (int) $user->id
            || strcasecmp((string) $row->contact, (string) $user->email) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DataSubjectRequest $r, bool $detail = false): array
    {
        $data = [
            'id' => $r->id,
            'subject_type' => $r->subject_type->value,
            'subject_id' => $r->subject_id,
            'applicant_name' => $r->applicant_name,
            'contact' => $r->contact,
            'request_types' => $r->request_types,
            'description' => $r->description,
            'channel' => $r->channel->value,
            'identity_verified' => $r->identity_verified,
            'verification_method' => $r->verification_method,
            'status' => $r->status->value,
            'due_date' => $r->due_date?->toDateString(),
            'days_remaining' => $r->daysRemaining(),
            'is_overdue' => $r->isOverdue(),
            'responded_at' => $r->responded_at?->toIso8601String(),
            'assigned_to' => $r->assigned_to,
            'assignee' => $r->assignee ? ['id' => $r->assignee->id, 'name' => $r->assignee->name] : null,
            'destruction_pending' => $r->destruction_pending,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
        if ($detail) {
            $data['response_body'] = $r->response_body;
            $data['response_template'] = $r->response_template;
            $data['rejection_reason'] = $r->rejection_reason;
            $data['destruction_scope'] = $r->destruction_scope;
            $data['packages'] = $r->exportPackages()->orderByDesc('id')->get()->map(fn ($p) => $this->serializePackage($p))->all();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePackage(DataSubjectExportPackage $p): array
    {
        return [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'status' => $p->status,
            'expires_at' => $p->expires_at?->toIso8601String(),
            'downloadable' => $p->isDownloadable(),
            // Önizleme YOK — içerik dönülmez
        ];
    }
}

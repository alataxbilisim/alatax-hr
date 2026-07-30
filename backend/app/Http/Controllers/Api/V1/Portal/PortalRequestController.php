<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\RequestType;
use App\Services\LookupService;
use App\Services\RequestTypeFormFieldsAdapter;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortalRequestController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
        protected RequestTypeFormFieldsAdapter $formFieldsAdapter,
        protected WorkflowService $workflowService,
    ) {}

    /**
     * Talep türlerini listele
     */
    public function types(Request $request): JsonResponse
    {
        $user = $request->user();

        $requestTypes = RequestType::where('company_id', $user->company_id)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'description', 'icon', 'color', 'requires_attachment', 'form_fields']);

        return $this->success($requestTypes);
    }

    /**
     * Taleplerim listesi
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $query = EmployeeRequest::where('employee_id', $employee->id)
            ->with('requestType:id,name,icon,color')
            ->orderByDesc('created_at');

        // Durum filtresi
        if ($request->filled('status')) {
            $this->lookups->assertValid(
                LookupService::TYPE_EMPLOYEE_REQUEST_STATUS,
                $request->string('status')->toString(),
                $this->getCompanyId(),
                'status'
            );
            $query->where('status', $request->status);
        }

        // Tip filtresi
        if ($request->has('request_type_id')) {
            $query->where('request_type_id', $request->request_type_id);
        }

        $requests = $query->paginate($request->get('per_page', 15));

        return $this->paginated($requests);
    }

    /**
     * Talep detayı
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $employeeRequest = EmployeeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->with(['requestType:id,name,icon,color', 'approver:id,name', 'history.changedBy:id,name'])
            ->first();

        if (! $employeeRequest) {
            return $this->error('Talep bulunamadı', 404);
        }

        return $this->success($employeeRequest);
    }

    /**
     * Yeni talep oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        // multipart: form_data JSON string gelebilir
        if (is_string($request->input('form_data'))) {
            $decoded = json_decode($request->input('form_data'), true);
            $request->merge(['form_data' => is_array($decoded) ? $decoded : []]);
        }

        $validated = $request->validate([
            'request_type_id' => 'required|exists:request_types,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'form_data' => 'nullable|array',
            'priority' => 'nullable|string|max:100',
            'effective_date' => 'nullable|date',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // Max 10MB per file
        ]);

        $this->lookups->assertValid(
            LookupService::TYPE_EMPLOYEE_REQUEST_PRIORITY,
            $validated['priority'] ?? null,
            $this->getCompanyId(),
            'priority'
        );

        // Talep türünü kontrol et
        $requestType = RequestType::where('id', $validated['request_type_id'])
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->first();

        if (! $requestType) {
            return $this->error('Geçersiz talep türü', 422);
        }

        // Ek dosya zorunlu mu?
        if ($requestType->requires_attachment && empty($request->file('attachments'))) {
            return $this->error('Bu talep türü için dosya eklenmesi zorunludur', 422);
        }

        $validated['form_data'] = $this->formFieldsAdapter->validateFormData(
            $requestType->form_fields,
            $validated['form_data'] ?? []
        );

        return DB::transaction(function () use ($request, $validated, $user, $employee, $requestType) {
            // Dosyaları yükle
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('request_attachments/'.$user->company_id, 'public');
                    $attachments[] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ];
                }
            }

            $requiresApproval = (bool) $requestType->requires_approval;

            $employeeRequest = EmployeeRequest::create([
                'company_id' => $user->company_id,
                'employee_id' => $employee->id,
                'request_type_id' => $validated['request_type_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'form_data' => $validated['form_data'] ?? null,
                'priority' => $validated['priority'] ?? 'normal',
                'effective_date' => $validated['effective_date'] ?? null,
                'attachments' => ! empty($attachments) ? $attachments : null,
                'status' => $requiresApproval
                    ? EmployeeRequest::STATUS_PENDING
                    : EmployeeRequest::STATUS_APPROVED,
                'approved_by' => $requiresApproval ? null : $user->id,
                'approved_at' => $requiresApproval ? null : now(),
                'created_by' => $user->id,
            ]);

            $employeeRequest->history()->create([
                'old_status' => null,
                'new_status' => $employeeRequest->status,
                'comment' => $requiresApproval ? 'Talep oluşturuldu' : 'Onay gerektirmeyen talep — otomatik onay',
                'changed_by' => $user->id,
            ]);

            // W1: requires_approval → ApprovalFlowEngine (workflow yoksa pending kalır; otomatik onay YOK)
            if ($requiresApproval) {
                $record = $this->workflowService->startWorkflow($employeeRequest, [
                    'requester_id' => $user->id,
                    'priority' => $employeeRequest->priority,
                    'request_type_id' => (int) $employeeRequest->request_type_id,
                    'department_id' => $employee->department_id,
                ]);

                if (! $record) {
                    Log::warning('portal.employee_request.created_without_workflow', [
                        'employee_request_id' => $employeeRequest->id,
                        'company_id' => $employeeRequest->company_id,
                    ]);
                }
            }

            return $this->created($employeeRequest->load('requestType:id,name'), 'Talep başarıyla oluşturuldu');
        });
    }

    /**
     * Talebi güncelle (sadece beklemede olanlar)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $employeeRequest = EmployeeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->first();

        if (! $employeeRequest) {
            return $this->error('Talep bulunamadı veya düzenlenemez', 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'form_data' => 'sometimes|nullable|array',
            'priority' => 'sometimes|nullable|string|max:100',
            'effective_date' => 'sometimes|nullable|date',
        ]);

        if (array_key_exists('priority', $validated)) {
            $this->lookups->assertValid(
                LookupService::TYPE_EMPLOYEE_REQUEST_PRIORITY,
                $validated['priority'] ?? null,
                $this->getCompanyId(),
                'priority'
            );
        }

        $employeeRequest->update($validated);

        return $this->success($employeeRequest, 'Talep güncellendi');
    }

    /**
     * Talebi iptal et
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $employeeRequest = EmployeeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->whereIn('status', ['pending', 'in_review'])
            ->first();

        if (! $employeeRequest) {
            return $this->error('Talep bulunamadı veya iptal edilemez', 404);
        }

        $employeeRequest->cancel();

        return $this->success(null, 'Talep iptal edildi');
    }

    /**
     * Bekleyen talep sayısı
     */
    public function pendingCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->success(['count' => 0]);
        }

        $count = EmployeeRequest::where('employee_id', $employee->id)
            ->pending()
            ->count();

        return $this->success(['count' => $count]);
    }
}

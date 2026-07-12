<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\RequestType;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalRequestController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
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
            return $this->error('Personel kaydı bulunamadı', null, 404);
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
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $employeeRequest = EmployeeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->with(['requestType:id,name,icon,color', 'approver:id,name', 'history.changedBy:id,name'])
            ->first();

        if (! $employeeRequest) {
            return $this->error('Talep bulunamadı', null, 404);
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
            return $this->error('Personel kaydı bulunamadı', null, 404);
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
            return $this->error('Geçersiz talep türü', null, 422);
        }

        // Ek dosya zorunlu mu?
        if ($requestType->requires_attachment && empty($request->file('attachments'))) {
            return $this->error('Bu talep türü için dosya eklenmesi zorunludur', null, 422);
        }

        return DB::transaction(function () use ($request, $validated, $user, $employee) {
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
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            // Geçmiş kaydı ekle
            $employeeRequest->history()->create([
                'old_status' => null,
                'new_status' => 'pending',
                'comment' => 'Talep oluşturuldu',
                'changed_by' => $user->id,
            ]);

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
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $employeeRequest = EmployeeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->first();

        if (! $employeeRequest) {
            return $this->error('Talep bulunamadı veya düzenlenemez', null, 404);
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
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $employeeRequest = EmployeeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->whereIn('status', ['pending', 'in_review'])
            ->first();

        if (! $employeeRequest) {
            return $this->error('Talep bulunamadı veya iptal edilemez', null, 404);
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

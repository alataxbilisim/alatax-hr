<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Enums\DataScopeLevel;
use App\Services\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeDocumentController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    /**
     * Personele ait belgeleri listele
     */
    public function index(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())
            ->findOrFail($employeeId);

        $this->authorize('view', $employee);

        $query = EmployeeDocument::where('employee_id', $employeeId)
            ->where('company_id', $this->getCompanyId());

        // Kategori filtresi
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Süresi dolmuş filtresi
        if ($request->has('is_expired')) {
            $query->where('is_expired', $request->boolean('is_expired'));
        }

        $documents = $query->with('uploadedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        // Süresi dolan belgeleri güncelle
        foreach ($documents as $doc) {
            $doc->checkExpiry();
        }

        return $this->success($documents);
    }

    /**
     * Belge detayı
     */
    public function show(int $employeeId, int $documentId): JsonResponse
    {
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('company_id', $this->getCompanyId())
            ->with('uploadedBy:id,name', 'employee:id,employee_code,user_id')
            ->findOrFail($documentId);

        $this->authorize('view', $document);

        $document->checkExpiry();

        return $this->success($document);
    }

    /**
     * Yeni belge yükle
     */
    public function store(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())
            ->findOrFail($employeeId);

        $this->authorize('createForEmployee', [EmployeeDocument::class, $employee]);

        $validated = $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => [
                'required',
                Rule::in(['id_card', 'contract', 'certificate', 'education', 'health', 'other']),
            ],
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:issue_date',
            'is_visible_to_employee' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store("employees/{$employeeId}/documents", 'private');

        $document = EmployeeDocument::create([
            'company_id' => $this->getCompanyId(),
            'employee_id' => $employeeId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'is_visible_to_employee' => $validated['is_visible_to_employee'] ?? true,
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
            'uploaded_by' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $document, "Personel belgesi yüklendi: {$validated['title']}");

        return $this->created($document->load('uploadedBy:id,name'), 'Belge başarıyla yüklendi');
    }

    /**
     * Belge güncelle
     */
    public function update(Request $request, int $employeeId, int $documentId): JsonResponse
    {
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('company_id', $this->getCompanyId())
            ->with('employee')
            ->findOrFail($documentId);

        $this->authorize('update', $document);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => [
                'sometimes',
                Rule::in(['id_card', 'contract', 'certificate', 'education', 'health', 'other']),
            ],
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'is_visible_to_employee' => 'boolean',
            'status' => 'sometimes|in:active,archived,expired',
            'notes' => 'nullable|string',
        ]);

        $oldValues = $document->toArray();

        $document->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        $document->checkExpiry();

        ActivityLog::log('update', $document, 'Personel belgesi güncellendi', $oldValues, $document->fresh()->toArray());

        return $this->success($document->load('uploadedBy:id,name'), 'Belge başarıyla güncellendi');
    }

    /**
     * Belge sil
     */
    public function destroy(int $employeeId, int $documentId): JsonResponse
    {
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('company_id', $this->getCompanyId())
            ->with('employee')
            ->findOrFail($documentId);

        $this->authorize('delete', $document);

        $oldValues = $document->toArray();

        if (Storage::disk('private')->exists($document->file_path)) {
            Storage::disk('private')->delete($document->file_path);
        }

        ActivityLog::log('delete', $document, "Personel belgesi silindi: {$document->title}", $oldValues, null);

        $document->delete();

        return $this->success(null, 'Belge başarıyla silindi');
    }

    /**
     * Belge indir
     */
    public function download(int $employeeId, int $documentId)
    {
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('company_id', $this->getCompanyId())
            ->with('employee')
            ->findOrFail($documentId);

        $this->authorize('view', $document);

        if (! Storage::disk('private')->exists($document->file_path)) {
            return $this->error('Dosya bulunamadı', 404);
        }

        return Storage::disk('private')->download(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $document->file_type]
        );
    }

    /**
     * Belge kategorilerini getir
     */
    public function categories(): JsonResponse
    {
        $categories = [
            ['key' => 'id_card', 'label' => 'Kimlik'],
            ['key' => 'contract', 'label' => 'Sözleşme'],
            ['key' => 'certificate', 'label' => 'Sertifika'],
            ['key' => 'education', 'label' => 'Eğitim'],
            ['key' => 'health', 'label' => 'Sağlık'],
            ['key' => 'other', 'label' => 'Diğer'],
        ];

        return $this->success($categories);
    }

    /**
     * Süresi yaklaşan belgeleri getir — DataScope ile personel sınırı
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmployeeDocument::class);

        $days = $request->get('days', 30);

        $query = EmployeeDocument::where('company_id', $this->getCompanyId())
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now())
            ->where('status', 'active')
            ->with(['employee:id,employee_code,user_id', 'employee.user:id,name']);

        $employeeIds = $this->dataScope->teamEmployeeIds($request->user());
        $scope = $this->dataScope->resolve($request->user());
        if ($scope !== DataScopeLevel::Company) {
            if ($scope === DataScopeLevel::Department) {
                $emp = $request->user()->employee;
                if ($emp?->department_id) {
                    $query->whereHas('employee', fn ($q) => $q->where('department_id', $emp->department_id));
                } else {
                    $query->whereIn('employee_id', $this->dataScope->ownEmployeeIds($request->user()));
                }
            } else {
                $query->whereIn('employee_id', $employeeIds !== []
                    ? $employeeIds
                    : $this->dataScope->ownEmployeeIds($request->user()));
            }
        }

        $documents = $query->orderBy('expiry_date', 'asc')->get();

        return $this->success($documents);
    }
}

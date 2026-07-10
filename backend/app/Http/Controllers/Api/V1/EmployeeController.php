<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\EmployeeInvitation;
use App\Models\ActivityLog;
use App\Models\AssetAssignment;
use App\Models\CustomFieldDefinition;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\PerformanceReview;
use App\Models\TrainingCertificate;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\EmployeeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends BaseController
{
    /**
     * Personel listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['user', 'department', 'manager.user'])
            ->where('company_id', $this->getCompanyId());

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filtreleme
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('position')) {
            $query->where('position', 'like', "%{$request->position}%");
        }

        // Sıralama
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Sayfalama
        $perPage = $request->get('per_page', 15);
        $employees = $query->paginate($perPage);

        return $this->success($employees);
    }

    /**
     * Personel detayı (ilişkili verilerle zenginleştirilmiş)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $employee = Employee::with([
            'user',
            'department',
            'manager.user',
            'subordinates.user',
            'documents' => function ($q) {
                $q->where('status', 'active')->orderBy('created_at', 'desc');
            },
            'requests' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(10);
            },
        ])->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        // İzin bilgilerini ekle (user_id varsa)
        $leaveData = null;
        if ($employee->user_id) {
            $currentYear = now()->year;

            // İzin bakiyeleri
            $leaveBalances = LeaveBalance::where('user_id', $employee->user_id)
                ->where('year', $currentYear)
                ->with('leaveType:id,name,color')
                ->get();

            // Son izin talepleri
            $leaveRequests = LeaveRequest::where('user_id', $employee->user_id)
                ->with('leaveType:id,name,color')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $leaveData = [
                'balances' => $leaveBalances,
                'requests' => $leaveRequests,
            ];
        }

        // Eğitim bilgilerini ekle
        $trainingData = null;
        if ($employee->user_id) {
            $trainings = TrainingParticipant::where('user_id', $employee->user_id)
                ->with(['session.training:id,title,category', 'session:id,training_id,start_date,end_date,status'])
                ->orderBy('created_at', 'desc')
                ->get();

            $certificates = TrainingCertificate::where('user_id', $employee->user_id)
                ->with('training:id,title')
                ->orderBy('issued_date', 'desc')
                ->get();

            $trainingData = [
                'participations' => $trainings,
                'certificates' => $certificates,
            ];
        }

        // Zimmet bilgilerini ekle
        $assetData = null;
        if ($employee->user_id) {
            $activeAssignments = AssetAssignment::where('user_id', $employee->user_id)
                ->whereNull('returned_date')
                ->with('asset:id,name,asset_code,brand,model,status')
                ->orderBy('assigned_date', 'desc')
                ->get();

            $pastAssignments = AssetAssignment::where('user_id', $employee->user_id)
                ->whereNotNull('returned_date')
                ->with('asset:id,name,asset_code,brand,model')
                ->orderBy('returned_date', 'desc')
                ->limit(10)
                ->get();

            $assetData = [
                'active' => $activeAssignments,
                'history' => $pastAssignments,
            ];
        }

        // Performans bilgilerini ekle
        $performanceData = null;
        if ($employee->user_id) {
            $reviews = PerformanceReview::where('employee_id', $employee->user_id)
                ->with('period:id,name,start_date,end_date')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $performanceData = [
                'reviews' => $reviews,
            ];
        }

        // Activity log (son 20 kayıt)
        $activityLog = ActivityLog::where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $this->success([
            'employee' => $employee,
            'leaves' => $leaveData,
            'trainings' => $trainingData,
            'assets' => $assetData,
            'performance' => $performanceData,
            'activity_log' => $activityLog,
        ]);
    }

    /**
     * Yeni personel oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees')->where(function ($query) {
                    return $query->where('company_id', $this->getCompanyId());
                }),
            ],
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'title' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'manager_id' => 'nullable|exists:employees,id',

            // Kişisel bilgiler
            'birth_date' => 'nullable|date',
            'national_id' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'blood_type' => 'nullable|string|max:10',
            'education_level' => 'nullable|string|max:50',

            // İletişim
            'personal_email' => 'nullable|email|max:255',
            'personal_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',

            // Acil durum
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:50',

            // İş bilgileri
            'hire_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'contract_type' => 'nullable|in:permanent,temporary,intern,contract',
            'work_type' => 'nullable|in:full_time,part_time,remote,hybrid',

            // Maaş bilgileri
            'gross_salary' => 'nullable|numeric|min:0',
            'net_salary' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',

            // SGK
            'sgk_number' => 'nullable|string|max:20',
            'sgk_start_date' => 'nullable|date',

            // Durum
            'status' => 'nullable|in:active,on_leave,suspended,terminated',
            'notes' => 'nullable|string',
            'custom_fields' => 'nullable|array',

            // Portal erişimi
            'create_portal_access' => 'boolean',
            'portal_email' => 'nullable|required_if:create_portal_access,true|email|unique:users,email',
        ]);

        DB::beginTransaction();
        try {
            // Personel kaydı oluştur
            $employee = Employee::create([
                'company_id' => $this->getCompanyId(),
                'employee_code' => $validated['employee_code'],
                'department_id' => $validated['department_id'] ?? null,
                'title' => $validated['title'] ?? null,
                'position' => $validated['position'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'marital_status' => $validated['marital_status'] ?? null,
                'blood_type' => $validated['blood_type'] ?? null,
                'education_level' => $validated['education_level'] ?? null,
                'personal_email' => $validated['personal_email'] ?? null,
                'personal_phone' => $validated['personal_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'district' => $validated['district'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relation' => $validated['emergency_contact_relation'] ?? null,
                'hire_date' => $validated['hire_date'] ?? null,
                'contract_start_date' => $validated['contract_start_date'] ?? null,
                'contract_end_date' => $validated['contract_end_date'] ?? null,
                'contract_type' => $validated['contract_type'] ?? null,
                'work_type' => $validated['work_type'] ?? null,
                'gross_salary' => $validated['gross_salary'] ?? null,
                'net_salary' => $validated['net_salary'] ?? null,
                'currency' => $validated['currency'] ?? 'TRY',
                'bank_name' => $validated['bank_name'] ?? null,
                'iban' => $validated['iban'] ?? null,
                'sgk_number' => $validated['sgk_number'] ?? null,
                'sgk_start_date' => $validated['sgk_start_date'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'notes' => $validated['notes'] ?? null,
                'custom_fields' => $validated['custom_fields'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Portal erişimi oluştur
            if ($request->get('create_portal_access', false)) {
                $temporaryPassword = Str::random(12);
                $invitationToken = Str::random(64);

                $user = User::create([
                    'company_id' => $this->getCompanyId(),
                    'name' => $validated['name'],
                    'email' => $validated['portal_email'],
                    'password' => Hash::make($temporaryPassword),
                    'type' => 'user',
                    'is_active' => true,
                    'invitation_token' => Hash::make($invitationToken),
                    'invited_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                // Employee'ye user_id'yi ata
                $employee->update(['user_id' => $user->id]);

                // Varsayılan rol ata
                $user->assignRole('employee');

                Mail::to($user->email)->queue(
                    new EmployeeInvitation($user->load('company'), $employee, $temporaryPassword, $invitationToken)
                );
            }

            ActivityLog::log('create', $employee, 'Personel kaydı oluşturuldu: '.$validated['name'], null, $employee->toArray());

            DB::commit();

            return $this->created($employee->load('user', 'department', 'manager'), 'Personel başarıyla oluşturuldu');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Personel oluşturulurken hata oluştu: '.$e->getMessage(), 500);
        }
    }

    /**
     * Personel güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'employee_code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('employees')->where(function ($query) {
                    return $query->where('company_id', $this->getCompanyId());
                })->ignore($employee->id),
            ],
            'department_id' => 'nullable|exists:departments,id',
            'title' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'manager_id' => 'nullable|exists:employees,id',
            'birth_date' => 'nullable|date',
            'national_id' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'blood_type' => 'nullable|string|max:10',
            'education_level' => 'nullable|string|max:50',
            'personal_email' => 'nullable|email|max:255',
            'personal_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:50',
            'hire_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'contract_type' => 'nullable|in:permanent,temporary,intern,contract',
            'work_type' => 'nullable|in:full_time,part_time,remote,hybrid',
            'gross_salary' => 'nullable|numeric|min:0',
            'net_salary' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',
            'sgk_number' => 'nullable|string|max:20',
            'sgk_start_date' => 'nullable|date',
            'status' => 'nullable|in:active,on_leave,suspended,terminated',
            'termination_date' => 'nullable|date',
            'termination_reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'custom_fields' => 'nullable|array',
        ]);

        $oldValues = $employee->toArray();

        $employee->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        ActivityLog::log('update', $employee, 'Personel kaydı güncellendi', $oldValues, $employee->fresh()->toArray());

        return $this->success($employee->load('user', 'department', 'manager'), 'Personel başarıyla güncellendi');
    }

    /**
     * Personel sil
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);
        $oldValues = $employee->toArray();

        ActivityLog::log('delete', $employee, 'Personel kaydı silindi', $oldValues, null);

        $employee->delete();

        return $this->success(null, 'Personel başarıyla silindi');
    }

    /**
     * Portal erişimi oluştur
     */
    public function createPortalAccess(Request $request, int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        if ($employee->user_id) {
            return $this->error('Bu personelin zaten portal erişimi var', 400);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $temporaryPassword = Str::random(12);
            $invitationToken = Str::random(64);

            $user = User::create([
                'company_id' => $this->getCompanyId(),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'type' => 'user',
                'is_active' => true,
                'invitation_token' => Hash::make($invitationToken),
                'invited_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $employee->update(['user_id' => $user->id]);
            $user->assignRole('employee');

            ActivityLog::log('update', $employee, 'Personele portal erişimi verildi');

            Mail::to($user->email)->queue(
                new EmployeeInvitation($user->load('company'), $employee, $temporaryPassword, $invitationToken)
            );

            DB::commit();

            return $this->success([
                'employee' => $employee->load('user'),
                'temporary_password' => $temporaryPassword,
            ], 'Portal erişimi başarıyla oluşturuldu');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Portal erişimi oluşturulurken hata oluştu: '.$e->getMessage(), 500);
        }
    }

    /**
     * Portal erişimini kaldır
     */
    public function revokePortalAccess(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        if (! $employee->user_id) {
            return $this->error('Bu personelin portal erişimi yok', 400);
        }

        DB::beginTransaction();
        try {
            $user = $employee->user;

            // Kullanıcıyı pasif yap
            $user->update(['is_active' => false]);

            // Employee'den user_id'yi kaldır
            $employee->update(['user_id' => null]);

            ActivityLog::log('update', $employee, 'Personelin portal erişimi kaldırıldı');

            DB::commit();

            return $this->success($employee, 'Portal erişimi başarıyla kaldırıldı');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Portal erişimi kaldırılırken hata oluştu: '.$e->getMessage(), 500);
        }
    }

    /**
     * Personel için özel alanları getir
     */
    public function getCustomFields(): JsonResponse
    {
        $fields = CustomFieldDefinition::forEntity('employee')
            ->active()
            ->ordered()
            ->where('company_id', $this->getCompanyId())
            ->get();

        return $this->success($fields);
    }

    /**
     * Personel listesini dışa aktar
     */
    public function export(Request $request)
    {
        $query = Employee::with(['user', 'department', 'manager.user'])
            ->where('company_id', $this->getCompanyId());

        // Filtreleri uygula
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $employees = $query->get();

        // CSV oluştur
        $filename = 'personel_listesi_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($employees) {
            $file = fopen('php://output', 'w');

            // BOM ekle (Excel için UTF-8 desteği)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Başlıklar
            fputcsv($file, [
                'Sicil No',
                'Ad Soyad',
                'Email',
                'Departman',
                'Pozisyon',
                'Ünvan',
                'İşe Giriş',
                'Durum',
                'Telefon',
            ], ';');

            // Veriler
            foreach ($employees as $employee) {
                fputcsv($file, [
                    $employee->employee_code,
                    $employee->user?->name ?? '-',
                    $employee->user?->email ?? $employee->personal_email ?? '-',
                    $employee->department?->name ?? '-',
                    $employee->position ?? '-',
                    $employee->title ?? '-',
                    $employee->hire_date?->format('d.m.Y') ?? '-',
                    $employee->status,
                    $employee->personal_phone ?? '-',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Personel import et
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,xlsx,xls,txt',
        ]);

        $importService = new EmployeeImportService(
            $this->getCompanyId(),
            auth()->id()
        );

        $result = $importService->import($request->file('file'));

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        ActivityLog::log('import', null, 'Personel import işlemi tamamlandı. Başarılı: '.$result['data']['success_count'].', Hatalı: '.$result['data']['failed_count']);

        return $this->success($result['data'], $result['message']);
    }

    /**
     * Import şablonu indir
     */
    public function importTemplate()
    {
        $content = EmployeeImportService::generateTemplate();

        $filename = 'personel_import_sablonu.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response($content, 200, $headers);
    }

    /**
     * Toplu güncelleme
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:employees,id',
            'data' => 'required|array',
            'data.status' => 'nullable|in:active,on_leave,suspended,terminated',
            'data.department_id' => 'nullable|exists:departments,id',
        ]);

        $employees = Employee::where('company_id', $this->getCompanyId())
            ->whereIn('id', $validated['ids'])
            ->get();

        if ($employees->isEmpty()) {
            return $this->error('Personel bulunamadı', 404);
        }

        $updateData = array_filter($validated['data'], fn ($v) => $v !== null);
        $updateData['updated_by'] = auth()->id();

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                $oldValues = $employee->toArray();
                $employee->update($updateData);
                ActivityLog::log('update', $employee, 'Toplu güncelleme yapıldı', $oldValues, $employee->fresh()->toArray());
            }

            DB::commit();

            return $this->success([
                'updated_count' => $employees->count(),
            ], 'Personeller başarıyla güncellendi');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Güncelleme sırasında hata oluştu: '.$e->getMessage(), 500);
        }
    }

    /**
     * Toplu silme
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:employees,id',
        ]);

        $employees = Employee::where('company_id', $this->getCompanyId())
            ->whereIn('id', $validated['ids'])
            ->get();

        if ($employees->isEmpty()) {
            return $this->error('Personel bulunamadı', 404);
        }

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                $oldValues = $employee->toArray();
                ActivityLog::log('delete', $employee, 'Toplu silme ile personel kaydı silindi', $oldValues, null);
                $employee->delete();
            }

            DB::commit();

            return $this->success([
                'deleted_count' => $employees->count(),
            ], 'Personeller başarıyla silindi');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Silme sırasında hata oluştu: '.$e->getMessage(), 500);
        }
    }

    /**
     * Departman listesi (dropdown için)
     */
    public function departments(): JsonResponse
    {
        $departments = Department::where('company_id', $this->getCompanyId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return $this->success($departments);
    }

    /**
     * Yönetici listesi (dropdown için)
     */
    public function managers(): JsonResponse
    {
        $managers = Employee::where('company_id', $this->getCompanyId())
            ->where('status', 'active')
            ->with('user:id,name')
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'user_id', 'position', 'title']);

        return $this->success($managers);
    }

    /**
     * Personelin izin bilgilerini getir
     */
    public function getLeaves(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        if (! $employee->user_id) {
            return $this->success([
                'balances' => [],
                'requests' => [],
            ]);
        }

        $currentYear = now()->year;

        $balances = LeaveBalance::where('user_id', $employee->user_id)
            ->where('year', $currentYear)
            ->with('leaveType:id,name,color')
            ->get();

        $requests = LeaveRequest::where('user_id', $employee->user_id)
            ->with('leaveType:id,name,color')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->success([
            'balances' => $balances,
            'requests' => $requests,
        ]);
    }

    /**
     * Personelin eğitim bilgilerini getir
     */
    public function getTrainings(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        if (! $employee->user_id) {
            return $this->success([
                'participations' => [],
                'certificates' => [],
            ]);
        }

        $participations = TrainingParticipant::where('user_id', $employee->user_id)
            ->with(['session.training:id,title,category,description', 'session:id,training_id,start_date,end_date,location,status'])
            ->orderBy('created_at', 'desc')
            ->get();

        $certificates = TrainingCertificate::where('user_id', $employee->user_id)
            ->with('training:id,title')
            ->orderBy('issued_date', 'desc')
            ->get();

        return $this->success([
            'participations' => $participations,
            'certificates' => $certificates,
        ]);
    }

    /**
     * Personelin zimmet bilgilerini getir
     */
    public function getAssets(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        if (! $employee->user_id) {
            return $this->success([
                'active' => [],
                'history' => [],
            ]);
        }

        $active = AssetAssignment::where('user_id', $employee->user_id)
            ->whereNull('returned_date')
            ->with(['asset:id,name,asset_code,brand,model,serial_number,status', 'asset.category:id,name'])
            ->orderBy('assigned_date', 'desc')
            ->get();

        $history = AssetAssignment::where('user_id', $employee->user_id)
            ->whereNotNull('returned_date')
            ->with('asset:id,name,asset_code,brand,model')
            ->orderBy('returned_date', 'desc')
            ->paginate(10);

        return $this->success([
            'active' => $active,
            'history' => $history,
        ]);
    }

    /**
     * Personelin performans bilgilerini getir
     */
    public function getPerformance(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        if (! $employee->user_id) {
            return $this->success([
                'reviews' => [],
            ]);
        }

        $reviews = PerformanceReview::where('employee_id', $employee->user_id)
            ->with(['period:id,name,start_date,end_date', 'reviewer:id,name', 'scores.criteria:id,name,weight'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return $this->success([
            'reviews' => $reviews,
        ]);
    }

    /**
     * Personelin aktivite logunu getir
     */
    public function getActivity(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        $activities = ActivityLog::where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->with('causer:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->success($activities);
    }

    /**
     * Organizasyon şeması için hiyerarşik veri
     */
    public function getOrganizationChart(): JsonResponse
    {
        $companyId = $this->getCompanyId();

        // Üst yöneticisi olmayan (root) personelleri bul
        $rootEmployees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNull('manager_id')
            ->with(['user:id,name,email', 'department:id,name'])
            ->get();

        // Recursive olarak alt çalışanları getir
        $buildTree = function ($employee) use (&$buildTree, $companyId) {
            $subordinates = Employee::where('company_id', $companyId)
                ->where('status', 'active')
                ->where('manager_id', $employee->id)
                ->with(['user:id,name,email', 'department:id,name'])
                ->get();

            $children = [];
            foreach ($subordinates as $sub) {
                $children[] = $buildTree($sub);
            }

            return [
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'position' => $employee->position,
                    'title' => $employee->title,
                    'user' => $employee->user,
                    'department' => $employee->department,
                ],
                'children' => $children,
                'expanded' => true,
            ];
        };

        $orgChart = [];
        foreach ($rootEmployees as $root) {
            $orgChart[] = $buildTree($root);
        }

        return $this->success($orgChart);
    }

    /**
     * Personel istatistikleri
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();

            // Tarih validasyonu
            $startDate = $request->get('start', now()->startOfYear()->toDateString());
            $endDate = $request->get('end', now()->toDateString());

            // Tarih formatını kontrol et ve düzelt
            try {
                $startDate = \Carbon\Carbon::parse($startDate)->toDateString();
                $endDate = \Carbon\Carbon::parse($endDate)->toDateString();
            } catch (\Exception $e) {
                return $this->error('Geçersiz tarih formatı', 422);
            }

            // Toplam personel sayıları
            $totalEmployees = Employee::where('company_id', $companyId)->count();
            $activeEmployees = Employee::where('company_id', $companyId)->where('status', 'active')->count();
            $onLeaveEmployees = Employee::where('company_id', $companyId)->where('status', 'on_leave')->count();
            $terminatedEmployees = Employee::where('company_id', $companyId)->where('status', 'terminated')->count();

            // Departmanlara göre dağılım - LEFT JOIN kullan (null department_id'ler için)
            $byDepartment = Employee::where('employees.company_id', $companyId)
                ->where('employees.status', 'active')
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->selectRaw('COALESCE(departments.name, "Departman Atanmamış") as name, count(*) as count')
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('count')
                ->get()
                ->map(function ($item) {
                    return ['name' => $item->name, 'count' => (int) $item->count];
                })
                ->toArray();

            // Sözleşme tiplerine göre dağılım
            $byContractType = Employee::where('company_id', $companyId)
                ->where('status', 'active')
                ->whereNotNull('contract_type')
                ->selectRaw('contract_type as type, count(*) as count')
                ->groupBy('contract_type')
                ->get()
                ->map(function ($item) {
                    return ['type' => $item->type, 'count' => (int) $item->count];
                })
                ->toArray();

            // Cinsiyete göre dağılım
            $byGender = Employee::where('company_id', $companyId)
                ->where('status', 'active')
                ->selectRaw('COALESCE(gender, "unspecified") as gender, count(*) as count')
                ->groupBy('gender')
                ->get()
                ->map(function ($item) {
                    return ['gender' => $item->gender, 'count' => (int) $item->count];
                })
                ->toArray();

            // Yaş gruplarına göre dağılım
            $byAgeGroup = Employee::where('company_id', $companyId)
                ->where('status', 'active')
                ->whereNotNull('birth_date')
                ->selectRaw('
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 25 THEN "18-24"
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 35 THEN "25-34"
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 45 THEN "35-44"
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 55 THEN "45-54"
                        ELSE "55+"
                    END as `group`,
                    count(*) as count
                ')
                ->groupBy('group')
                ->get()
                ->map(function ($item) {
                    return ['name' => $item->group, 'count' => (int) $item->count];
                })
                ->toArray();

            // Kıdeme göre dağılım
            $bySeniority = Employee::where('company_id', $companyId)
                ->where('status', 'active')
                ->whereNotNull('hire_date')
                ->selectRaw('
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 1 THEN "0-1 Yıl"
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 3 THEN "1-3 Yıl"
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 5 THEN "3-5 Yıl"
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 10 THEN "5-10 Yıl"
                        ELSE "10+ Yıl"
                    END as `range`,
                    count(*) as count
                ')
                ->groupBy('range')
                ->get()
                ->map(function ($item) {
                    return ['range' => $item->range, 'count' => (int) $item->count];
                })
                ->toArray();

            // Son dönem işe alımlar - null kontrolü ile
            $recentHires = Employee::where('company_id', $companyId)
                ->whereNotNull('hire_date')
                ->whereBetween('hire_date', [$startDate, $endDate])
                ->count();

            // Son dönem işten ayrılanlar - null kontrolü ile
            $recentTerminations = Employee::where('company_id', $companyId)
                ->where('status', 'terminated')
                ->whereNotNull('termination_date')
                ->whereBetween('termination_date', [$startDate, $endDate])
                ->count();

            return $this->success([
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'on_leave_employees' => $onLeaveEmployees,
                'terminated_employees' => $terminatedEmployees,
                'by_department' => $byDepartment,
                'by_contract_type' => $byContractType,
                'by_gender' => $byGender,
                'by_age_group' => $byAgeGroup,
                'by_seniority' => $bySeniority,
                'recent_hires' => $recentHires,
                'recent_terminations' => $recentTerminations,
            ]);
        } catch (\Exception $e) {
            \Log::error('Employee stats error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return $this->error('İstatistikler yüklenirken bir hata oluştu: '.$e->getMessage(), 500);
        }
    }
}

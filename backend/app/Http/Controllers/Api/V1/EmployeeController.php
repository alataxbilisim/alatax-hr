<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EmployeeResource;
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
use App\Services\CustomFieldValidationService;
use App\Services\DataScopeService;
use App\Services\EmployeeImportService;
use App\Services\EmployeeSensitiveFieldService;
use App\Services\InvitationService;
use App\Services\LookupService;
use App\Services\OrganizationChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected EmployeeSensitiveFieldService $sensitiveFields,
        protected LookupService $lookups,
        protected CustomFieldValidationService $customFieldValidation,
        protected InvitationService $invitations,
        protected OrganizationChartService $organizationChart,
    ) {}

    /**
     * Personel listesi
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::with(['user', 'department', 'branch', 'manager.user'])
            ->where('company_id', $this->getCompanyId());

        $this->dataScope->scopeForEmployee($query, $request->user());

        // Arama — filled: boş string ile tüm kayıtları filtreleme
        if ($request->filled('search')) {
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
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('position')) {
            $query->where('position', 'like', "%{$request->position}%");
        }

        // Sıralama
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Sayfalama
        $perPage = $request->get('per_page', 15);
        $employees = $query->paginate($perPage);

        return $this->paginated(
            EmployeeResource::collection($employees->getCollection())->resolve(),
            'Personel listelendi',
            $employees
        );
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

        $this->authorize('view', $employee);

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

            $certificates = TrainingCertificate::whereHas('participant', function ($q) use ($employee) {
                $q->where('user_id', $employee->user_id);
            })
                ->with(['participant.session.training:id,title'])
                ->orderBy('issue_date', 'desc')
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
                ->whereNull('return_date')
                ->with('asset:id,name,asset_code,brand,model,status')
                ->orderBy('assigned_date', 'desc')
                ->get();

            $pastAssignments = AssetAssignment::where('user_id', $employee->user_id)
                ->whereNotNull('return_date')
                ->with('asset:id,name,asset_code,brand,model')
                ->orderBy('return_date', 'desc')
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

        // Activity log (son 20 kayıt) — FQCN + legacy basename uyumu
        $activityLog = ActivityLog::query()
            ->where('model_id', $employee->id)
            ->where(function ($q) {
                $q->where('model_type', Employee::class)
                    ->orWhere('model_type', class_basename(Employee::class));
            })
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // A9: maaş alanları response'ta varsa hassas okuma logu (normal okuma loglanmaz)
        if ($this->sensitiveFields->canViewSalary($request->user())) {
            ActivityLog::log('view_sensitive', $employee, 'maaş bilgisi görüntülendi');
        }

        return $this->success([
            'employee' => new EmployeeResource($employee),
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
        $this->authorize('create', Employee::class);

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
            'branch_id' => 'nullable|exists:branches,id',
            'title' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'manager_id' => 'nullable|exists:employees,id',

            // Kişisel bilgiler
            'birth_date' => 'nullable|date',
            'national_id' => 'nullable|string|max:20',
            'gender' => 'nullable|string|max:100',
            'marital_status' => 'nullable|string|max:100',
            'blood_type' => 'nullable|string|max:20',
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
            'contract_type' => 'nullable|string|max:100',
            'work_type' => 'nullable|string|max:100',

            // Maaş bilgileri
            'gross_salary' => 'nullable|numeric|min:0',
            'net_salary' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',

            // SGK
            'sgk_number' => 'nullable|string|max:20',
            'sgk_start_date' => 'nullable|date',

            // Durum — LookupEngine employee_status value (K-A)
            'status' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'custom_fields' => 'nullable|array',

            // Portal erişimi
            'create_portal_access' => 'boolean',
            'portal_email' => 'nullable|required_if:create_portal_access,true|email|unique:users,email',
            'portal_access_mode' => 'nullable|in:invite,set_password',
            'portal_password' => [
                'nullable',
                'required_if:portal_access_mode,set_password',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(LookupService::TYPE_EMPLOYEE_STATUS, $validated['status'] ?? null, $companyId, 'status');
        $this->lookups->assertValid(LookupService::TYPE_WORK_TYPE, $validated['work_type'] ?? null, $companyId, 'work_type');
        $this->lookups->assertValid(LookupService::TYPE_CURRENCY, $validated['currency'] ?? null, $companyId, 'currency');
        $this->lookups->assertValid(LookupService::TYPE_GENDER, $validated['gender'] ?? null, $companyId, 'gender');
        $this->lookups->assertValid(LookupService::TYPE_MARITAL_STATUS, $validated['marital_status'] ?? null, $companyId, 'marital_status');
        $this->lookups->assertValid(LookupService::TYPE_BLOOD_TYPE, $validated['blood_type'] ?? null, $companyId, 'blood_type');
        $this->lookups->assertValid(LookupService::TYPE_EDUCATION_LEVEL, $validated['education_level'] ?? null, $companyId, 'education_level');
        $this->lookups->assertValid(LookupService::TYPE_EMERGENCY_RELATION, $validated['emergency_contact_relation'] ?? null, $companyId, 'emergency_contact_relation');
        $this->lookups->assertValid(LookupService::TYPE_CONTRACT_TYPE, $validated['contract_type'] ?? null, $companyId, 'contract_type');
        $this->customFieldValidation->validate(
            CustomFieldDefinition::ENTITY_EMPLOYEE,
            $validated['custom_fields'] ?? null
        );

        $strip = $this->sensitiveFields->stripUnauthorizedWrite($request->user(), $validated);
        $validated = $strip['data'];
        if ($strip['stripped'] !== []) {
            ActivityLog::log(
                'update',
                null,
                'yetkisiz alan güncellemesi yok sayıldı: '.implode(', ', $strip['stripped'])
            );
        }

        DB::beginTransaction();
        try {
            // Personel kaydı oluştur
            $employee = Employee::create([
                'company_id' => $this->getCompanyId(),
                'employee_code' => $validated['employee_code'],
                'department_id' => $validated['department_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
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
                $mode = $validated['portal_access_mode'] ?? 'invite';
                $user = $this->provisionPortalUser(
                    $employee,
                    $validated['portal_email'],
                    $validated['name'],
                    $mode,
                    $validated['portal_password'] ?? null
                );
                $employee->update(['user_id' => $user->id]);
            }

            // Observer Auditable create log yazar — manuel CRUD log yok

            DB::commit();

            return $this->created(
                new EmployeeResource($employee->load('user', 'department', 'branch', 'manager')),
                'Personel başarıyla oluşturuldu'
            );
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

        $this->authorize('update', $employee);

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
            'branch_id' => 'nullable|exists:branches,id',
            'title' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'manager_id' => 'nullable|exists:employees,id',
            'birth_date' => 'nullable|date',
            'national_id' => 'nullable|string|max:20',
            'gender' => 'nullable|string|max:100',
            'marital_status' => 'nullable|string|max:100',
            'blood_type' => 'nullable|string|max:20',
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
            'contract_type' => 'nullable|string|max:100',
            'work_type' => 'nullable|string|max:100',
            'gross_salary' => 'nullable|numeric|min:0',
            'net_salary' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',
            'sgk_number' => 'nullable|string|max:20',
            'sgk_start_date' => 'nullable|date',
            'status' => 'nullable|string|max:100',
            'termination_date' => 'nullable|date',
            'termination_reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'custom_fields' => 'nullable|array',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(LookupService::TYPE_EMPLOYEE_STATUS, $validated['status'] ?? null, $companyId, 'status');
        $this->lookups->assertValid(LookupService::TYPE_WORK_TYPE, $validated['work_type'] ?? null, $companyId, 'work_type');
        $this->lookups->assertValid(LookupService::TYPE_CURRENCY, $validated['currency'] ?? null, $companyId, 'currency');
        $this->lookups->assertValid(LookupService::TYPE_GENDER, $validated['gender'] ?? null, $companyId, 'gender');
        $this->lookups->assertValid(LookupService::TYPE_MARITAL_STATUS, $validated['marital_status'] ?? null, $companyId, 'marital_status');
        $this->lookups->assertValid(LookupService::TYPE_BLOOD_TYPE, $validated['blood_type'] ?? null, $companyId, 'blood_type');
        $this->lookups->assertValid(LookupService::TYPE_EDUCATION_LEVEL, $validated['education_level'] ?? null, $companyId, 'education_level');
        $this->lookups->assertValid(LookupService::TYPE_EMERGENCY_RELATION, $validated['emergency_contact_relation'] ?? null, $companyId, 'emergency_contact_relation');
        $this->lookups->assertValid(LookupService::TYPE_CONTRACT_TYPE, $validated['contract_type'] ?? null, $companyId, 'contract_type');
        if (array_key_exists('custom_fields', $validated)) {
            $this->customFieldValidation->validate(
                CustomFieldDefinition::ENTITY_EMPLOYEE,
                $validated['custom_fields']
            );
        }

        $strip = $this->sensitiveFields->stripUnauthorizedWrite($request->user(), $validated);
        $validated = $strip['data'];
        if ($strip['stripped'] !== []) {
            ActivityLog::log(
                'update',
                $employee,
                'yetkisiz alan güncellemesi yok sayıldı: '.implode(', ', $strip['stripped'])
            );
        }

        $employee->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        // Observer Auditable update log yazar — manuel CRUD log yok

        return $this->success(
            new EmployeeResource($employee->load('user', 'department', 'branch', 'manager')),
            'Personel başarıyla güncellendi'
        );
    }

    /**
     * Personel sil
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        $this->authorize('delete', $employee);

        // Observer Auditable delete log yazar — manuel CRUD log yok
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
            'access_mode' => 'nullable|in:invite,set_password',
            'password' => [
                'nullable',
                'required_if:access_mode,set_password',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        DB::beginTransaction();
        try {
            $mode = $validated['access_mode'] ?? 'invite';
            $user = $this->provisionPortalUser(
                $employee,
                $validated['email'],
                $validated['name'],
                $mode,
                $validated['password'] ?? null
            );

            $employee->update(['user_id' => $user->id]);

            ActivityLog::log('update', $employee, 'Personele portal erişimi verildi');

            DB::commit();

            return $this->success([
                'employee' => new EmployeeResource($employee->load('user')),
                'access_mode' => $mode,
                'invitation_sent' => $mode === 'invite',
                'must_change_password' => $mode === 'set_password',
            ], $mode === 'invite'
                ? 'Portal daveti gönderildi'
                : 'Portal erişimi oluşturuldu (şifre değiştirme zorunlu)');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Portal erişimi oluşturulurken hata oluştu: '.$e->getMessage(), 500);
        }
    }

    /**
     * Portal kullanıcısı oluştur (davet veya anlık şifre).
     */
    private function provisionPortalUser(
        Employee $employee,
        string $email,
        string $name,
        string $mode,
        ?string $password
    ): User {
        if ($mode === 'set_password') {
            $user = User::create([
                'company_id' => $this->getCompanyId(),
                'name' => $name,
                'email' => $email,
                'password' => Hash::make((string) $password),
                'type' => 'user',
                'is_active' => true,
                'must_change_password' => true,
                'created_by' => auth()->id(),
            ]);
            $user->assignRole('employee');

            return $user;
        }

        $issue = $this->invitations->issue();

        $user = User::create([
            'company_id' => $this->getCompanyId(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'type' => 'user',
            'is_active' => false,
            'must_change_password' => false,
            'invitation_token' => $issue['hash'],
            'invited_at' => $issue['invited_at'],
            'created_by' => auth()->id(),
        ]);
        $user->assignRole('employee');

        Mail::to($user->email)->queue(
            new EmployeeInvitation($user->load('company'), $employee, null, $issue['plain'])
        );

        return $user;
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

            return $this->success(new EmployeeResource($employee), 'Portal erişimi başarıyla kaldırıldı');
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
            'data.status' => 'nullable|string|max:100',
            'data.department_id' => 'nullable|exists:departments,id',
        ]);

        $this->lookups->assertValid(
            LookupService::TYPE_EMPLOYEE_STATUS,
            $validated['data']['status'] ?? null,
            $this->getCompanyId(),
            'data.status'
        );

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
                // Observer Auditable update log yazar
                $employee->update($updateData);
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
                // Observer Auditable delete log yazar
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

        $this->authorize('view', $employee);

        $activities = ActivityLog::query()
            ->where('model_id', $employee->id)
            ->where(function ($q) {
                $q->where('model_type', Employee::class)
                    ->orWhere('model_type', class_basename(Employee::class));
            })
            ->with('user:id,name')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->paginated(
            $activities->getCollection()->values()->all(),
            'Personel geçmişi listelendi',
            $activities
        );
    }

    /**
     * Organizasyon şeması — mode: people | department | hybrid
     */
    public function getOrganizationChart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['sometimes', 'string', Rule::in(OrganizationChartService::MODES)],
        ]);

        $mode = $validated['mode'] ?? OrganizationChartService::MODE_PEOPLE;
        $companyId = $this->getCompanyId();

        $tree = $this->organizationChart->build((int) $companyId, $mode);

        return $this->success([
            'mode' => $mode,
            'tree' => $tree,
        ]);
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

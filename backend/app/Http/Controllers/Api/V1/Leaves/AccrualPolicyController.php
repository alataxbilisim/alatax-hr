<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\AccrualPolicy;
use App\Models\AccrualLog;
use App\Models\ActivityLog;
use App\Services\LeaveCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccrualPolicyController extends BaseController
{
    protected LeaveCalculationService $leaveService;

    public function __construct(LeaveCalculationService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    /**
     * Hakediş politikaları listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccrualPolicy::where('company_id', $this->getCompanyId())
            ->with('leaveType');

        if ($request->has('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $policies = $query->orderBy('name')->get();

        return $this->success($policies, 'Hakediş politikaları listelendi');
    }

    /**
     * Politika detayı
     */
    public function show(int $id): JsonResponse
    {
        $policy = AccrualPolicy::where('company_id', $this->getCompanyId())
            ->with('leaveType')
            ->findOrFail($id);

        return $this->success($policy, 'Politika detayı');
    }

    /**
     * Yeni politika oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'accrual_type' => 'required|string|in:annual,monthly,per_pay_period,hourly,custom',
            'accrual_rate' => 'required|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'min_balance' => 'nullable|numeric',
            'tenure_rules' => 'nullable|array',
            'tenure_rules.*.years' => 'required_with:tenure_rules|integer|min:0',
            'tenure_rules.*.days' => 'required_with:tenure_rules|numeric|min:0',
            'allow_carryover' => 'boolean',
            'max_carryover_days' => 'nullable|numeric|min:0',
            'carryover_expiry_date' => 'nullable|date',
            'allow_encashment' => 'boolean',
            'max_encashment_days' => 'nullable|numeric|min:0',
            'encashment_rate' => 'nullable|numeric|min:0|max:2',
            'waiting_period_days' => 'nullable|integer|min:0',
            'prorate_first_year' => 'boolean',
        ]);

        $policy = AccrualPolicy::create([
            'company_id' => $this->getCompanyId(),
            'leave_type_id' => $validated['leave_type_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'accrual_type' => $validated['accrual_type'],
            'accrual_rate' => $validated['accrual_rate'],
            'max_balance' => $validated['max_balance'] ?? null,
            'min_balance' => $validated['min_balance'] ?? 0,
            'tenure_rules' => $validated['tenure_rules'] ?? null,
            'allow_carryover' => $validated['allow_carryover'] ?? true,
            'max_carryover_days' => $validated['max_carryover_days'] ?? null,
            'carryover_expiry_date' => $validated['carryover_expiry_date'] ?? null,
            'allow_encashment' => $validated['allow_encashment'] ?? false,
            'max_encashment_days' => $validated['max_encashment_days'] ?? null,
            'encashment_rate' => $validated['encashment_rate'] ?? 1,
            'waiting_period_days' => $validated['waiting_period_days'] ?? 0,
            'prorate_first_year' => $validated['prorate_first_year'] ?? true,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $policy, 'Yeni hakediş politikası oluşturuldu: ' . $policy->name);

        return $this->created($policy->load('leaveType'), 'Hakediş politikası oluşturuldu');
    }

    /**
     * Politika güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $policy = AccrualPolicy::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'accrual_type' => 'sometimes|required|string|in:annual,monthly,per_pay_period,hourly,custom',
            'accrual_rate' => 'sometimes|required|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'min_balance' => 'nullable|numeric',
            'tenure_rules' => 'nullable|array',
            'allow_carryover' => 'boolean',
            'max_carryover_days' => 'nullable|numeric|min:0',
            'carryover_expiry_date' => 'nullable|date',
            'allow_encashment' => 'boolean',
            'max_encashment_days' => 'nullable|numeric|min:0',
            'encashment_rate' => 'nullable|numeric|min:0|max:2',
            'waiting_period_days' => 'nullable|integer|min:0',
            'prorate_first_year' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $oldValues = $policy->toArray();
        $policy->update(array_merge($validated, ['updated_by' => auth()->id()]));

        ActivityLog::log('update', $policy, 'Hakediş politikası güncellendi', $oldValues, $policy->toArray());

        return $this->success($policy->load('leaveType'), 'Politika güncellendi');
    }

    /**
     * Politika sil
     */
    public function destroy(int $id): JsonResponse
    {
        $policy = AccrualPolicy::where('company_id', $this->getCompanyId())->findOrFail($id);

        // Kullanımda mı kontrol et
        if ($policy->logs()->exists()) {
            return $this->error('Bu politika kullanımda olduğu için silinemez', 400);
        }

        $policy->delete();

        ActivityLog::log('delete', $policy, 'Hakediş politikası silindi: ' . $policy->name);

        return $this->success(null, 'Politika silindi');
    }

    /**
     * Hakediş tiplerini getir
     */
    public function getAccrualTypes(): JsonResponse
    {
        return $this->success(AccrualPolicy::getAccrualTypes(), 'Hakediş tipleri');
    }

    /**
     * Bir kullanıcının hakediş loglarını getir
     */
    public function getUserAccrualLogs(Request $request, int $userId): JsonResponse
    {
        $query = AccrualLog::where('company_id', $this->getCompanyId())
            ->where('user_id', $userId)
            ->with(['leaveType', 'accrualPolicy']);

        if ($request->has('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $logs = $query->orderBy('effective_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return $this->paginated($logs, 'Hakediş logları listelendi');
    }

    /**
     * Aylık hakediş işlemini tetikle (Admin)
     */
    public function processMonthlyAccruals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2050',
        ]);

        $results = $this->leaveService->processMonthlyAccruals(
            $this->getCompanyId(),
            $validated['month'],
            $validated['year']
        );

        return $this->success([
            'processed_count' => count($results),
            'details' => $results,
        ], 'Aylık hakediş işlendi');
    }

    /**
     * Yıl sonu devir işlemini tetikle (Admin)
     */
    public function processYearEndCarryover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2050',
        ]);

        $results = $this->leaveService->processYearEndCarryover(
            $this->getCompanyId(),
            $validated['year']
        );

        return $this->success([
            'processed_count' => count($results),
            'details' => $results,
        ], 'Yıl sonu devir işlendi');
    }

    /**
     * Log tiplerini getir
     */
    public function getLogTypes(): JsonResponse
    {
        return $this->success(AccrualLog::getTypeLabels(), 'Log tipleri');
    }
}



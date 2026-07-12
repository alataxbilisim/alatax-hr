<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Holiday;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
    ) {}

    /**
     * Tatil listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::forCompany($this->getCompanyId())
            ->active();

        if ($request->has('year')) {
            $query->forYear($request->year);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $holidays = $query->orderBy('date')->get();

        return $this->success($holidays, 'Tatiller listelendi');
    }

    /**
     * Tatil detayı
     */
    public function show(int $id): JsonResponse
    {
        $holiday = Holiday::forCompany($this->getCompanyId())->findOrFail($id);

        return $this->success($holiday, 'Tatil detayı');
    }

    /**
     * Yeni tatil ekle (sadece şirket tatilleri)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:date',
            'type' => 'required|string|max:100',
            'is_recurring' => 'boolean',
            'is_half_day' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_HOLIDAY_TYPE,
            $validated['type'],
            $companyId,
            'type'
        );
        if (! in_array($validated['type'], ['company', 'regional'], true)) {
            return $this->error('Firma tatilleri yalnızca şirket veya bölgesel olabilir', 422);
        }

        $holiday = Holiday::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'date' => $validated['date'],
            'end_date' => $validated['end_date'] ?? null,
            'type' => $validated['type'],
            'country_code' => 'TR',
            'is_recurring' => $validated['is_recurring'] ?? false,
            'is_half_day' => $validated['is_half_day'] ?? false,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $holiday, 'Yeni tatil eklendi: '.$holiday->name);

        return $this->created($holiday, 'Tatil eklendi');
    }

    /**
     * Tatil güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $holiday = Holiday::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:date',
            'type' => 'sometimes|required|string|max:100',
            'is_recurring' => 'boolean',
            'is_half_day' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (array_key_exists('type', $validated)) {
            $this->lookups->assertValid(
                LookupService::TYPE_HOLIDAY_TYPE,
                $validated['type'],
                $this->getCompanyId(),
                'type'
            );
        }

        $oldValues = $holiday->toArray();
        $holiday->update(array_merge($validated, ['updated_by' => auth()->id()]));

        ActivityLog::log('update', $holiday, 'Tatil güncellendi', $oldValues, $holiday->toArray());

        return $this->success($holiday, 'Tatil güncellendi');
    }

    /**
     * Tatil sil
     */
    public function destroy(int $id): JsonResponse
    {
        $holiday = Holiday::where('company_id', $this->getCompanyId())->findOrFail($id);

        $holiday->delete();

        ActivityLog::log('delete', $holiday, 'Tatil silindi: '.$holiday->name);

        return $this->success(null, 'Tatil silindi');
    }

    /**
     * Tatil tiplerini getir
     */
    public function getTypes(): JsonResponse
    {
        return $this->success(Holiday::getTypeLabels(), 'Tatil tipleri');
    }

    /**
     * Belirli bir yıl için Türkiye resmi tatillerini seed et
     */
    public function seedNationalHolidays(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin()) {
            return $this->error('Bu işlem için yetkiniz yok', 403);
        }

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2050',
        ]);

        Holiday::seedTurkishHolidays($validated['year']);

        return $this->success(null, 'Resmi tatiller eklendi');
    }

    /**
     * Belirli tarih aralığındaki tatilleri getir
     */
    public function getHolidaysInRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $holidays = Holiday::forCompany($this->getCompanyId())
            ->active()
            ->inDateRange($validated['start_date'], $validated['end_date'])
            ->orderBy('date')
            ->get();

        return $this->success($holidays, 'Tarih aralığındaki tatiller');
    }

    /**
     * Bir tarihin tatil olup olmadığını kontrol et
     */
    public function checkDate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $isHoliday = Holiday::isHoliday(
            \Carbon\Carbon::parse($validated['date']),
            $this->getCompanyId()
        );

        $holiday = null;
        if ($isHoliday) {
            $holiday = Holiday::forCompany($this->getCompanyId())
                ->active()
                ->whereDate('date', '<=', $validated['date'])
                ->where(function ($q) use ($validated) {
                    $q->whereDate('date', $validated['date'])
                        ->orWhere(function ($q2) use ($validated) {
                            $q2->whereNotNull('end_date')
                                ->whereDate('end_date', '>=', $validated['date']);
                        });
                })
                ->first();
        }

        return $this->success([
            'is_holiday' => $isHoliday,
            'holiday' => $holiday,
        ], 'Tarih kontrol sonucu');
    }
}

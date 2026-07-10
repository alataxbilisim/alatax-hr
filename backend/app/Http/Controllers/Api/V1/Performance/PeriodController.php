<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\PerformancePeriod;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodController extends BaseController
{
    /**
     * Dönem listesi
     */
    public function index(Request $request): JsonResponse
    {
        $periods = PerformancePeriod::where('company_id', $this->getCompanyId())
            ->withCount('reviews')
            ->orderBy('start_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($periods, 'Performans dönemleri listelendi');
    }

    /**
     * Yeni dönem oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string',
        ]);

        $period = PerformancePeriod::create([
            ...$validated,
            'company_id' => $this->getCompanyId(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $period, 'Performans dönemi oluşturuldu: ' . $period->name);

        return $this->success($period, 'Performans dönemi oluşturuldu', 201);
    }

    /**
     * Dönem detayı
     */
    public function show(int $id): JsonResponse
    {
        $period = PerformancePeriod::where('company_id', $this->getCompanyId())
            ->withCount('reviews')
            ->findOrFail($id);

        return $this->success($period);
    }

    /**
     * Dönem güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $period = PerformancePeriod::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'status' => 'sometimes|in:draft,active,closed',
            'description' => 'nullable|string',
        ]);

        $oldValues = $period->getOriginal();
        $period->update($validated);

        ActivityLog::log('update', $period, 'Performans dönemi güncellendi: ' . $period->name, $oldValues, $period->fresh()->toArray());

        return $this->success($period, 'Dönem güncellendi');
    }

    /**
     * Dönem sil
     */
    public function destroy(int $id): JsonResponse
    {
        $period = PerformancePeriod::where('company_id', $this->getCompanyId())->findOrFail($id);
        
        if ($period->reviews()->exists()) {
            return $this->error('Bu döneme ait değerlendirmeler var, silinemez.', 422);
        }

        $periodName = $period->name;
        ActivityLog::log('delete', null, 'Performans dönemi silindi: ' . $periodName);
        
        $period->delete();

        return $this->success(null, 'Dönem silindi');
    }

    /**
     * Dönemi aktifleştir
     */
    public function activate(int $id): JsonResponse
    {
        $period = PerformancePeriod::where('company_id', $this->getCompanyId())->findOrFail($id);
        $period->update(['status' => 'active']);

        ActivityLog::log('update', $period, 'Dönem aktifleştirildi: ' . $period->name);

        return $this->success($period, 'Dönem aktifleştirildi');
    }

    /**
     * Dönemi kapat
     */
    public function close(int $id): JsonResponse
    {
        $period = PerformancePeriod::where('company_id', $this->getCompanyId())->findOrFail($id);
        $period->update(['status' => 'closed']);

        ActivityLog::log('update', $period, 'Dönem kapatıldı: ' . $period->name);

        return $this->success($period, 'Dönem kapatıldı');
    }
}


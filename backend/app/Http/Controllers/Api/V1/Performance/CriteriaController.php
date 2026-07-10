<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\PerformanceCriteria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CriteriaController extends BaseController
{
    /**
     * Kriter listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = PerformanceCriteria::where('company_id', $this->getCompanyId())
            ->ordered();

        if ($request->has('active_only')) {
            $query->active();
        }

        $criteria = $query->get();

        return $this->success($criteria, 'Performans kriterleri listelendi');
    }

    /**
     * Yeni kriter oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'weight' => 'required|integer|min:1|max:100',
            'max_score' => 'required|integer|min:1|max:10',
            'sort_order' => 'nullable|integer',
        ]);

        $criteria = PerformanceCriteria::create([
            ...$validated,
            'company_id' => $this->getCompanyId(),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $criteria, 'Performans kriteri oluşturuldu: '.$criteria->name);

        return $this->success($criteria, 'Kriter oluşturuldu', 201);
    }

    /**
     * Kriter detayı
     */
    public function show(int $id): JsonResponse
    {
        $criteria = PerformanceCriteria::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        return $this->success($criteria);
    }

    /**
     * Kriter güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $criteria = PerformanceCriteria::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'weight' => 'sometimes|required|integer|min:1|max:100',
            'max_score' => 'sometimes|required|integer|min:1|max:10',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $oldValues = $criteria->getOriginal();
        $criteria->update($validated);

        ActivityLog::log('update', $criteria, 'Performans kriteri güncellendi: '.$criteria->name, $oldValues, $criteria->fresh()->toArray());

        return $this->success($criteria, 'Kriter güncellendi');
    }

    /**
     * Kriter sil
     */
    public function destroy(int $id): JsonResponse
    {
        $criteria = PerformanceCriteria::where('company_id', $this->getCompanyId())->findOrFail($id);

        if ($criteria->scores()->exists()) {
            return $this->error('Bu kritere ait puanlamalar var, silinemez. Pasif yapabilirsiniz.', 422);
        }

        $criteriaName = $criteria->name;
        ActivityLog::log('delete', null, 'Performans kriteri silindi: '.$criteriaName);

        $criteria->delete();

        return $this->success(null, 'Kriter silindi');
    }
}

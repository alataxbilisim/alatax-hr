<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\KeyResult;
use App\Models\Objective;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OkrController extends BaseController
{
    /**
     * Hedef listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Objective::where('company_id', $this->getCompanyId())
            ->with(['owner:id,name', 'keyResults', 'parent:id,title', 'department:id,name']);

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->has('period_id')) {
            $query->where('performance_period_id', $request->period_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            // Sadece üst düzey hedefleri getir
            if (! $request->has('all')) {
                $query->whereNull('parent_id');
            }
        }

        $objectives = $query->orderBy('level')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($objectives, 'Hedefler listelendi');
    }

    /**
     * Hedef detayı
     */
    public function show(int $id): JsonResponse
    {
        $objective = Objective::where('company_id', $this->getCompanyId())
            ->with([
                'owner:id,name,email',
                'keyResults.owner:id,name',
                'keyResults.updates' => fn ($q) => $q->latest()->limit(5),
                'children.keyResults',
                'parent:id,title',
                'department:id,name',
            ])
            ->findOrFail($id);

        return $this->success($objective, 'Hedef detayı');
    }

    /**
     * Yeni hedef oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'performance_period_id' => 'nullable|exists:performance_periods,id',
            'parent_id' => 'nullable|exists:objectives,id',
            'level' => 'required|in:company,department,team,individual',
            'department_id' => 'nullable|exists:departments,id',
            'owner_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'key_results' => 'nullable|array',
            'key_results.*.title' => 'required|string|max:255',
            'key_results.*.metric_type' => 'required|in:number,percentage,currency,boolean,milestone',
            'key_results.*.start_value' => 'required|numeric',
            'key_results.*.target_value' => 'required|numeric',
            'key_results.*.weight' => 'nullable|numeric|min:0|max:100',
            'key_results.*.due_date' => 'nullable|date',
        ]);

        DB::beginTransaction();
        try {
            $objective = Objective::create([
                'company_id' => $this->getCompanyId(),
                'performance_period_id' => $validated['performance_period_id'] ?? null,
                'parent_id' => $validated['parent_id'] ?? null,
                'level' => $validated['level'],
                'department_id' => $validated['department_id'] ?? null,
                'owner_id' => $validated['owner_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'weight' => $validated['weight'] ?? 100,
                'status' => Objective::STATUS_DRAFT,
                'progress' => 0,
                'created_by' => auth()->id(),
            ]);

            // Key Results oluştur
            if (! empty($validated['key_results'])) {
                foreach ($validated['key_results'] as $krData) {
                    KeyResult::create([
                        'objective_id' => $objective->id,
                        'owner_id' => $validated['owner_id'],
                        'title' => $krData['title'],
                        'metric_type' => $krData['metric_type'],
                        'start_value' => $krData['start_value'],
                        'target_value' => $krData['target_value'],
                        'current_value' => $krData['start_value'],
                        'weight' => $krData['weight'] ?? 100,
                        'due_date' => $krData['due_date'] ?? $validated['end_date'],
                        'status' => KeyResult::STATUS_NOT_STARTED,
                        'progress' => 0,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            ActivityLog::log('create', $objective, 'Yeni hedef oluşturuldu: '.$objective->title);

            return $this->created($objective->load('keyResults'), 'Hedef oluşturuldu');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Hedef oluşturulamadı: '.$e->getMessage(), 500);
        }
    }

    /**
     * Hedef güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $objective = Objective::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:draft,active,completed,cancelled',
        ]);

        $oldValues = $objective->toArray();
        $objective->update(array_merge($validated, ['updated_by' => auth()->id()]));

        ActivityLog::log('update', $objective, 'Hedef güncellendi', $oldValues, $objective->toArray());

        return $this->success($objective->load('keyResults'), 'Hedef güncellendi');
    }

    /**
     * Hedef sil
     */
    public function destroy(int $id): JsonResponse
    {
        $objective = Objective::where('company_id', $this->getCompanyId())->findOrFail($id);

        // Alt hedefler varsa silinemez
        if ($objective->children()->exists()) {
            return $this->error('Alt hedefleri olan bir hedef silinemez', 400);
        }

        $objective->delete();

        ActivityLog::log('delete', $objective, 'Hedef silindi: '.$objective->title);

        return $this->success(null, 'Hedef silindi');
    }

    /**
     * Hedefi aktifleştir
     */
    public function activate(int $id): JsonResponse
    {
        $objective = Objective::where('company_id', $this->getCompanyId())->findOrFail($id);

        if ($objective->keyResults()->count() == 0) {
            return $this->error('En az bir anahtar sonuç eklemelisiniz', 400);
        }

        $objective->update(['status' => Objective::STATUS_ACTIVE]);

        ActivityLog::log('update', $objective, 'Hedef aktifleştirildi');

        return $this->success($objective, 'Hedef aktifleştirildi');
    }

    /**
     * Key Result ekle
     */
    public function addKeyResult(Request $request, int $objectiveId): JsonResponse
    {
        $objective = Objective::where('company_id', $this->getCompanyId())->findOrFail($objectiveId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'metric_type' => 'required|in:number,percentage,currency,boolean,milestone',
            'start_value' => 'required|numeric',
            'target_value' => 'required|numeric',
            'weight' => 'nullable|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $keyResult = KeyResult::create([
            'objective_id' => $objective->id,
            'owner_id' => $validated['owner_id'] ?? $objective->owner_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'metric_type' => $validated['metric_type'],
            'start_value' => $validated['start_value'],
            'target_value' => $validated['target_value'],
            'current_value' => $validated['start_value'],
            'weight' => $validated['weight'] ?? 100,
            'due_date' => $validated['due_date'] ?? $objective->end_date,
            'status' => KeyResult::STATUS_NOT_STARTED,
            'progress' => 0,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $keyResult, 'Anahtar sonuç eklendi: '.$keyResult->title);

        return $this->created($keyResult, 'Anahtar sonuç eklendi');
    }

    /**
     * Key Result güncelle (check-in)
     */
    public function updateKeyResult(Request $request, int $keyResultId): JsonResponse
    {
        $keyResult = KeyResult::whereHas('objective', fn ($q) => $q->where('company_id', $this->getCompanyId()))
            ->findOrFail($keyResultId);

        $validated = $request->validate([
            'current_value' => 'required|numeric',
            'note' => 'nullable|string',
            'confidence' => 'nullable|in:low,medium,high',
        ]);

        $keyResult->updateValue(
            $validated['current_value'],
            $validated['note'] ?? null,
            $validated['confidence'] ?? 'medium'
        );

        ActivityLog::log('update', $keyResult, 'Anahtar sonuç güncellendi');

        return $this->success($keyResult->fresh(['updates', 'objective']), 'Anahtar sonuç güncellendi');
    }

    /**
     * Key Result sil
     */
    public function deleteKeyResult(int $keyResultId): JsonResponse
    {
        $keyResult = KeyResult::whereHas('objective', fn ($q) => $q->where('company_id', $this->getCompanyId()))
            ->findOrFail($keyResultId);

        $objective = $keyResult->objective;
        $keyResult->delete();

        // Hedef ilerlemesini güncelle
        $objective->updateProgress();

        ActivityLog::log('delete', $keyResult, 'Anahtar sonuç silindi');

        return $this->success(null, 'Anahtar sonuç silindi');
    }

    /**
     * Seviye ve durum etiketlerini getir
     */
    public function getLabels(): JsonResponse
    {
        return $this->success([
            'levels' => Objective::getLevelLabels(),
            'statuses' => Objective::getStatusLabels(),
            'metric_types' => KeyResult::getMetricTypes(),
            'kr_statuses' => KeyResult::getStatusLabels(),
        ], 'Etiketler');
    }
}

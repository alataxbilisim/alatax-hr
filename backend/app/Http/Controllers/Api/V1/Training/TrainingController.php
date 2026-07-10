<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends BaseController
{
    /**
     * Eğitim listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Training::where('company_id', $this->getCompanyId())
            ->withCount('sessions');

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('mandatory_only')) {
            $query->mandatory();
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $trainings = $query->orderBy('title')
            ->paginate($request->get('per_page', 15));

        return $this->success($trainings, 'Eğitimler listelendi');
    }

    /**
     * Yeni eğitim oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'type' => 'required|in:online,classroom,hybrid',
            'instructor' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'cost' => 'nullable|numeric|min:0',
            'is_mandatory' => 'boolean',
        ]);

        $training = Training::create([
            ...$validated,
            'company_id' => $this->getCompanyId(),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $training, 'Eğitim oluşturuldu: '.$training->title);

        return $this->success($training, 'Eğitim oluşturuldu', 201);
    }

    /**
     * Eğitim detayı
     */
    public function show(int $id): JsonResponse
    {
        $training = Training::where('company_id', $this->getCompanyId())
            ->withCount('sessions')
            ->with(['sessions' => function ($query) {
                $query->withCount('participants')
                    ->orderBy('start_date', 'desc')
                    ->take(5);
            }])
            ->findOrFail($id);

        return $this->success($training);
    }

    /**
     * Eğitim güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $training = Training::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'type' => 'sometimes|in:online,classroom,hybrid',
            'instructor' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'cost' => 'nullable|numeric|min:0',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $oldValues = $training->getOriginal();
        $training->update($validated);

        ActivityLog::log('update', $training, 'Eğitim güncellendi: '.$training->title, $oldValues, $training->fresh()->toArray());

        return $this->success($training, 'Eğitim güncellendi');
    }

    /**
     * Eğitim sil
     */
    public function destroy(int $id): JsonResponse
    {
        $training = Training::where('company_id', $this->getCompanyId())->findOrFail($id);

        if ($training->sessions()->whereHas('participants')->exists()) {
            return $this->error('Bu eğitime kayıtlı katılımcılar var, silinemez.', 422);
        }

        $trainingTitle = $training->title;
        ActivityLog::log('delete', null, 'Eğitim silindi: '.$trainingTitle);

        $training->delete();

        return $this->success(null, 'Eğitim silindi');
    }

    /**
     * Kategorileri listele
     */
    public function categories(): JsonResponse
    {
        $categories = Training::where('company_id', $this->getCompanyId())
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return $this->success($categories, 'Kategoriler listelendi');
    }
}

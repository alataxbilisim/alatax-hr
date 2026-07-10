<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Competency;
use App\Models\PositionCompetency;
use App\Models\UserCompetency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetencyController extends BaseController
{
    /**
     * Yetkinlik listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Competency::where('company_id', $this->getCompanyId());

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $competencies = $query->orderBy('category')->orderBy('name')->get();

        return $this->success($competencies, 'Yetkinlikler listelendi');
    }

    /**
     * Yetkinlik detayı
     */
    public function show(int $id): JsonResponse
    {
        $competency = Competency::where('company_id', $this->getCompanyId())
            ->with(['userCompetencies.user:id,name', 'positionCompetencies'])
            ->findOrFail($id);

        return $this->success($competency, 'Yetkinlik detayı');
    }

    /**
     * Yeni yetkinlik oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'levels' => 'nullable|array',
            'levels.*.level' => 'required|integer|min:1',
            'levels.*.name' => 'required|string',
            'levels.*.description' => 'nullable|string',
            'max_level' => 'nullable|integer|min:1|max:10',
        ]);

        // Aynı isimde yetkinlik var mı?
        if (Competency::where('company_id', $this->getCompanyId())
            ->where('name', $validated['name'])
            ->exists()) {
            return $this->error('Bu isimde bir yetkinlik zaten mevcut', 400);
        }

        $competency = Competency::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'levels' => $validated['levels'] ?? Competency::getDefaultLevels(),
            'max_level' => $validated['max_level'] ?? 5,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $competency, 'Yeni yetkinlik oluşturuldu: '.$competency->name);

        return $this->created($competency, 'Yetkinlik oluşturuldu');
    }

    /**
     * Yetkinlik güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $competency = Competency::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|required|string|max:100',
            'levels' => 'nullable|array',
            'max_level' => 'nullable|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        $oldValues = $competency->toArray();
        $competency->update(array_merge($validated, ['updated_by' => auth()->id()]));

        ActivityLog::log('update', $competency, 'Yetkinlik güncellendi', $oldValues, $competency->toArray());

        return $this->success($competency, 'Yetkinlik güncellendi');
    }

    /**
     * Yetkinlik sil
     */
    public function destroy(int $id): JsonResponse
    {
        $competency = Competency::where('company_id', $this->getCompanyId())->findOrFail($id);

        // Kullanımda mı kontrol et
        if ($competency->userCompetencies()->exists()) {
            return $this->error('Bu yetkinlik kullanımda olduğu için silinemez', 400);
        }

        $competency->delete();

        ActivityLog::log('delete', $competency, 'Yetkinlik silindi: '.$competency->name);

        return $this->success(null, 'Yetkinlik silindi');
    }

    /**
     * Kategorileri getir
     */
    public function getCategories(): JsonResponse
    {
        $categories = Competency::where('company_id', $this->getCompanyId())
            ->distinct()
            ->pluck('category');

        return $this->success([
            'categories' => $categories,
            'predefined' => Competency::getCategoryLabels(),
        ], 'Kategoriler');
    }

    // ===== Kullanıcı Yetkinlikleri =====

    /**
     * Kullanıcının yetkinliklerini getir
     */
    public function getUserCompetencies(int $userId): JsonResponse
    {
        $competencies = UserCompetency::where('company_id', $this->getCompanyId())
            ->where('user_id', $userId)
            ->with(['competency', 'assessedBy:id,name'])
            ->get();

        return $this->success($competencies, 'Kullanıcı yetkinlikleri');
    }

    /**
     * Kullanıcıya yetkinlik ata/güncelle
     */
    public function setUserCompetency(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'competency_id' => 'required|exists:competencies,id',
            'current_level' => 'required|integer|min:1',
            'target_level' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $competency = Competency::where('company_id', $this->getCompanyId())
            ->findOrFail($validated['competency_id']);

        if ($validated['current_level'] > $competency->max_level) {
            return $this->error('Seviye maksimum değeri aşamaz', 400);
        }

        $userCompetency = UserCompetency::updateOrCreate(
            [
                'company_id' => $this->getCompanyId(),
                'user_id' => $userId,
                'competency_id' => $validated['competency_id'],
            ],
            [
                'current_level' => $validated['current_level'],
                'target_level' => $validated['target_level'] ?? null,
                'assessed_at' => now(),
                'assessed_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        ActivityLog::log('update', $userCompetency, 'Kullanıcı yetkinliği güncellendi');

        return $this->success($userCompetency->load('competency'), 'Yetkinlik atandı');
    }

    /**
     * Skill Gap Analizi
     */
    public function getSkillGapAnalysis(int $userId, string $positionName): JsonResponse
    {
        // Pozisyon için beklenen yetkinlikler
        $positionCompetencies = PositionCompetency::where('company_id', $this->getCompanyId())
            ->where('position_name', $positionName)
            ->with('competency')
            ->get();

        // Kullanıcının mevcut yetkinlikleri
        $userCompetencies = UserCompetency::where('company_id', $this->getCompanyId())
            ->where('user_id', $userId)
            ->get()
            ->keyBy('competency_id');

        $analysis = [];
        foreach ($positionCompetencies as $pc) {
            $userComp = $userCompetencies->get($pc->competency_id);
            $currentLevel = $userComp?->current_level ?? 0;
            $gap = $pc->expected_level - $currentLevel;

            $analysis[] = [
                'competency' => $pc->competency,
                'expected_level' => $pc->expected_level,
                'current_level' => $currentLevel,
                'gap' => $gap,
                'is_required' => $pc->is_required,
                'status' => $gap <= 0 ? 'met' : ($gap <= 1 ? 'close' : 'gap'),
            ];
        }

        // Gap'e göre sırala (en büyük gap önce)
        usort($analysis, fn ($a, $b) => $b['gap'] - $a['gap']);

        return $this->success([
            'position' => $positionName,
            'user_id' => $userId,
            'analysis' => $analysis,
            'summary' => [
                'total_competencies' => count($analysis),
                'met' => count(array_filter($analysis, fn ($a) => $a['status'] === 'met')),
                'gaps' => count(array_filter($analysis, fn ($a) => $a['status'] === 'gap')),
            ],
        ], 'Skill Gap Analizi');
    }

    // ===== Pozisyon Yetkinlikleri =====

    /**
     * Pozisyon yetkinliklerini getir
     */
    public function getPositionCompetencies(string $positionName): JsonResponse
    {
        $competencies = PositionCompetency::where('company_id', $this->getCompanyId())
            ->where('position_name', $positionName)
            ->with('competency')
            ->get();

        return $this->success($competencies, 'Pozisyon yetkinlikleri');
    }

    /**
     * Pozisyona yetkinlik ata
     */
    public function setPositionCompetency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position_name' => 'required|string|max:255',
            'competency_id' => 'required|exists:competencies,id',
            'expected_level' => 'required|integer|min:1',
            'weight' => 'nullable|numeric|min:0|max:100',
            'is_required' => 'boolean',
        ]);

        $positionComp = PositionCompetency::updateOrCreate(
            [
                'company_id' => $this->getCompanyId(),
                'position_name' => $validated['position_name'],
                'competency_id' => $validated['competency_id'],
            ],
            [
                'expected_level' => $validated['expected_level'],
                'weight' => $validated['weight'] ?? 100,
                'is_required' => $validated['is_required'] ?? true,
            ]
        );

        ActivityLog::log('update', $positionComp, 'Pozisyon yetkinliği güncellendi');

        return $this->success($positionComp->load('competency'), 'Pozisyon yetkinliği atandı');
    }

    /**
     * Pozisyon yetkinliği kaldır
     */
    public function removePositionCompetency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position_name' => 'required|string',
            'competency_id' => 'required|exists:competencies,id',
        ]);

        PositionCompetency::where('company_id', $this->getCompanyId())
            ->where('position_name', $validated['position_name'])
            ->where('competency_id', $validated['competency_id'])
            ->delete();

        return $this->success(null, 'Pozisyon yetkinliği kaldırıldı');
    }
}

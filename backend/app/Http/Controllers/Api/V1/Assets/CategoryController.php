<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\AssetCategory;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    /**
     * Kategori listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssetCategory::where('company_id', $this->getCompanyId())
            ->withCount('assets')
            ->ordered();

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $categories = $query->get();

        return $this->success($categories, 'Kategoriler listelendi');
    }

    /**
     * Yeni kategori oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
        ]);

        $category = AssetCategory::create([
            ...$validated,
            'company_id' => $this->getCompanyId(),
            'is_active' => true,
        ]);

        ActivityLog::log('create', $category, 'Varlık kategorisi oluşturuldu: ' . $category->name);

        return $this->success($category, 'Kategori oluşturuldu', 201);
    }

    /**
     * Kategori detayı
     */
    public function show(int $id): JsonResponse
    {
        $category = AssetCategory::where('company_id', $this->getCompanyId())
            ->withCount('assets')
            ->findOrFail($id);

        return $this->success($category);
    }

    /**
     * Kategori güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = AssetCategory::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $oldValues = $category->getOriginal();
        $category->update($validated);

        ActivityLog::log('update', $category, 'Varlık kategorisi güncellendi: ' . $category->name, $oldValues, $category->fresh()->toArray());

        return $this->success($category, 'Kategori güncellendi');
    }

    /**
     * Kategori sil
     */
    public function destroy(int $id): JsonResponse
    {
        $category = AssetCategory::where('company_id', $this->getCompanyId())->findOrFail($id);
        
        if ($category->assets()->exists()) {
            return $this->error('Bu kategoride varlıklar var, silinemez.', 422);
        }

        $categoryName = $category->name;
        ActivityLog::log('delete', null, 'Varlık kategorisi silindi: ' . $categoryName);
        
        $category->delete();

        return $this->success(null, 'Kategori silindi');
    }
}


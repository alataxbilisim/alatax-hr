<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\DocumentCategory;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends BaseController
{
    /**
     * Kategori listesi
     */
    public function index(): JsonResponse
    {
        $categories = DocumentCategory::withCount('documents')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'documents_count' => $category->documents_count,
                ];
            });

        return $this->success($categories);
    }

    /**
     * Kategori oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        $category = DocumentCategory::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? 'folder',
            'color' => $validated['color'] ?? null,
        ]);

        ActivityLog::log('create', $category, 'Doküman kategorisi oluşturuldu: ' . $category->name);

        return $this->success($category, 'Kategori oluşturuldu', 201);
    }

    /**
     * Kategori güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = DocumentCategory::find($id);

        if (!$category) {
            return $this->notFound('Kategori bulunamadı');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $oldValues = $category->getOriginal();
        $category->update($validated);

        ActivityLog::log('update', $category, 'Doküman kategorisi güncellendi: ' . $category->name, $oldValues, $category->fresh()->toArray());

        return $this->success($category, 'Kategori güncellendi');
    }

    /**
     * Kategori sil
     */
    public function destroy(int $id): JsonResponse
    {
        $category = DocumentCategory::find($id);

        if (!$category) {
            return $this->notFound('Kategori bulunamadı');
        }

        // Kategorideki dokümanları kategorisiz yap
        $categoryName = $category->name;
        $category->documents()->update(['category_id' => null]);
        
        ActivityLog::log('delete', null, 'Doküman kategorisi silindi: ' . $categoryName);
        
        $category->delete();

        return $this->success(null, 'Kategori silindi');
    }
}

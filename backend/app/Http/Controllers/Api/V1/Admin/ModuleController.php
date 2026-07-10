<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends BaseController
{
    /**
     * Modül listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Module::withCount('companies')
            ->orderBy('sort_order');

        if ($request->has('is_core')) {
            $query->where('is_core', $request->boolean('is_core'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $modules = $query->get();

        return $this->success($modules, 'Modüller listelendi');
    }

    /**
     * Modül detay
     */
    public function show(Module $module): JsonResponse
    {
        $module->loadCount('companies');

        return $this->success($module);
    }

    /**
     * Modül oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:modules,slug',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'is_core' => 'required|boolean',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        $module = Module::create($validated);

        ActivityLog::log('create', $module, 'Modül oluşturuldu: '.$module->name);

        return $this->created($module, 'Modül oluşturuldu');
    }

    /**
     * Modül güncelle
     */
    public function update(Request $request, Module $module): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:modules,slug,'.$module->id,
            'description' => 'sometimes|nullable|string|max:1000',
            'icon' => 'sometimes|nullable|string|max:50',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $module->toArray();
        $module->update($validated);

        ActivityLog::log('update', $module, 'Modül güncellendi: '.$module->name, $oldValues, $module->fresh()->toArray());

        return $this->success($module->fresh(), 'Modül güncellendi');
    }

    /**
     * Modül sil
     */
    public function destroy(Module $module): JsonResponse
    {
        // Core modüller silinemez
        if ($module->is_core) {
            return $this->error('Core modüller silinemez', 403);
        }

        // Kullanan firma varsa silinemez
        if ($module->companies()->count() > 0) {
            return $this->error('Bu modülü kullanan firmalar var. Önce firmaların modüllerini kaldırın.', 400);
        }

        $moduleName = $module->name;
        $module->delete();

        ActivityLog::log('delete', null, 'Modül silindi: '.$moduleName);

        return $this->success(null, 'Modül silindi');
    }
}

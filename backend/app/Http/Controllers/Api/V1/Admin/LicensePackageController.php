<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\LicensePackage;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LicensePackageController extends BaseController
{
    /**
     * Paket listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = LicensePackage::with(['modules' => function ($q) {
            $q->wherePivot('is_included', true);
        }])
            ->withCount('companies');

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Durum filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sıralama
        $packages = $query->ordered()->get();

        return $this->success($packages, 'Lisans paketleri listelendi');
    }

    /**
     * Paket detayı
     */
    public function show(LicensePackage $package): JsonResponse
    {
        $package->load(['modules', 'companies' => function ($q) {
            $q->select('id', 'name', 'license_package_id', 'status')->limit(10);
        }]);
        $package->loadCount('companies');

        return $this->success($package, 'Paket detayları');
    }

    /**
     * Yeni paket oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:license_packages,name',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'annual_price' => 'nullable|numeric|min:0',
            'user_limit' => 'required|integer|min:0',
            'location_limit' => 'required|integer|min:0',
            'employee_limit' => 'required|integer|min:0',
            'storage_limit_gb' => 'required|integer|min:0',
            'duration_months' => 'nullable|integer|min:1|max:60',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|array',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'exists:modules,id',
        ]);

        $package = LicensePackage::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'base_price' => $validated['base_price'],
            'annual_price' => $validated['annual_price'] ?? null,
            'user_limit' => $validated['user_limit'],
            'location_limit' => $validated['location_limit'],
            'employee_limit' => $validated['employee_limit'],
            'storage_limit_gb' => $validated['storage_limit_gb'],
            'duration_months' => $validated['duration_months'],
            'is_active' => $validated['is_active'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'features' => $validated['features'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Modülleri ekle
        $syncData = [];
        if (! empty($validated['module_ids'])) {
            foreach ($validated['module_ids'] as $moduleId) {
                $syncData[$moduleId] = ['is_included' => true];
            }
        }

        // Core modülleri otomatik ekle
        $coreModules = Module::where('is_core', true)->pluck('id')->toArray();
        foreach ($coreModules as $moduleId) {
            if (! isset($syncData[$moduleId])) {
                $syncData[$moduleId] = ['is_included' => true];
            }
        }

        // Tüm modülleri sync et
        if (! empty($syncData)) {
            $package->modules()->sync($syncData);
        }

        $package->load('modules');

        ActivityLog::log('create', $package, 'Lisans paketi oluşturuldu: '.$package->name);

        return $this->success($package, 'Lisans paketi oluşturuldu', 201);
    }

    /**
     * Paket güncelle
     */
    public function update(Request $request, $package): JsonResponse
    {
        // Route model binding - Laravel apiResource ile {license-package} parametresi gelir
        // Bu durumda parametre adı snake_case'e çevrilir: license_package
        // Controller metodunda LicensePackage $licensePackage veya manuel yükleme gerekir
        if (! $package instanceof LicensePackage) {
            $packageId = is_numeric($package) ? $package : $request->route('license-package') ?? $request->route('package') ?? $request->route('id');
            if (! $packageId) {
                // Route parametrelerinden ID'yi bul
                $routeParams = $request->route()->parameters();
                $packageId = $routeParams['license-package'] ?? $routeParams['package'] ?? $routeParams['id'] ?? null;
            }
            if (! $packageId) {
                abort(404, 'Package not found');
            }
            $package = LicensePackage::findOrFail($packageId);
        }

        // Eğer name değişmemişse unique validation'ı atla
        $nameRules = 'sometimes|required|string|max:255';
        $nameChanged = $request->has('name') && $request->input('name') !== $package->name;
        if ($nameChanged) {
            // Name değişmişse unique kontrolü yap
            $nameRules .= '|unique:license_packages,name,'.$package->id;
        }

        try {
            $validated = $request->validate([
                'name' => $nameRules,
                'description' => 'sometimes|nullable|string',
                'base_price' => 'sometimes|required|numeric|min:0',
                'annual_price' => 'sometimes|nullable|numeric|min:0',
                'user_limit' => 'sometimes|required|integer|min:0',
                'location_limit' => 'sometimes|required|integer|min:0',
                'employee_limit' => 'sometimes|required|integer|min:0',
                'storage_limit_gb' => 'sometimes|required|integer|min:0',
                'duration_months' => 'sometimes|nullable|integer|min:1|max:60',
                'is_active' => 'sometimes|boolean',
                'is_featured' => 'sometimes|boolean',
                'sort_order' => 'sometimes|integer|min:0',
                'features' => 'sometimes|nullable|array',
                'module_ids' => 'sometimes|nullable|array',
                'module_ids.*' => 'exists:modules,id',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }

        if (isset($validated['name']) && $validated['name'] !== $package->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['updated_by'] = auth()->id();

        // Modül güncellemesi varsa
        $moduleIds = $validated['module_ids'] ?? null;
        unset($validated['module_ids']);

        $package->update($validated);

        // Modülleri güncelle
        if ($moduleIds !== null) {
            $syncData = [];
            foreach ($moduleIds as $moduleId) {
                $syncData[$moduleId] = ['is_included' => true];
            }

            // Core modülleri otomatik ekle
            $coreModules = Module::where('is_core', true)->pluck('id')->toArray();
            foreach ($coreModules as $moduleId) {
                if (! isset($syncData[$moduleId])) {
                    $syncData[$moduleId] = ['is_included' => true];
                }
            }

            if (! empty($syncData)) {
                $package->modules()->sync($syncData);
            }
        }

        $package->load('modules');

        ActivityLog::log('update', $package, 'Lisans paketi güncellendi: '.$package->name);

        return $this->success($package, 'Lisans paketi güncellendi');
    }

    /**
     * Paket sil
     */
    public function destroy(LicensePackage $package): JsonResponse
    {
        // Aktif firma varsa silme
        if ($package->companies()->where('status', 'active')->exists()) {
            return $this->error('Bu pakete bağlı aktif firmalar var. Önce firmaları başka pakete taşıyın.', null, 422);
        }

        $packageName = $package->name;
        $package->delete();

        ActivityLog::log('delete', null, 'Lisans paketi silindi: '.$packageName);

        return $this->success(null, 'Lisans paketi silindi');
    }

    /**
     * Paket modüllerini güncelle
     */
    public function syncModules(Request $request, LicensePackage $package): JsonResponse
    {
        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:modules,id',
            'modules.*.is_included' => 'required|boolean',
            'modules.*.additional_price' => 'nullable|numeric|min:0',
        ]);

        $syncData = [];
        foreach ($validated['modules'] as $module) {
            $syncData[$module['id']] = [
                'is_included' => $module['is_included'],
                'additional_price' => $module['additional_price'] ?? 0,
            ];
        }

        $package->modules()->sync($syncData);
        $package->load('modules');

        ActivityLog::log('update', $package, 'Paket modülleri güncellendi: '.$package->name);

        return $this->success($package, 'Paket modülleri güncellendi');
    }

    /**
     * Paket kopyala
     */
    public function duplicate(LicensePackage $package): JsonResponse
    {
        $newPackage = $package->replicate();
        $newPackage->name = $package->name.' (Kopya)';
        $newPackage->slug = Str::slug($newPackage->name);
        $newPackage->is_active = false;
        $newPackage->created_by = auth()->id();
        $newPackage->save();

        // Modülleri kopyala
        foreach ($package->modules as $module) {
            $newPackage->modules()->attach($module->id, [
                'is_included' => $module->pivot->is_included,
                'additional_price' => $module->pivot->additional_price,
            ]);
        }

        $newPackage->load('modules');

        ActivityLog::log('create', $newPackage, 'Paket kopyalandı: '.$package->name.' → '.$newPackage->name);

        return $this->success($newPackage, 'Paket kopyalandı', 201);
    }

    /**
     * Tüm modülleri listele (paket oluştururken seçim için)
     */
    public function availableModules(): JsonResponse
    {
        $modules = Module::where('is_active', true)
            ->orderBy('is_core', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'is_core', 'price_monthly', 'price_yearly']);

        return $this->success($modules, 'Modüller listelendi');
    }
}

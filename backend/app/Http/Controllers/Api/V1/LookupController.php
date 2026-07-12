<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\Lookup;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LookupController extends BaseController
{
    public function __construct(
        private readonly LookupService $lookups
    ) {}

    /**
     * Form dropdown — aktif birleşik liste (auth yeterli).
     */
    public function forType(string $type): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $rows = $this->lookups->forType($type, $companyId, activeOnly: true);

        return $this->success($rows->map->toApiArray()->values());
    }

    /**
     * Yönetim listesi — pasifler dahil.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lookup_type' => 'required|string|max:64',
            'active_only' => 'sometimes|boolean',
        ]);

        $activeOnly = $request->boolean('active_only', false);
        $rows = $this->lookups->forType(
            $validated['lookup_type'],
            $this->getCompanyId(),
            $activeOnly
        );

        return $this->success($rows->map->toApiArray()->values());
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lookup_type' => 'required|string|max:64',
            'value' => 'required|string|max:100',
        ]);

        $resolved = $this->lookups->resolve(
            $validated['lookup_type'],
            $validated['value'],
            $this->getCompanyId()
        );

        return $this->success($resolved);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli', 403);
        }

        $validated = $request->validate([
            'lookup_type' => 'required|string|max:64',
            'value' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
            ],
            'label' => 'required|string|max:255',
            'color' => 'nullable|string|max:32',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'meta' => 'nullable|array',
        ]);

        // Sistem tiplerine ekleme yasak
        if ($this->lookups->isSystemType($validated['lookup_type'])) {
            return $this->error('Sistem lookup değerleri eklenemez', 403);
        }

        // Hibrit: yeni value eklenemez (kod sabit)
        if ($this->lookups->isHybridType($validated['lookup_type'])) {
            return $this->error('Hibrit lookup değerleri eklenemez (yalnızca etiket/renk/sıra)', 403);
        }

        $value = $validated['value'] ?? Str::slug($validated['label'], '_');
        if ($value === '') {
            return $this->error('value üretilemedi', 422);
        }

        if ($this->lookups->forType($validated['lookup_type'], $companyId, activeOnly: false)
            ->contains(fn (Lookup $r) => $r->value === $value)) {
            return $this->error('Bu değer zaten mevcut', 422);
        }

        $row = Lookup::create([
            'company_id' => $companyId,
            'lookup_type' => $validated['lookup_type'],
            'value' => $value,
            'label' => $validated['label'],
            'color' => $validated['color'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 100,
            'is_active' => $validated['is_active'] ?? true,
            'is_system' => false,
            'meta' => $validated['meta'] ?? null,
        ]);

        ActivityLog::log('create', $row, "Lookup oluşturuldu: {$row->lookup_type}/{$row->value}");

        return $this->created($row->toApiArray(), 'Lookup değeri oluşturuldu');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli', 403);
        }

        $row = Lookup::query()->findOrFail($id);

        if ($row->is_system) {
            return $this->error('Sistem lookup düzenlenemez', 403);
        }

        // Başka firmanın satırı
        if ($row->company_id !== null && $row->company_id !== $companyId) {
            return $this->error('Bu kayda erişim yok', 403);
        }

        // Platform default (company_id null) → override oluştur
        if ($row->company_id === null && $this->lookups->isSystemType($row->lookup_type)) {
            return $this->error('Sistem lookup düzenlenemez', 403);
        }

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'color' => 'nullable|string|max:32',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'meta' => 'nullable|array',
        ]);

        // Hibrit: value değiştirilemez (zaten validate'de value yok)
        if ($row->isHybrid() && $request->has('value')) {
            return $this->error('Hibrit lookup value değiştirilemez', 403);
        }

        $updated = $this->lookups->ensureCompanyRow($row, $companyId, $validated);

        ActivityLog::log('update', $updated, "Lookup güncellendi: {$updated->lookup_type}/{$updated->value}");

        return $this->success($updated->toApiArray(), 'Lookup güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli', 403);
        }

        $row = Lookup::query()->findOrFail($id);

        if ($row->is_system) {
            return $this->error('Sistem lookup silinemez', 403);
        }

        if ($row->company_id !== null && $row->company_id !== $companyId) {
            return $this->error('Bu kayda erişim yok', 403);
        }

        if ($row->isHybrid() || $this->lookups->isHybridType($row->lookup_type)) {
            return $this->error('Hibrit lookup silinemez (yalnızca etiket/renk düzenlenir)', 403);
        }

        if ($row->company_id === null && $this->lookups->isSystemType($row->lookup_type)) {
            return $this->error('Sistem lookup silinemez', 403);
        }

        $used = $this->lookups->isUsed($row->lookup_type, $row->value, $companyId);

        if ($used) {
            // K-B: pasifleştir
            $deactivated = $this->lookups->ensureCompanyRow($row, $companyId, ['is_active' => false]);
            ActivityLog::log('update', $deactivated, "Lookup pasifleştirildi (kullanımda): {$deactivated->lookup_type}/{$deactivated->value}");

            return $this->success($deactivated->toApiArray(), 'Değer kullanımda olduğu için silinmedi; pasifleştirildi');
        }

        if ($row->company_id === $companyId) {
            $row->forceDelete();
            ActivityLog::log('delete', $row, "Lookup silindi: {$row->lookup_type}/{$row->value}");

            return $this->success(null, 'Lookup değeri silindi');
        }

        // Platform default, kullanılmıyor → firma için gizle (pasif override)
        $hidden = $this->lookups->ensureCompanyRow($row, $companyId, ['is_active' => false]);
        ActivityLog::log('update', $hidden, "Lookup firma için gizlendi: {$hidden->lookup_type}/{$hidden->value}");

        return $this->success($hidden->toApiArray(), 'Platform varsayılanı firma için pasifleştirildi');
    }

    public function reorder(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli', 403);
        }

        $validated = $request->validate([
            'lookup_type' => 'required|string|max:64',
            'items' => 'required|array|min:1',
            'items.*.value' => 'required|string|max:100',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($this->lookups->isSystemType($validated['lookup_type'])) {
            return $this->error('Sistem lookup sıralanamaz', 403);
        }

        foreach ($validated['items'] as $item) {
            $source = $this->lookups->findEditableRow(
                $validated['lookup_type'],
                $item['value'],
                $companyId
            );
            if ($source && ! $source->is_system) {
                $this->lookups->ensureCompanyRow($source, $companyId, [
                    'sort_order' => $item['sort_order'],
                ]);
            }
        }

        $rows = $this->lookups->forType($validated['lookup_type'], $companyId, activeOnly: false);

        return $this->success($rows->map->toApiArray()->values(), 'Sıra güncellendi');
    }
}

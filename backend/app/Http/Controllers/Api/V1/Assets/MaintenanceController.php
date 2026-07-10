<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends BaseController
{
    /**
     * Bakım listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssetMaintenance::whereHas('asset', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->with('asset:id,name,asset_code');

        if ($request->has('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $maintenances = $query->orderBy('scheduled_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($maintenances, 'Bakım kayıtları listelendi');
    }

    /**
     * Yeni bakım kaydı oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'type' => 'required|in:preventive,corrective,upgrade',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:255',
        ]);

        // Varlık bu firmaya ait mi?
        $asset = Asset::where('company_id', $this->getCompanyId())
            ->findOrFail($validated['asset_id']);

        $maintenance = AssetMaintenance::create([
            ...$validated,
            'status' => 'scheduled',
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $maintenance, 'Bakım kaydı oluşturuldu: '.$maintenance->title);

        return $this->success($maintenance->load('asset'), 'Bakım kaydı oluşturuldu', 201);
    }

    /**
     * Bakım detayı
     */
    public function show(int $id): JsonResponse
    {
        $maintenance = AssetMaintenance::whereHas('asset', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })
            ->with('asset')
            ->findOrFail($id);

        return $this->success($maintenance);
    }

    /**
     * Bakım kaydı güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $maintenance = AssetMaintenance::whereHas('asset', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|in:preventive,corrective,upgrade',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:255',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'resolution' => 'nullable|string',
        ]);

        $oldValues = $maintenance->getOriginal();
        $maintenance->update($validated);

        ActivityLog::log('update', $maintenance, 'Bakım kaydı güncellendi: '.$maintenance->title, $oldValues, $maintenance->fresh()->toArray());

        return $this->success($maintenance, 'Bakım kaydı güncellendi');
    }

    /**
     * Bakım kaydı sil
     */
    public function destroy(int $id): JsonResponse
    {
        $maintenance = AssetMaintenance::whereHas('asset', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        $maintenanceTitle = $maintenance->title;
        ActivityLog::log('delete', null, 'Bakım kaydı silindi: '.$maintenanceTitle);

        $maintenance->delete();

        return $this->success(null, 'Bakım kaydı silindi');
    }

    /**
     * Bakımı tamamla
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $maintenance = AssetMaintenance::whereHas('asset', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        $validated = $request->validate([
            'resolution' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $maintenance->update([
            'status' => 'completed',
            'completed_date' => now(),
            'resolution' => $validated['resolution'] ?? $maintenance->resolution,
            'cost' => $validated['cost'] ?? $maintenance->cost,
        ]);

        // Varlığı tekrar kullanılabilir yap
        if ($maintenance->asset->status === 'maintenance') {
            $maintenance->asset->update(['status' => 'available']);
        }

        ActivityLog::log('update', $maintenance, 'Bakım tamamlandı: '.$maintenance->title);

        return $this->success($maintenance, 'Bakım tamamlandı');
    }
}

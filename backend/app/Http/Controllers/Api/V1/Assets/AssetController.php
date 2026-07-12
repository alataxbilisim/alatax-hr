<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Enums\AssetStatus;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\User;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
    ) {}

    /**
     * Varlık listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Asset::where('company_id', $this->getCompanyId())
            ->with(['category:id,name', 'currentAssignment.user:id,name']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return $this->success($assets, 'Varlıklar listelendi');
    }

    /**
     * Yeni varlık oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'asset_code' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'warranty_end_date' => 'nullable|date',
            'condition' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'specifications' => 'nullable|array',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_ASSET_CONDITION,
            $validated['condition'] ?? null,
            $companyId,
            'condition'
        );

        $asset = Asset::create([
            ...$validated,
            'company_id' => $companyId,
            'status' => 'available',
            'condition' => $validated['condition'] ?? 'new',
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $asset, 'Varlık oluşturuldu: '.$asset->name);

        return $this->success($asset->load('category'), 'Varlık oluşturuldu', 201);
    }

    /**
     * Varlık detayı
     */
    public function show(int $id): JsonResponse
    {
        $asset = Asset::where('company_id', $this->getCompanyId())
            ->with([
                'category',
                'currentAssignment.user',
                'assignments' => function ($q) {
                    $q->with('user:id,name')->latest()->take(10);
                },
                'maintenances' => function ($q) {
                    $q->latest()->take(5);
                },
            ])
            ->findOrFail($id);

        return $this->success($asset);
    }

    /**
     * Varlık güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $asset = Asset::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:asset_categories,id',
            'name' => 'sometimes|required|string|max:255',
            'asset_code' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'warranty_end_date' => 'nullable|date',
            'condition' => 'nullable|string|max:100',
            'status' => 'sometimes|nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'specifications' => 'nullable|array',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_ASSET_CONDITION,
            $validated['condition'] ?? null,
            $companyId,
            'condition'
        );
        $this->lookups->assertValid(
            LookupService::TYPE_ASSET_STATUS,
            $validated['status'] ?? null,
            $companyId,
            'status'
        );

        $oldValues = $asset->getOriginal();
        $asset->update($validated);

        ActivityLog::log('update', $asset, 'Varlık güncellendi: '.$asset->name, $oldValues, $asset->fresh()->toArray());

        return $this->success($asset, 'Varlık güncellendi');
    }

    /**
     * Varlık sil
     */
    public function destroy(int $id): JsonResponse
    {
        $asset = Asset::where('company_id', $this->getCompanyId())->findOrFail($id);

        if ($asset->status === AssetStatus::Assigned) {
            return $this->error('Zimmetli varlık silinemez. Önce iade alınmalıdır.', 422);
        }

        $assetName = $asset->name;
        ActivityLog::log('delete', null, 'Varlık silindi: '.$assetName);

        $asset->delete();

        return $this->success(null, 'Varlık silindi');
    }

    /**
     * Zimmet ver
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $asset = Asset::where('company_id', $this->getCompanyId())->findOrFail($id);

        if ($asset->status !== AssetStatus::Available) {
            return $this->error('Bu varlık şu anda kullanılabilir değil.', 422);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $asset->assignments()->create([
            'user_id' => $validated['user_id'],
            'assigned_date' => now(),
            'notes' => $validated['notes'] ?? null,
            'condition_at_assignment' => $asset->condition,
            'assigned_by' => auth()->id(),
        ]);

        $asset->update(['status' => 'assigned']);

        $user = User::find($validated['user_id']);
        ActivityLog::log('update', $asset, "Varlık zimmetlendi: {$user->name}");

        return $this->success($asset->load('currentAssignment.user'), 'Varlık zimmetlendi');
    }

    /**
     * İade al
     */
    public function returnAsset(Request $request, int $id): JsonResponse
    {
        $asset = Asset::where('company_id', $this->getCompanyId())->findOrFail($id);

        if ($asset->status !== AssetStatus::Assigned) {
            return $this->error('Bu varlık zaten zimmetli değil.', 422);
        }

        $validated = $request->validate([
            'condition' => 'required|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_ASSET_CONDITION,
            $validated['condition'],
            $companyId,
            'condition'
        );

        $currentAssignment = $asset->currentAssignment;
        if ($currentAssignment) {
            $currentAssignment->update([
                'return_date' => now(),
                'condition_at_return' => $validated['condition'],
                'notes' => $validated['notes'] ?? $currentAssignment->notes,
                'returned_to' => auth()->id(),
            ]);
        }

        $asset->update([
            'status' => 'available',
            'condition' => $validated['condition'],
        ]);

        ActivityLog::log('update', $asset, 'Varlık iade alındı');

        return $this->success($asset, 'Varlık iade alındı');
    }
}

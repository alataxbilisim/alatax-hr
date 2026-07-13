<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PositionController extends BaseController
{
    /**
     * Pozisyon kataloğu listesi (sayfalı).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query()
            ->with(['department:id,name,code'])
            ->ordered();

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('sgk_occupation_code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->department_id);
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return $this->paginated($query->paginate($perPage), 'Pozisyonlar listelendi');
    }

    public function show(int $id): JsonResponse
    {
        $position = Position::query()
            ->with(['department:id,name,code'])
            ->findOrFail($id);

        return $this->success($position);
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $position = Position::create([
            'company_id' => $this->getCompanyId(),
            'code' => $validated['code'] ?? $this->generateCode($validated['name']),
            'name' => $validated['name'],
            'department_id' => $validated['department_id'] ?? null,
            'sgk_occupation_code' => $validated['sgk_occupation_code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_system' => false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        return $this->created(
            $position->load('department:id,name,code'),
            'Pozisyon oluşturuldu'
        );
    }

    public function update(UpdatePositionRequest $request, int $id): JsonResponse
    {
        $position = Position::query()->findOrFail($id);
        $validated = $request->validated();

        // Sistem kodu (code) korunur; etiket/SGK/departman düzenlenebilir
        if ($position->is_system && array_key_exists('code', $validated)) {
            unset($validated['code']);
        }

        $position->fill($validated);
        $position->updated_by = auth()->id();
        $position->save();

        return $this->success(
            $position->fresh()->load('department:id,name,code'),
            'Pozisyon güncellendi'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $position = Position::query()->findOrFail($id);

        if ($position->is_system) {
            return $this->error('Sistem pozisyonları silinemez. Pasife alabilirsiniz.', 422);
        }

        $position->delete();

        return $this->success(null, 'Pozisyon silindi');
    }

    private function generateCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, ''));
        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'POS';
        $base = substr($base, 0, 12);

        $code = $base;
        $i = 1;
        while (
            Position::query()
                ->where('code', $code)
                ->exists()
        ) {
            $code = substr($base, 0, 10).$i;
            $i++;
        }

        return $code;
    }
}

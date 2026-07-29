<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ReportFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportFolderController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $folders = ReportFolder::query()
            ->where('company_id', $this->getCompanyId())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success($folders, 'Klasörler');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $folder = ReportFolder::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->success($folder, 'Klasör oluşturuldu', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $folder = ReportFolder::query()
            ->where('company_id', $this->getCompanyId())
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|integer',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $folder->fill($validated);
        $folder->save();

        return $this->success($folder, 'Klasör güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        $folder = ReportFolder::query()
            ->where('company_id', $this->getCompanyId())
            ->whereKey($id)
            ->firstOrFail();
        $folder->delete();

        return $this->success(null, 'Klasör silindi');
    }
}

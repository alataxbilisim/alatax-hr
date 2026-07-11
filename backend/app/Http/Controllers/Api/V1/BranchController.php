<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EmployeeResource;
use App\Models\ActivityLog;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends BaseController
{
    /**
     * Şube listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Branch::with(['manager:id,name,email', 'createdBy:id,name', 'updatedBy:id,name'])
            ->ordered();

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Aktif/Pasif filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Merkez şube filtresi
        if ($request->has('is_headquarters')) {
            $query->where('is_headquarters', $request->boolean('is_headquarters'));
        }

        $branches = $query->paginate($request->get('per_page', 15));

        return $this->paginated($branches, 'Şubeler listelendi');
    }

    /**
     * Şube detayı
     */
    public function show(int $id): JsonResponse
    {
        $branch = Branch::with(['manager:id,name,email', 'createdBy:id,name', 'updatedBy:id,name'])
            ->find($id);

        if (! $branch) {
            return $this->notFound('Şube bulunamadı');
        }

        return $this->success($branch);
    }

    /**
     * Yeni şube oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (! $company) {
            return $this->notFound('Firma bulunamadı');
        }

        // Lisans limiti kontrolü
        if ($company->hasReachedLocationLimit()) {
            return $this->error(
                'Şube limitinize ulaştınız. Daha fazla şube eklemek için lisansınızı yükseltin.',
                403
            );
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:branches,code,NULL,id,company_id,'.$company->id,
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
            'is_headquarters' => 'sometimes|boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Manager'ın aynı firmaya ait olduğunu kontrol et
        if (isset($validated['manager_id'])) {
            $manager = \App\Models\User::find($validated['manager_id']);
            if (! $manager || $manager->company_id !== $company->id) {
                return $this->error('Geçersiz yönetici seçimi', 422);
            }
        }

        DB::beginTransaction();
        try {
            $branch = Branch::create($validated);

            // Eğer merkez şube olarak işaretlendiyse
            if ($validated['is_headquarters'] ?? false) {
                $branch->setAsHeadquarters();
            }

            // Location count güncelle
            $company->updateLocationCount();

            ActivityLog::log(
                'create',
                $branch,
                "Şube oluşturuldu: {$branch->name}",
                null,
                $branch->toArray()
            );

            DB::commit();

            return $this->created($branch->load(['manager:id,name,email']), 'Şube oluşturuldu');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->serverError('Şube oluşturulurken bir hata oluştu: '.$e->getMessage());
        }
    }

    /**
     * Şube güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (! $branch) {
            return $this->notFound('Şube bulunamadı');
        }

        $company = auth()->user()->company;

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50|unique:branches,code,'.$id.',id,company_id,'.$company->id,
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
            'is_headquarters' => 'sometimes|boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Manager kontrolü
        if (isset($validated['manager_id'])) {
            $manager = \App\Models\User::find($validated['manager_id']);
            if (! $manager || $manager->company_id !== $company->id) {
                return $this->error('Geçersiz yönetici seçimi', 422);
            }
        }

        $oldValues = $branch->toArray();

        DB::beginTransaction();
        try {
            $branch->update($validated);

            // Merkez şube değişikliği
            if (isset($validated['is_headquarters']) && $validated['is_headquarters']) {
                $branch->setAsHeadquarters();
            }

            // Location count güncelle
            $company->updateLocationCount();

            ActivityLog::log(
                'update',
                $branch,
                "Şube güncellendi: {$branch->name}",
                $oldValues,
                $branch->fresh()->toArray()
            );

            DB::commit();

            return $this->success($branch->fresh()->load(['manager:id,name,email']), 'Şube güncellendi');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->serverError('Şube güncellenirken bir hata oluştu: '.$e->getMessage());
        }
    }

    /**
     * Şube sil
     */
    public function destroy(int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (! $branch) {
            return $this->notFound('Şube bulunamadı');
        }

        // Merkez şubeyi silmeye izin verme
        if ($branch->is_headquarters) {
            return $this->error('Merkez şube silinemez. Önce başka bir şubeyi merkez şube yapın.', 403);
        }

        $company = auth()->user()->company;
        $branchName = $branch->name;

        DB::beginTransaction();
        try {
            $oldValues = $branch->toArray();
            $branch->delete();

            // Location count güncelle
            $company->updateLocationCount();

            ActivityLog::log(
                'delete',
                $branch,
                "Şube silindi: {$branchName}",
                $oldValues,
                null
            );

            DB::commit();

            return $this->success(null, 'Şube silindi');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->serverError('Şube silinirken bir hata oluştu: '.$e->getMessage());
        }
    }

    /**
     * Merkez şube yap
     */
    public function setHeadquarters(int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (! $branch) {
            return $this->notFound('Şube bulunamadı');
        }

        if (! $branch->is_active) {
            return $this->error('Pasif şubeler merkez şube yapılamaz', 422);
        }

        DB::beginTransaction();
        try {
            $oldValues = $branch->toArray();
            $branch->setAsHeadquarters();

            ActivityLog::log(
                'update',
                $branch,
                "Şube merkez şube yapıldı: {$branch->name}",
                $oldValues,
                $branch->fresh()->toArray()
            );

            DB::commit();

            return $this->success($branch->fresh()->load(['manager:id,name,email']), 'Merkez şube güncellendi');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->serverError('Merkez şube güncellenirken bir hata oluştu: '.$e->getMessage());
        }
    }

    /**
     * Şube çalışanları
     */
    public function employees(int $id, Request $request): JsonResponse
    {
        $branch = Branch::find($id);

        if (! $branch) {
            return $this->notFound('Şube bulunamadı');
        }

        // Employee modelinde branch_id yoksa, User modelinden branch_id ile filtrele
        $query = \App\Models\Employee::where('company_id', $this->getCompanyId())
            ->whereHas('user', function ($q) use ($id) {
                $q->where('branch_id', $id);
            })
            ->with(['user:id,name,email,branch_id', 'department:id,name']);

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $employees = $query->paginate($request->get('per_page', 15));

        return $this->paginated(
            EmployeeResource::collection($employees->getCollection())->resolve(),
            'Şube çalışanları listelendi',
            $employees
        );
    }
}

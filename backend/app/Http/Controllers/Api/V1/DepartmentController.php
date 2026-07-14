<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends BaseController
{
    /**
     * Departman listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::where('company_id', $this->getCompanyId())
            ->with(['parent:id,name', 'manager:id,name']);

        // Personel sayısını ekle
        if ($request->boolean('with_counts')) {
            $query->withCount('employees');
        }

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Sadece aktifler
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        // Sıralama
        $query->orderBy('sort_order')->orderBy('name');

        $departments = $query->get();

        // manager_id → users (şema); eager load yeterli
        return $this->success($departments);
    }

    /**
     * Departman detayı
     */
    public function show(int $id): JsonResponse
    {
        $department = Department::where('company_id', $this->getCompanyId())
            ->with(['parent:id,name', 'children:id,name,parent_id', 'manager:id,name'])
            ->withCount('employees')
            ->findOrFail($id);

        return $this->success($department);
    }

    /**
     * Yeni departman oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where(function ($query) {
                    return $query->where('company_id', $this->getCompanyId());
                }),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments')->where(function ($query) {
                    return $query->where('company_id', $this->getCompanyId());
                }),
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $department = Department::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->generateCode($validated['name']),
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        return $this->created($department->load('parent:id,name'), 'Departman başarıyla oluşturuldu');
    }

    /**
     * Departman güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('departments')->where(function ($query) {
                    return $query->where('company_id', $this->getCompanyId());
                })->ignore($department->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments')->where(function ($query) {
                    return $query->where('company_id', $this->getCompanyId());
                })->ignore($department->id),
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Kendi kendine parent olmasın
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return $this->error('Departman kendisinin üst departmanı olamaz', 422);
        }

        $oldValues = $department->toArray();

        $department->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        return $this->success($department->load('parent:id,name'), 'Departman başarıyla güncellendi');
    }

    /**
     * Departman sil
     */
    public function destroy(int $id): JsonResponse
    {
        $department = Department::where('company_id', $this->getCompanyId())
            ->withCount('employees')
            ->findOrFail($id);

        // Personeli var mı kontrol et
        if ($department->employees_count > 0) {
            return $this->error('Bu departmanda personel bulunmaktadır. Önce personelleri başka departmana taşıyın.', 422);
        }

        // Alt departman var mı kontrol et
        $childCount = Department::where('parent_id', $id)->count();
        if ($childCount > 0) {
            return $this->error('Bu departmanın alt departmanları bulunmaktadır. Önce alt departmanları silin veya taşıyın.', 422);
        }

        $oldValues = $department->toArray();

        $department->delete();

        return $this->success(null, 'Departman başarıyla silindi');
    }

    /**
     * Yönetici olabilecek personel listesi
     */
    public function getManagers(): JsonResponse
    {
        $managers = Employee::where('company_id', $this->getCompanyId())
            ->where('status', 'active')
            ->with('user:id,name')
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'user_id', 'position', 'title']);

        return $this->success($managers);
    }

    /**
     * Departman hiyerarşisi
     */
    public function getHierarchy(): JsonResponse
    {
        $departments = Department::where('company_id', $this->getCompanyId())
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->where('is_active', true)
                    ->with(['children' => function ($q2) {
                        $q2->where('is_active', true);
                    }]);
            }])
            ->withCount('employees')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success($departments);
    }

    /**
     * Departman kodu üret
     */
    private function generateCode(string $name): string
    {
        $code = mb_strtoupper($name);
        $code = str_replace(['ğ', 'Ğ'], 'G', $code);
        $code = str_replace(['ü', 'Ü'], 'U', $code);
        $code = str_replace(['ş', 'Ş'], 'S', $code);
        $code = str_replace(['ı', 'I'], 'I', $code);
        $code = str_replace(['ö', 'Ö'], 'O', $code);
        $code = str_replace(['ç', 'Ç'], 'C', $code);
        $code = preg_replace('/[^A-Z0-9]/', '', $code);

        return substr($code, 0, 10);
    }
}

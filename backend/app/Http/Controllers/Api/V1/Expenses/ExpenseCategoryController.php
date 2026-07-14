<?php

namespace App\Http\Controllers\Api\V1\Expenses;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Expenses\StoreExpenseCategoryRequest;
use App\Http\Requests\Expenses\UpdateExpenseCategoryRequest;
use App\Models\ActivityLog;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR masraf kategori CRUD.
 */
class ExpenseCategoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::query()->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $categories = $query->paginate($request->get('per_page', 50));

        return $this->paginated($categories, 'Masraf kategorileri listelendi');
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = ExpenseCategory::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'max_amount' => $validated['max_amount'] ?? null,
            'requires_receipt' => $validated['requires_receipt'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        ActivityLog::log('created', $category, 'Masraf kategorisi oluşturuldu: '.$category->name);

        return $this->success($category, 'Masraf kategorisi oluşturuldu', 201);
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        return $this->success($expenseCategory, 'Masraf kategorisi detayı');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $old = $expenseCategory->getOriginal();
        $expenseCategory->update($request->validated());

        ActivityLog::log(
            'updated',
            $expenseCategory,
            'Masraf kategorisi güncellendi: '.$expenseCategory->name,
            $old,
            $expenseCategory->fresh()->toArray()
        );

        return $this->success($expenseCategory->fresh(), 'Masraf kategorisi güncellendi');
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->expenseItems()->exists()) {
            return $this->error('Bu kategoriye bağlı masraf kalemleri var; silinemez', 422);
        }

        $name = $expenseCategory->name;
        $expenseCategory->delete();

        ActivityLog::log('deleted', null, 'Masraf kategorisi silindi: '.$name);

        return $this->success(null, 'Masraf kategorisi silindi');
    }
}

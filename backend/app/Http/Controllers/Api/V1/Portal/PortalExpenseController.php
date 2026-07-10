<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ExpenseClaim;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PortalExpenseController extends BaseController
{
    /**
     * Masraf kategorilerini listele
     */
    public function categories(): JsonResponse
    {
        $categories = ExpenseCategory::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_amount', 'requires_receipt']);

        return $this->success($categories, 'Masraf kategorileri');
    }

    /**
     * Kendi masraf taleplerini listele
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseClaim::where('user_id', auth()->id())
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $claims = $query->paginate($request->get('per_page', 20));

        return $this->paginated($claims, 'Masraf talepleri listelendi');
    }

    /**
     * Masraf talebi detayı
     */
    public function show(int $id): JsonResponse
    {
        $claim = ExpenseClaim::where('user_id', auth()->id())
            ->with(['items.category:id,name', 'approver:id,name'])
            ->findOrFail($id);

        return $this->success($claim, 'Masraf talebi detayı');
    }

    /**
     * Yeni masraf talebi oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expense_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.expense_category_id' => 'required|exists:expense_categories,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.item_date' => 'required|date',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.vendor_name' => 'nullable|string|max:255',
            'items.*.receipt_number' => 'nullable|string|max:100',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $claim = ExpenseClaim::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'expense_date' => $validated['expense_date'],
            'claim_number' => ExpenseClaim::generateClaimNumber($user->company_id),
            'total_amount' => 0,
            'status' => ExpenseClaim::STATUS_DRAFT,
        ]);

        foreach ($validated['items'] as $itemData) {
            ExpenseItem::create([
                'expense_claim_id' => $claim->id,
                'expense_category_id' => $itemData['expense_category_id'],
                'description' => $itemData['description'],
                'item_date' => $itemData['item_date'],
                'amount' => $itemData['amount'],
                'vendor_name' => $itemData['vendor_name'] ?? null,
                'receipt_number' => $itemData['receipt_number'] ?? null,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }

        $claim->calculateTotal();
        $claim->save();

        ActivityLog::log('expense_claim_created', $claim, 'Masraf talebi oluşturuldu');

        return $this->success($claim->load('items'), 'Masraf talebi oluşturuldu', 201);
    }

    /**
     * Masraf talebini güncelle (sadece taslak durumunda)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $claim = ExpenseClaim::where('user_id', auth()->id())
            ->where('status', ExpenseClaim::STATUS_DRAFT)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expense_date' => 'sometimes|date',
        ]);

        $claim->update($validated);

        ActivityLog::log('expense_claim_updated', $claim, 'Masraf talebi güncellendi');

        return $this->success($claim, 'Masraf talebi güncellendi');
    }

    /**
     * Masraf talebini gönder
     */
    public function submit(int $id): JsonResponse
    {
        $claim = ExpenseClaim::where('user_id', auth()->id())
            ->where('status', ExpenseClaim::STATUS_DRAFT)
            ->findOrFail($id);

        if ($claim->items()->count() === 0) {
            return $this->error('En az bir masraf kalemi eklemelisiniz', 422);
        }

        $claim->update([
            'status' => ExpenseClaim::STATUS_SUBMITTED,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        ActivityLog::log('expense_claim_submitted', $claim, 'Masraf talebi gönderildi');

        return $this->success($claim, 'Masraf talebi gönderildi');
    }

    /**
     * Masraf talebini iptal et
     */
    public function cancel(int $id): JsonResponse
    {
        $claim = ExpenseClaim::where('user_id', auth()->id())
            ->whereIn('status', [ExpenseClaim::STATUS_DRAFT, ExpenseClaim::STATUS_SUBMITTED])
            ->findOrFail($id);

        $claim->delete();

        ActivityLog::log('expense_claim_cancelled', $claim, 'Masraf talebi iptal edildi');

        return $this->success(null, 'Masraf talebi iptal edildi');
    }

    /**
     * Masraf kalemine fiş yükle
     */
    public function uploadReceipt(Request $request, int $itemId): JsonResponse
    {
        $item = ExpenseItem::whereHas('expenseClaim', function ($q) {
            $q->where('user_id', auth()->id())
              ->where('status', ExpenseClaim::STATUS_DRAFT);
        })->findOrFail($itemId);

        $validated = $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        $path = $request->file('receipt')->store(
            'expenses/' . auth()->user()->company_id . '/' . date('Y-m'),
            'public'
        );

        $item->update(['receipt_path' => $path]);

        ActivityLog::log('expense_receipt_uploaded', $item, 'Masraf fişi yüklendi');

        return $this->success([
            'receipt_path' => $path,
            'receipt_url' => Storage::url($path),
        ], 'Fiş yüklendi');
    }

    /**
     * Özet bilgiler
     */
    public function summary(): JsonResponse
    {
        $userId = auth()->id();

        $summary = [
            'pending_count' => ExpenseClaim::where('user_id', $userId)
                ->where('status', ExpenseClaim::STATUS_SUBMITTED)
                ->count(),
            'pending_amount' => ExpenseClaim::where('user_id', $userId)
                ->where('status', ExpenseClaim::STATUS_SUBMITTED)
                ->sum('total_amount'),
            'approved_this_month' => ExpenseClaim::where('user_id', $userId)
                ->where('status', ExpenseClaim::STATUS_APPROVED)
                ->whereMonth('approved_at', now()->month)
                ->sum('total_amount'),
            'paid_this_month' => ExpenseClaim::where('user_id', $userId)
                ->where('status', ExpenseClaim::STATUS_PAID)
                ->whereMonth('paid_at', now()->month)
                ->sum('total_amount'),
        ];

        return $this->success($summary, 'Masraf özeti');
    }
}


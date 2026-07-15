<?php

namespace App\Http\Controllers\Api\V1\Salary;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\SalaryReviewItem;
use App\Models\SalaryReviewPeriod;
use App\Services\EmployeeSensitiveFieldService;
use App\Services\Salary\SalaryReviewService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalaryReviewController extends BaseController
{
    public function __construct(
        protected SalaryReviewService $reviews,
        protected EmployeeSensitiveFieldService $sensitive,
        protected WorkflowService $workflows,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->sensitive->canViewSalary(auth()->user())) {
            return $this->error('Ücret görüntüleme yetkiniz yok', 403);
        }

        $query = SalaryReviewPeriod::query()->withCount('items')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return $this->paginated(
            $query->paginate(min(max((int) $request->input('per_page', 15), 1), 50)),
            'Zam dönemleri'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scope_type' => 'nullable|in:company,department,branch',
            'scope_id' => 'nullable|integer',
            'effective_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $period = $this->reviews->createPeriod(
            (int) $this->getCompanyId(),
            $validated,
            auth()->id()
        );

        return $this->success($period, 'Zam dönemi oluşturuldu', 201);
    }

    public function show(int $id): JsonResponse
    {
        if (! $this->sensitive->canViewSalary(auth()->user())) {
            return $this->error('Ücret görüntüleme yetkiniz yok', 403);
        }

        $period = SalaryReviewPeriod::with(['items.employee.user', 'creator:id,name'])
            ->findOrFail($id);

        $items = $period->items->map(fn (SalaryReviewItem $item) => $this->reviews->itemWithBand($item));

        return $this->success([
            'period' => $period->makeHidden('items'),
            'items' => $items,
        ], 'Zam dönemi detayı');
    }

    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $period = SalaryReviewPeriod::findOrFail($id);
        $item = SalaryReviewItem::where('period_id', $period->id)->findOrFail($itemId);

        $validated = $request->validate([
            'proposed_amount' => 'nullable|numeric|min:0',
            'increase_percent' => 'nullable|numeric',
            'change_reason' => 'nullable|string|max:64',
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $updated = $this->reviews->updateItem($period, $item, $validated);
        } catch (ValidationException $e) {
            return $this->error(
                collect($e->errors())->flatten()->first() ?? 'Güncellenemedi',
                422,
                $e->errors()
            );
        }

        return $this->success($this->reviews->itemWithBand($updated), 'Öneri güncellendi');
    }

    public function submit(int $id): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $period = SalaryReviewPeriod::findOrFail($id);

        try {
            $updated = $this->reviews->submitForApproval($period, (int) auth()->id());
        } catch (ValidationException $e) {
            return $this->error(
                collect($e->errors())->flatten()->first() ?? 'Onaya gönderilemedi',
                422,
                $e->errors()
            );
        }

        return $this->success($updated, 'Zam dönemi onaya gönderildi');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $period = SalaryReviewPeriod::findOrFail($id);
        $user = auth()->user();
        $record = $this->workflows->findPendingRecordForActor($period, (int) $user->id);

        if ($record === null) {
            return $this->error('Onay kaydı bulunamadı veya yetkiniz yok', 403);
        }

        $ok = $this->workflows->processAuthorizedApproval(
            $record,
            (int) $user->id,
            $request->input('notes')
        );

        if (! $ok) {
            return $this->error('Onay işlenemedi', 422);
        }

        return $this->success($period->fresh(['items']), 'Onay kaydedildi');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $period = SalaryReviewPeriod::findOrFail($id);
        $user = auth()->user();
        $record = $this->workflows->findPendingRecordForActor($period, (int) $user->id);

        if ($record === null) {
            return $this->error('Onay kaydı bulunamadı veya yetkiniz yok', 403);
        }

        $ok = $this->workflows->processAuthorizedRejection(
            $record,
            (int) $user->id,
            $validated['reason']
        );

        if (! $ok) {
            return $this->error('Red işlenemedi', 422);
        }

        return $this->success($period->fresh(), 'Dönem reddedildi');
    }
}

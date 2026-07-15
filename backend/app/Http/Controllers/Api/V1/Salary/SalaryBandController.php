<?php

namespace App\Http\Controllers\Api\V1\Salary;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Position;
use App\Models\SalaryBand;
use App\Services\EmployeeSensitiveFieldService;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalaryBandController extends BaseController
{
    public function __construct(
        protected EmployeeSensitiveFieldService $sensitive,
        protected LookupService $lookups,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->sensitive->canViewSalary(auth()->user())) {
            return $this->error('Ücret görüntüleme yetkiniz yok', 403);
        }

        $query = SalaryBand::query()
            ->with(['position:id,name,code'])
            ->orderBy('id');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return $this->paginated(
            $query->paginate(min(max((int) $request->input('per_page', 25), 1), 100)),
            'Ücret bantları'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $validated = $this->validateBand($request);
        $this->assertPosition($validated['position_id']);

        $band = SalaryBand::create([
            ...$validated,
            'company_id' => $this->getCompanyId(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $this->success($band->load('position:id,name,code'), 'Ücret bandı oluşturuldu', 201);
    }

    public function show(int $id): JsonResponse
    {
        if (! $this->sensitive->canViewSalary(auth()->user())) {
            return $this->error('Ücret görüntüleme yetkiniz yok', 403);
        }

        $band = SalaryBand::with('position:id,name,code')->findOrFail($id);

        return $this->success($band, 'Ücret bandı');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $band = SalaryBand::findOrFail($id);
        $validated = $this->validateBand($request, false);
        if (isset($validated['position_id'])) {
            $this->assertPosition($validated['position_id']);
        }

        $band->update([
            ...$validated,
            'updated_by' => auth()->id(),
        ]);

        return $this->success($band->fresh()->load('position:id,name,code'), 'Ücret bandı güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        if (! $this->sensitive->canEditSalary(auth()->user())) {
            return $this->error('Ücret düzenleme yetkiniz yok', 403);
        }

        $band = SalaryBand::findOrFail($id);
        $band->delete();

        return $this->success(null, 'Ücret bandı silindi');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBand(Request $request, bool $creating = true): array
    {
        $rules = [
            'position_id' => ($creating ? 'required' : 'sometimes').'|integer|exists:positions,id',
            'min_amount' => ($creating ? 'required' : 'sometimes').'|numeric|min:0',
            'mid_amount' => ($creating ? 'required' : 'sometimes').'|numeric|min:0',
            'max_amount' => ($creating ? 'required' : 'sometimes').'|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'is_active' => 'sometimes|boolean',
        ];

        $validated = $request->validate($rules);

        $min = (float) ($validated['min_amount'] ?? 0);
        $mid = (float) ($validated['mid_amount'] ?? 0);
        $max = (float) ($validated['max_amount'] ?? 0);

        if (isset($validated['min_amount'], $validated['mid_amount'], $validated['max_amount'])
            && ($min > $mid || $mid > $max)) {
            throw ValidationException::withMessages([
                'mid_amount' => ['min ≤ mid ≤ max olmalıdır.'],
            ]);
        }

        if (isset($validated['currency'])) {
            $this->lookups->assertValid(
                LookupService::TYPE_CURRENCY,
                $validated['currency'],
                $this->getCompanyId(),
                'currency'
            );
        } elseif ($creating) {
            $validated['currency'] = 'TRY';
        }

        return $validated;
    }

    private function assertPosition(int $positionId): void
    {
        $ok = Position::query()
            ->where('company_id', $this->getCompanyId())
            ->where('id', $positionId)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'position_id' => ['Pozisyon bu firmaya ait değil.'],
            ]);
        }
    }
}

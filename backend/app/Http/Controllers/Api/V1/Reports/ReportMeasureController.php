<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ReportMeasure;
use App\Services\Reports\ReportMeasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReportMeasureController extends BaseController
{
    public function __construct(
        protected ReportMeasureService $measures,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->measures->listFor(
            (int) $this->getCompanyId(),
            $request->query('dataset_key'),
            $request->integer('per_page', 50)
        );

        return $this->paginated($page, 'Ölçü kütüphanesi');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset_key' => 'required|string|max:64',
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'label' => 'required|string|max:255',
            'expression' => 'required|string|max:2000',
            'format' => ['sometimes', Rule::in(['number', 'money', 'percent'])],
            'decimals' => 'sometimes|integer|min:0|max:6',
        ]);

        try {
            $m = $this->measures->create($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($m, 'Ölçü kaydedildi', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $measure = $this->findOwned($id);
        $validated = $request->validate([
            'dataset_key' => 'sometimes|string|max:64',
            'key' => ['sometimes', 'string', 'max:64', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'label' => 'sometimes|string|max:255',
            'expression' => 'sometimes|string|max:2000',
            'format' => ['sometimes', Rule::in(['number', 'money', 'percent'])],
            'decimals' => 'sometimes|integer|min:0|max:6',
        ]);

        try {
            $m = $this->measures->update($measure, $request->user(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($m, 'Ölçü güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        $measure = $this->findOwned($id);
        $this->measures->delete($measure);

        return $this->success(null, 'Ölçü silindi');
    }

    public function validateExpression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => 'required|string|max:64',
            'expression' => 'required|string|max:2000',
        ]);

        $result = $this->measures->validate(
            $request->user(),
            (int) $this->getCompanyId(),
            $validated['dataset'],
            $validated['expression']
        );

        if (! $result['ok']) {
            return $this->error($result['error'] ?? 'Geçersiz ifade', 422, $result);
        }

        return $this->success($result, 'İfade geçerli');
    }

    private function findOwned(int $id): ReportMeasure
    {
        $m = ReportMeasure::query()
            ->where('company_id', $this->getCompanyId())
            ->whereKey($id)
            ->first();
        if (! $m) {
            abort(404, 'Ölçü bulunamadı');
        }

        return $m;
    }
}

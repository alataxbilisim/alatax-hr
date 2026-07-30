<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Http\Controllers\Controller;
use App\Models\LegalHold;
use App\Services\Kvkk\Retention\LegalHoldService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalHoldController extends Controller
{
    use ApiResponse;

    public function __construct(private LegalHoldService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rows = LegalHold::query()
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return $this->paginated($rows, 'Hukuki tutmalar');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'string', 'max:32'],
            'subject_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3'],
            'case_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $hold = $this->service->place(
            (int) $request->user()->company_id,
            $request->user(),
            $data['subject_type'],
            (int) $data['subject_id'],
            $data['reason'],
            $data['case_reference'] ?? null,
        );

        return $this->success($hold, 'Hukuki tutma konuldu', 201);
    }

    public function release(Request $request, int $id): JsonResponse
    {
        $hold = LegalHold::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        return $this->success($this->service->release($hold, $request->user()), 'Hukuki tutma kaldırıldı');
    }
}

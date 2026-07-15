<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Employee;
use App\Services\Onboarding\OffboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OffboardingController extends BaseController
{
    public function __construct(
        protected OffboardingService $offboardingService,
    ) {}

    /**
     * Personel için işten çıkış süreci başlat.
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $employee = Employee::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'termination_reason_code' => 'required|string|max:10',
            'termination_date' => 'required|date',
            'exit_notes' => 'nullable|string|max:5000',
            'template_id' => 'nullable|integer|exists:onboarding_templates,id',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $process = $this->offboardingService->start(
                $employee,
                $validated,
                auth()->id()
            );
        } catch (ValidationException $e) {
            return $this->error(
                collect($e->errors())->flatten()->first() ?? 'İşten çıkış başlatılamadı',
                422,
                $e->errors()
            );
        }

        return $this->success($process, 'İşten çıkış süreci başlatıldı', 201);
    }
}

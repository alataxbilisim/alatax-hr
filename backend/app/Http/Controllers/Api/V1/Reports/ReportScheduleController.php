<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ReportSchedule;
use App\Services\Reports\ReportScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportScheduleController extends BaseController
{
    public function __construct(
        protected ReportScheduleService $schedules,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->schedules->listFor(
            $request->user(),
            (int) $this->getCompanyId(),
            $request->integer('per_page', 20)
        );

        return $this->paginated($page, 'Zamanlanmış raporlar');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'report_id' => 'nullable|integer',
            'dashboard_id' => 'nullable|integer',
            'cadence' => ['required', Rule::in(ReportSchedule::CADENCES)],
            'hour' => 'nullable|integer|min:0|max:23',
            'minute' => 'nullable|integer|min:0|max:59',
            'day' => 'nullable|integer|min:1|max:28',
            'cron_expression' => 'nullable|string|max:64',
            'timezone' => 'nullable|string|max:64',
            'format' => ['nullable', Rule::in(ReportSchedule::FORMATS)],
            'recipients' => 'required|array|min:1',
            'recipients.*.user_id' => 'nullable|integer',
            'recipients.*.role_id' => 'nullable|integer',
            'recipients.*.department_id' => 'nullable|integer',
            'filters' => 'nullable|array',
            'only_if_data' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        try {
            $s = $this->schedules->create($request->user(), (int) $this->getCompanyId(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success($s, 'Zamanlama oluşturuldu', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $schedule = $this->findOwned($request, $id);

        return $this->success($schedule->load(['report', 'dashboard', 'owner']));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $schedule = $this->findOwned($request, $id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'cadence' => ['sometimes', Rule::in(ReportSchedule::CADENCES)],
            'hour' => 'nullable|integer|min:0|max:23',
            'minute' => 'nullable|integer|min:0|max:59',
            'day' => 'nullable|integer|min:1|max:28',
            'cron_expression' => 'nullable|string|max:64',
            'timezone' => 'nullable|string|max:64',
            'format' => ['nullable', Rule::in(ReportSchedule::FORMATS)],
            'recipients' => 'sometimes|array|min:1',
            'recipients.*.user_id' => 'nullable|integer',
            'recipients.*.role_id' => 'nullable|integer',
            'recipients.*.department_id' => 'nullable|integer',
            'filters' => 'nullable|array',
            'only_if_data' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        try {
            $s = $this->schedules->update($schedule, $request->user(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success($s, 'Zamanlama güncellendi');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $schedule = $this->findOwned($request, $id);
        $this->schedules->delete($schedule, $request->user());

        return $this->success(null, 'Zamanlama silindi');
    }

    /**
     * Manuel tetikleme (test / yetkili).
     */
    public function runNow(Request $request, int $id): JsonResponse
    {
        $schedule = $this->findOwned($request, $id);
        $result = $this->schedules->runSchedule($schedule);

        return $this->success($result, 'Zamanlama çalıştırıldı');
    }

    private function findOwned(Request $request, int $id): ReportSchedule
    {
        $user = $request->user();
        $q = ReportSchedule::query()
            ->where('company_id', (int) $this->getCompanyId())
            ->whereKey($id);
        if ($user->type?->value !== 'company_admin') {
            $q->where('owner_id', $user->id);
        }
        $schedule = $q->first();
        if (! $schedule) {
            abort(404, 'Zamanlama bulunamadı');
        }

        return $schedule;
    }
}

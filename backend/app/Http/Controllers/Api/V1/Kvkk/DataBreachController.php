<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\DataBreach;
use App\Services\Kvkk\Breach\DataBreachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DataBreachController extends BaseController
{
    public function __construct(private DataBreachService $service) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->service->summary((int) $this->getCompanyId()), 'İhlal özeti');
    }

    public function index(Request $request): JsonResponse
    {
        $rows = DataBreach::query()
            ->where('company_id', (int) $this->getCompanyId())
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        $hours = $this->service->notifyDeadlineHours();
        $rows->getCollection()->transform(function (DataBreach $b) use ($hours) {
            $arr = $b->toArray();
            $arr['kvkk_deadline_at'] = $b->kvkkDeadlineAt()->toIso8601String();
            $arr['kvkk_deadline_overdue'] = $b->isKvkkDeadlineOverdue();
            $arr['notify_hours_max'] = $hours;

            return $arr;
        });

        return $this->paginated($rows, 'İhlal defteri');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'detected_at' => ['required', 'date'],
            'occurred_at' => ['nullable', 'date'],
            'description' => ['required', 'string', 'min:5'],
            'affected_categories' => ['nullable', 'array'],
            'affected_subject_count' => ['nullable', 'integer', 'min:0'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'root_cause' => ['nullable', 'string'],
            'containment_actions' => ['nullable', 'string'],
        ]);

        $row = $this->service->create((int) $this->getCompanyId(), $request->user(), $data);

        return $this->success($row, 'İhlal kaydı oluşturuldu', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $breach = DataBreach::query()
            ->where('company_id', (int) $this->getCompanyId())
            ->findOrFail($id);

        $data = $request->validate([
            'occurred_at' => ['nullable', 'date'],
            'description' => ['sometimes', 'string', 'min:5'],
            'affected_categories' => ['nullable', 'array'],
            'affected_subject_count' => ['nullable', 'integer', 'min:0'],
            'severity' => ['sometimes', 'in:low,medium,high,critical'],
            'root_cause' => ['nullable', 'string'],
            'containment_actions' => ['nullable', 'string'],
            'status' => ['sometimes', 'string'],
            'notified_kvkk' => ['sometimes', 'boolean'],
            'notified_kvkk_at' => ['nullable', 'date'],
            'notified_subjects' => ['sometimes', 'boolean'],
            'notified_subjects_at' => ['nullable', 'date'],
            'notified_subjects_method' => ['nullable', 'string'],
            'closed_at' => ['nullable', 'date'],
        ]);

        return $this->success($this->service->update($breach, $request->user(), $data), 'İhlal güncellendi');
    }

    public function report(Request $request, int $id): Response
    {
        $breach = DataBreach::query()
            ->where('company_id', (int) $this->getCompanyId())
            ->findOrFail($id);

        return response($this->service->reportHtml($breach), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="veri-ihlali-'.$breach->id.'.html"',
        ]);
    }
}

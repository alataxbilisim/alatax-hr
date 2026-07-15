<?php

namespace App\Http\Controllers\Api\V1\Timesheet;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Services\DataScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeShiftController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $start = $validated['start_date'] ?? now()->startOfWeek()->toDateString();
        $end = $validated['end_date'] ?? now()->endOfWeek()->toDateString();

        $query = EmployeeShift::query()
            ->with(['user:id,name', 'shift:id,name,code,start_time,end_time,color,is_night_shift'])
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('user_id');

        $this->dataScope->scopeForUser($query, $request->user());

        if (! empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        $rows = $query->paginate($validated['per_page'] ?? 100);

        return $this->paginated($rows, 'Vardiya atamaları listelendi');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'shift_id' => 'required|integer|exists:shifts,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if (! $this->dataScope->allowsUserId($request->user(), (int) $validated['user_id'])) {
            return $this->error('Bu personel için vardiya atama yetkiniz yok', 403);
        }

        $shift = Shift::query()->where('id', $validated['shift_id'])->where('is_active', true)->first();
        if (! $shift) {
            return $this->error('Vardiya bulunamadı veya pasif', 422);
        }

        $row = EmployeeShift::query()->updateOrCreate(
            [
                'company_id' => $this->getCompanyId(),
                'user_id' => $validated['user_id'],
                'date' => $validated['date'],
            ],
            [
                'shift_id' => $validated['shift_id'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        ActivityLog::log('assign', $row, 'Vardiya atandı');

        return $this->success($row->load(['user:id,name', 'shift']), 'Vardiya atandı', 201);
    }

    /**
     * Toplu atama: tarih aralığı + user_ids veya department_id.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => 'required|integer|exists:shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if (empty($validated['user_ids']) && empty($validated['department_id'])) {
            return $this->error('user_ids veya department_id gerekli', 422);
        }

        $shift = Shift::query()->where('id', $validated['shift_id'])->where('is_active', true)->first();
        if (! $shift) {
            return $this->error('Vardiya bulunamadı veya pasif', 422);
        }

        $userIds = $validated['user_ids'] ?? [];

        if (! empty($validated['department_id'])) {
            $deptUserIds = Employee::query()
                ->where('company_id', $this->getCompanyId())
                ->where('department_id', $validated['department_id'])
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $userIds = array_values(array_unique(array_merge($userIds, $deptUserIds)));
        }

        $actor = $request->user();
        $allowed = [];
        $denied = [];
        foreach ($userIds as $uid) {
            if ($this->dataScope->allowsUserId($actor, (int) $uid)) {
                $allowed[] = (int) $uid;
            } else {
                $denied[] = (int) $uid;
            }
        }

        if ($allowed === [] && $denied !== []) {
            return $this->error('Kapsam dışındaki personele atama yapılamaz', 403);
        }

        if ($allowed === []) {
            return $this->error('Atanacak personel bulunamadı', 422);
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        if ($start->diffInDays($end) > 62) {
            return $this->error('Tarih aralığı en fazla 62 gün olabilir', 422);
        }

        $created = 0;
        DB::transaction(function () use ($allowed, $start, $end, $validated, &$created): void {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                foreach ($allowed as $uid) {
                    EmployeeShift::query()->updateOrCreate(
                        [
                            'company_id' => $this->getCompanyId(),
                            'user_id' => $uid,
                            'date' => $d->toDateString(),
                        ],
                        [
                            'shift_id' => $validated['shift_id'],
                            'notes' => $validated['notes'] ?? null,
                        ]
                    );
                    $created++;
                }
            }
        });

        ActivityLog::log('bulk_assign', null, "Toplu vardiya ataması: {$created} kayıt", null, [
            'shift_id' => $validated['shift_id'],
            'assigned_users' => count($allowed),
            'denied_users' => $denied,
        ]);

        return $this->success([
            'assigned_count' => $created,
            'user_count' => count($allowed),
            'denied_user_ids' => $denied,
        ], 'Toplu vardiya ataması tamamlandı');
    }

    public function destroy(int $id): JsonResponse
    {
        $row = EmployeeShift::query()->findOrFail($id);

        if (! $this->dataScope->allowsUserId(request()->user(), (int) $row->user_id)) {
            return $this->error('Bu atamayı silme yetkiniz yok', 403);
        }

        ActivityLog::log('delete', $row, 'Vardiya ataması silindi');
        $row->delete();

        return $this->success(null, 'Vardiya ataması silindi');
    }
}

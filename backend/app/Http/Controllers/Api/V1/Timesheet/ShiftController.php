<?php

namespace App\Http\Controllers\Api\V1\Timesheet;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Shift::query()->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $shifts = $query->paginate($request->get('per_page', 50));

        return $this->paginated($shifts, 'Vardiyalar listelendi');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:20',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'break_duration_minutes' => 'nullable|integer|min:0|max:480',
            'color' => 'nullable|string|max:20',
            'is_night_shift' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['company_id'] = $this->getCompanyId();
        $validated['break_duration_minutes'] = $validated['break_duration_minutes'] ?? 0;
        $validated['color'] = $validated['color'] ?? '#3b82f6';
        $validated['is_night_shift'] = $validated['is_night_shift'] ?? false;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $shift = Shift::create($validated);
        ActivityLog::log('create', $shift, 'Vardiya oluşturuldu: '.$shift->name);

        return $this->success($shift, 'Vardiya oluşturuldu', 201);
    }

    public function show(int $id): JsonResponse
    {
        $shift = Shift::query()->findOrFail($id);

        return $this->success($shift, 'Vardiya detayı');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $shift = Shift::query()->findOrFail($id);
        $old = $shift->toArray();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'code' => 'nullable|string|max:20',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'break_duration_minutes' => 'nullable|integer|min:0|max:480',
            'color' => 'nullable|string|max:20',
            'is_night_shift' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $shift->update($validated);
        ActivityLog::log('update', $shift, 'Vardiya güncellendi: '.$shift->name, $old, $shift->fresh()->toArray());

        return $this->success($shift->fresh(), 'Vardiya güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        $shift = Shift::query()->findOrFail($id);

        if ($shift->employeeShifts()->exists()) {
            return $this->error('Ataması olan vardiya silinemez — pasife alın', 422);
        }

        $name = $shift->name;
        ActivityLog::log('delete', $shift, 'Vardiya silindi: '.$name);
        $shift->delete();

        return $this->success(null, 'Vardiya silindi');
    }
}

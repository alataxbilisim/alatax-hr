<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\LeaveType;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveTypeController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $leaveTypes = LeaveType::latest()
            ->paginate($request->get('per_page', 15));

        return $this->success($leaveTypes, 'İzin türleri listelendi');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'is_paid' => 'boolean',
            'default_days' => 'integer|min:0',
            'requires_document' => 'boolean',
            'gender_restriction' => 'in:all,male,female',
            'max_days_at_once' => 'nullable|integer|min:1',
            'min_days_notice' => 'integer|min:0',
            'approval_flow' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $leaveType = LeaveType::create($validated);

        ActivityLog::log('create', $leaveType, 'İzin türü oluşturuldu: ' . $leaveType->name);

        return $this->success($leaveType, 'İzin türü oluşturuldu', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(LeaveType $leaveType): JsonResponse
    {
        return $this->success($leaveType, 'İzin türü detayları');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|nullable|string|max:10',
            'description' => 'sometimes|nullable|string',
            'is_paid' => 'sometimes|boolean',
            'default_days' => 'sometimes|integer|min:0',
            'requires_document' => 'sometimes|boolean',
            'gender_restriction' => 'sometimes|in:all,male,female',
            'max_days_at_once' => 'sometimes|nullable|integer|min:1',
            'min_days_notice' => 'sometimes|integer|min:0',
            'approval_flow' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $leaveType->getOriginal();
        $leaveType->update($validated);

        ActivityLog::log('update', $leaveType, 'İzin türü güncellendi: ' . $leaveType->name, $oldValues, $leaveType->fresh()->toArray());

        return $this->success($leaveType, 'İzin türü güncellendi');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $leaveTypeName = $leaveType->name;
        ActivityLog::log('delete', null, 'İzin türü silindi: ' . $leaveTypeName);
        
        $leaveType->delete();
        return $this->success(null, 'İzin türü silindi');
    }
}

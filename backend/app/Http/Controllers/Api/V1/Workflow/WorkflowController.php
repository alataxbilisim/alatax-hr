<?php

namespace App\Http\Controllers\Api\V1\Workflow;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalStep;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WorkflowController extends BaseController
{
    /**
     * Workflow listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApprovalWorkflow::where('company_id', $this->getCompanyId())
            ->withCount('steps')
            ->with('steps');

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $workflows = $query->orderBy('entity_type')->orderBy('name')->get();

        return $this->success($workflows, 'Onay akışları listelendi');
    }

    /**
     * Workflow detayı
     */
    public function show(int $id): JsonResponse
    {
        $workflow = ApprovalWorkflow::where('company_id', $this->getCompanyId())
            ->with(['steps' => fn($q) => $q->orderBy('step_order')])
            ->findOrFail($id);

        return $this->success($workflow, 'Onay akışı detayı');
    }

    /**
     * Yeni workflow oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'entity_type' => 'required|string|in:leave_request,asset_request,expense_request,training_request,document_approval',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'conditions' => 'nullable|array',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.approver_type' => 'required|string|in:direct_manager,department_head,specific_user,specific_role,hr,cfo,ceo',
            'steps.*.specific_user_id' => 'nullable|exists:users,id',
            'steps.*.specific_role' => 'nullable|string',
            'steps.*.is_required' => 'boolean',
            'steps.*.can_skip' => 'boolean',
            'steps.*.timeout_hours' => 'nullable|integer|min:1',
            'steps.*.timeout_action' => 'nullable|string|in:escalate,auto_approve,auto_reject',
        ]);

        DB::beginTransaction();
        try {
            // Eğer varsayılan olarak işaretlendiyse, diğerlerini güncelle
            if ($validated['is_default'] ?? false) {
                ApprovalWorkflow::where('company_id', $this->getCompanyId())
                    ->where('entity_type', $validated['entity_type'])
                    ->update(['is_default' => false]);
            }

            $workflow = ApprovalWorkflow::create([
                'company_id' => $this->getCompanyId(),
                'name' => $validated['name'],
                'entity_type' => $validated['entity_type'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $validated['is_default'] ?? false,
                'conditions' => $validated['conditions'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Adımları oluştur
            foreach ($validated['steps'] as $index => $stepData) {
                ApprovalStep::create([
                    'approval_workflow_id' => $workflow->id,
                    'step_order' => $index + 1,
                    'name' => $stepData['name'],
                    'approver_type' => $stepData['approver_type'],
                    'specific_user_id' => $stepData['specific_user_id'] ?? null,
                    'specific_role' => $stepData['specific_role'] ?? null,
                    'is_required' => $stepData['is_required'] ?? true,
                    'can_skip' => $stepData['can_skip'] ?? false,
                    'timeout_hours' => $stepData['timeout_hours'] ?? null,
                    'timeout_action' => $stepData['timeout_action'] ?? null,
                ]);
            }

            DB::commit();

            ActivityLog::log('create', $workflow, 'Yeni onay akışı oluşturuldu: ' . $workflow->name);

            $workflow->load('steps');
            return $this->created($workflow, 'Onay akışı oluşturuldu');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Onay akışı oluşturulamadı: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Workflow güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $workflow = ApprovalWorkflow::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'conditions' => 'nullable|array',
            'steps' => 'sometimes|required|array|min:1',
            'steps.*.id' => 'nullable|integer',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.approver_type' => 'required|string|in:direct_manager,department_head,specific_user,specific_role,hr,cfo,ceo',
            'steps.*.specific_user_id' => 'nullable|exists:users,id',
            'steps.*.specific_role' => 'nullable|string',
            'steps.*.is_required' => 'boolean',
            'steps.*.can_skip' => 'boolean',
            'steps.*.timeout_hours' => 'nullable|integer|min:1',
            'steps.*.timeout_action' => 'nullable|string|in:escalate,auto_approve,auto_reject',
        ]);

        DB::beginTransaction();
        try {
            $oldValues = $workflow->toArray();

            // Varsayılan kontrolü
            if (($validated['is_default'] ?? false) && !$workflow->is_default) {
                ApprovalWorkflow::where('company_id', $this->getCompanyId())
                    ->where('entity_type', $workflow->entity_type)
                    ->where('id', '!=', $workflow->id)
                    ->update(['is_default' => false]);
            }

            $workflow->update([
                'name' => $validated['name'] ?? $workflow->name,
                'description' => $validated['description'] ?? $workflow->description,
                'is_active' => $validated['is_active'] ?? $workflow->is_active,
                'is_default' => $validated['is_default'] ?? $workflow->is_default,
                'conditions' => $validated['conditions'] ?? $workflow->conditions,
                'updated_by' => auth()->id(),
            ]);

            // Adımları güncelle
            if (isset($validated['steps'])) {
                // Mevcut adımları sil
                $workflow->steps()->delete();

                // Yeni adımları oluştur
                foreach ($validated['steps'] as $index => $stepData) {
                    ApprovalStep::create([
                        'approval_workflow_id' => $workflow->id,
                        'step_order' => $index + 1,
                        'name' => $stepData['name'],
                        'approver_type' => $stepData['approver_type'],
                        'specific_user_id' => $stepData['specific_user_id'] ?? null,
                        'specific_role' => $stepData['specific_role'] ?? null,
                        'is_required' => $stepData['is_required'] ?? true,
                        'can_skip' => $stepData['can_skip'] ?? false,
                        'timeout_hours' => $stepData['timeout_hours'] ?? null,
                        'timeout_action' => $stepData['timeout_action'] ?? null,
                    ]);
                }
            }

            DB::commit();

            ActivityLog::log('update', $workflow, 'Onay akışı güncellendi', $oldValues, $workflow->toArray());

            $workflow->load('steps');
            return $this->success($workflow, 'Onay akışı güncellendi');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Onay akışı güncellenemedi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Workflow sil
     */
    public function destroy(int $id): JsonResponse
    {
        $workflow = ApprovalWorkflow::where('company_id', $this->getCompanyId())->findOrFail($id);

        // Aktif kullanımda mı kontrol et
        if ($workflow->records()->exists()) {
            return $this->error('Bu akış kullanımda olduğu için silinemez', 400);
        }

        $workflow->delete();

        ActivityLog::log('delete', $workflow, 'Onay akışı silindi: ' . $workflow->name);

        return $this->success(null, 'Onay akışı silindi');
    }

    /**
     * Entity tiplerine göre mevcut workflow'ları getir
     */
    public function getByEntityType(string $entityType): JsonResponse
    {
        $workflows = ApprovalWorkflow::where('company_id', $this->getCompanyId())
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->with('steps')
            ->get();

        return $this->success($workflows, 'Onay akışları listelendi');
    }

    /**
     * Desteklenen entity tiplerini getir
     */
    public function getEntityTypes(): JsonResponse
    {
        return $this->success(ApprovalWorkflow::getEntityTypes(), 'Entity tipleri');
    }

    /**
     * Onaylayıcı tiplerini getir
     */
    public function getApproverTypes(): JsonResponse
    {
        return $this->success(ApprovalStep::getApproverTypes(), 'Onaylayıcı tipleri');
    }
}



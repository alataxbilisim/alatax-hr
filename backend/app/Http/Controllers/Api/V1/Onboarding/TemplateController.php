<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\OnboardingTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OnboardingTemplate::withCount('processes')->latest();

        $type = $request->get('process_type', OnboardingTemplate::TYPE_ONBOARDING);
        if (in_array($type, [OnboardingTemplate::TYPE_ONBOARDING, OnboardingTemplate::TYPE_OFFBOARDING], true)) {
            $query->where('process_type', $type);
        }

        $templates = $query->paginate($request->get('per_page', 15));

        return $this->success($templates, 'Onboarding şablonları listelendi');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'process_type' => 'nullable|in:onboarding,offboarding',
            'tasks' => 'required|array|min:1',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.type' => 'required|in:document_upload,document_fill,training,meeting,system_setup,quiz,custom',
            'tasks.*.is_required' => 'boolean',
            'tasks.*.days_offset' => 'nullable|integer|min:0',
            'tasks.*.action_key' => 'nullable|string|max:64',
            'estimated_days' => 'integer|min:1',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $validated['process_type'] = $validated['process_type'] ?? OnboardingTemplate::TYPE_ONBOARDING;

        // If setting as default, unset other defaults of same type
        if ($validated['is_default'] ?? false) {
            OnboardingTemplate::query()
                ->where('process_type', $validated['process_type'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template = OnboardingTemplate::create($validated);

        ActivityLog::log('create', $template, 'Onboarding şablonu oluşturuldu: '.$template->name);

        return $this->success($template, 'Onboarding şablonu oluşturuldu', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(OnboardingTemplate $template): JsonResponse
    {
        return $this->success($template->loadCount('processes'), 'Onboarding şablonu detayları');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OnboardingTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'process_type' => 'sometimes|in:onboarding,offboarding',
            'tasks' => 'sometimes|required|array|min:1',
            'tasks.*.title' => 'required_with:tasks|string|max:255',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.type' => 'required_with:tasks|in:document_upload,document_fill,training,meeting,system_setup,quiz,custom',
            'tasks.*.is_required' => 'boolean',
            'tasks.*.days_offset' => 'nullable|integer|min:0',
            'tasks.*.action_key' => 'nullable|string|max:64',
            'estimated_days' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        $processType = $validated['process_type'] ?? $template->process_type;

        // If setting as default, unset other defaults of same type
        if (($validated['is_default'] ?? false) && ! $template->is_default) {
            OnboardingTemplate::query()
                ->where('process_type', $processType)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $oldValues = $template->getOriginal();
        $template->update($validated);

        ActivityLog::log('update', $template, 'Onboarding şablonu güncellendi: '.$template->name, $oldValues, $template->fresh()->toArray());

        return $this->success($template, 'Onboarding şablonu güncellendi');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OnboardingTemplate $template): JsonResponse
    {
        if ($template->processes()->active()->exists()) {
            return $this->error('Aktif süreçleri olan şablon silinemez', null, 422);
        }

        $templateName = $template->name;
        ActivityLog::log('delete', null, 'Onboarding şablonu silindi: '.$templateName);

        $template->delete();

        return $this->success(null, 'Onboarding şablonu silindi');
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(OnboardingTemplate $template): JsonResponse
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name.' (Kopya)';
        $newTemplate->is_default = false;
        $newTemplate->save();

        ActivityLog::log('create', $newTemplate, 'Onboarding şablonu kopyalandı: '.$newTemplate->name);

        return $this->success($newTemplate, 'Şablon kopyalandı', 201);
    }
}

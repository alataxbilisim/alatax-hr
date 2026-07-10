<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ApplicationForm;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FormBuilderController extends BaseController
{
    /**
     * Form listesi
     */
    public function index(): JsonResponse
    {
        $forms = ApplicationForm::with(['createdBy'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($form) {
                return [
                    'id' => $form->id,
                    'name' => $form->name,
                    'description' => $form->description,
                    'fields' => $form->fields ?? [],
                    'is_active' => $form->is_active,
                    'created_at' => $form->created_at->toDateTimeString(),
                    'created_by' => $form->createdBy ? [
                        'id' => $form->createdBy->id,
                        'name' => $form->createdBy->name,
                    ] : null,
                ];
            });

        return $this->success($forms);
    }

    /**
     * Yeni form oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'fields' => 'required|array',
            'fields.*.id' => 'required|string',
            'fields.*.type' => 'required|string|in:text,email,phone,textarea,select,checkbox,radio,file,date',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.required' => 'boolean',
            'fields.*.options' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $form = ApplicationForm::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'fields' => $validated['fields'],
            'is_active' => $validated['is_active'] ?? true,
            'company_id' => $this->getCompanyId(),
        ]);

        ActivityLog::log('create', $form, 'Başvuru formu oluşturuldu: ' . $form->name);

        return $this->success($form, 'Form başarıyla oluşturuldu', 201);
    }

    /**
     * Form detayı
     */
    public function show(int $id): JsonResponse
    {
        $form = ApplicationForm::find($id);

        if (!$form) {
            return $this->notFound('Form bulunamadı');
        }

        return $this->success([
            'id' => $form->id,
            'name' => $form->name,
            'description' => $form->description,
            'fields' => $form->fields ?? [],
            'is_active' => $form->is_active,
            'created_at' => $form->created_at->toDateTimeString(),
        ]);
    }

    /**
     * Form güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $form = ApplicationForm::find($id);

        if (!$form) {
            return $this->notFound('Form bulunamadı');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'fields' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $form->toArray();
        $form->update($validated);

        ActivityLog::log('update', $form, 'Başvuru formu güncellendi: ' . $form->name, $oldValues);

        return $this->success($form, 'Form güncellendi');
    }

    /**
     * Form sil
     */
    public function destroy(int $id): JsonResponse
    {
        $form = ApplicationForm::find($id);

        if (!$form) {
            return $this->notFound('Form bulunamadı');
        }

        ActivityLog::log('delete', $form, 'Başvuru formu silindi: ' . $form->name);
        
        $form->delete();

        return $this->success(null, 'Form silindi');
    }
}

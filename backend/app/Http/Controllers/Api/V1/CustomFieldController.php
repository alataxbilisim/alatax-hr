<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\CustomFieldDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomFieldController extends BaseController
{
    /**
     * Özel alan listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomFieldDefinition::where('company_id', $this->getCompanyId());

        // Entity type filtresi
        if ($request->has('entity_type')) {
            $query->forEntity($request->entity_type);
        }

        // Aktif/pasif filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $fields = $query->ordered()->get();

        return $this->success($fields);
    }

    /**
     * Özel alan detayı
     */
    public function show(int $id): JsonResponse
    {
        $field = CustomFieldDefinition::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        return $this->success($field);
    }

    /**
     * Yeni özel alan oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => [
                'required',
                'string',
                Rule::in(array_keys(CustomFieldDefinition::getEntityTypes())),
            ],
            'field_key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('custom_field_definitions')->where(function ($query) use ($request) {
                    return $query->where('company_id', $this->getCompanyId())
                        ->where('entity_type', $request->entity_type);
                }),
            ],
            'field_label' => 'required|string|max:255',
            'field_type' => [
                'required',
                'string',
                Rule::in(array_keys(CustomFieldDefinition::getFieldTypes())),
            ],
            'field_options' => 'nullable|array',
            'field_options.*.value' => 'required_with:field_options|string',
            'field_options.*.label' => 'required_with:field_options|string',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'validation_rules' => 'nullable|array',
            'placeholder' => 'nullable|string|max:255',
            'help_text' => 'nullable|string',
            'default_value' => 'nullable|string',
        ]);

        $field = CustomFieldDefinition::create([
            'company_id' => $this->getCompanyId(),
            'entity_type' => $validated['entity_type'],
            'field_key' => $validated['field_key'],
            'field_label' => $validated['field_label'],
            'field_type' => $validated['field_type'],
            'field_options' => $validated['field_options'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'validation_rules' => $validated['validation_rules'] ?? null,
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
            'default_value' => $validated['default_value'] ?? null,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $field, 'Özel alan oluşturuldu: '.$field->field_label);

        return $this->created($field, 'Özel alan başarıyla oluşturuldu');
    }

    /**
     * Özel alan güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $field = CustomFieldDefinition::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $validated = $request->validate([
            'field_label' => 'sometimes|string|max:255',
            'field_type' => [
                'sometimes',
                'string',
                Rule::in(array_keys(CustomFieldDefinition::getFieldTypes())),
            ],
            'field_options' => 'nullable|array',
            'field_options.*.value' => 'required_with:field_options|string',
            'field_options.*.label' => 'required_with:field_options|string',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'validation_rules' => 'nullable|array',
            'placeholder' => 'nullable|string|max:255',
            'help_text' => 'nullable|string',
            'default_value' => 'nullable|string',
        ]);

        $field->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        ActivityLog::log('update', $field, 'Özel alan güncellendi: '.$field->field_label);

        return $this->success($field, 'Özel alan başarıyla güncellendi');
    }

    /**
     * Özel alan sil
     */
    public function destroy(int $id): JsonResponse
    {
        $field = CustomFieldDefinition::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        ActivityLog::log('delete', $field, 'Özel alan silindi: '.$field->field_label);

        $field->delete();

        return $this->success(null, 'Özel alan başarıyla silindi');
    }

    /**
     * Özel alanları yeniden sırala
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:custom_field_definitions,id',
            'fields.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['fields'] as $fieldData) {
            CustomFieldDefinition::where('company_id', $this->getCompanyId())
                ->where('id', $fieldData['id'])
                ->update(['sort_order' => $fieldData['sort_order']]);
        }

        return $this->success(null, 'Özel alanlar başarıyla sıralandı');
    }

    /**
     * Özel alan tiplerini getir
     */
    public function getFieldTypes(): JsonResponse
    {
        return $this->success(CustomFieldDefinition::getFieldTypes());
    }

    /**
     * Entity tiplerini getir
     */
    public function getEntityTypes(): JsonResponse
    {
        return $this->success(CustomFieldDefinition::getEntityTypes());
    }
}

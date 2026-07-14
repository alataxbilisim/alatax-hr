<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CustomFieldDefinition;
use App\Models\FormDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Form definition okuma / güncelleme (layout + sistem alan metadata override).
 */
class FormDefinitionService
{
    public function __construct(
        protected FormFieldCatalogService $catalog
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getForEntity(string $entityType, int $companyId): array
    {
        $this->assertSupportedEntity($entityType);

        $form = FormDefinition::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->first();

        if (! $form) {
            $form = FormDefinition::withoutGlobalScopes()
                ->whereNull('company_id')
                ->where('entity_type', $entityType)
                ->where('is_active', true)
                ->first();
        }

        $systemFields = $this->catalog->mergedSystemFields($entityType, $companyId)
            ->sortBy(fn (CustomFieldDefinition $f) => [$f->sort_order, $f->field_label])
            ->values();

        $customFields = $this->catalog->companyCustomFields($entityType, $companyId);

        $fields = $systemFields
            ->map(fn (CustomFieldDefinition $f) => $this->catalog->serializeField($f))
            ->concat($customFields->map(fn (CustomFieldDefinition $f) => $this->catalog->serializeField($f)))
            ->values()
            ->all();

        return [
            'id' => $form?->id,
            'company_id' => $form?->company_id ?? $companyId,
            'entity_type' => $entityType,
            'name' => $form?->name ?? ucfirst($entityType),
            'is_active' => $form?->is_active ?? true,
            'is_system_default' => $form === null || $form->company_id === null,
            'layout' => $form?->layout ?? $this->catalog->defaultLayoutFor($entityType),
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateForEntity(string $entityType, int $companyId, array $payload, ?int $userId): array
    {
        $this->assertSupportedEntity($entityType);

        return DB::transaction(function () use ($entityType, $companyId, $payload, $userId) {
            if (isset($payload['fields']) && is_array($payload['fields'])) {
                foreach ($payload['fields'] as $fieldPatch) {
                    $this->applyFieldPatch($entityType, $companyId, $fieldPatch, $userId);
                }
            }

            if (array_key_exists('layout', $payload) || array_key_exists('name', $payload) || array_key_exists('is_active', $payload)) {
                $systemDefault = FormDefinition::withoutGlobalScopes()
                    ->whereNull('company_id')
                    ->where('entity_type', $entityType)
                    ->first();

                $form = FormDefinition::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('entity_type', $entityType)
                    ->first();

                if (! $form) {
                    $form = new FormDefinition([
                        'company_id' => $companyId,
                        'entity_type' => $entityType,
                        'name' => $payload['name'] ?? ($systemDefault?->name ?? 'Personel Formu'),
                        'is_active' => true,
                        'layout' => $systemDefault?->layout ?? $this->catalog->defaultLayoutFor($entityType),
                        'created_by' => $userId,
                    ]);
                }

                if (array_key_exists('name', $payload) && is_string($payload['name'])) {
                    $form->name = $payload['name'];
                }
                if (array_key_exists('is_active', $payload)) {
                    $form->is_active = (bool) $payload['is_active'];
                }
                if (array_key_exists('layout', $payload) && is_array($payload['layout'])) {
                    $form->layout = $payload['layout'];
                }
                $form->updated_by = $userId;
                $form->save();

                ActivityLog::log('update', $form, "Form tanımı güncellendi: {$entityType}");
            }

            return $this->getForEntity($entityType, $companyId);
        });
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function applyFieldPatch(string $entityType, int $companyId, array $patch, ?int $userId): void
    {
        $systemKey = $patch['system_key'] ?? null;
        $fieldKey = $patch['field_key'] ?? null;
        $id = $patch['id'] ?? null;

        // Sistem alanı: silme yok — yalnızca override
        if (! empty($systemKey) || (! empty($patch['is_system']))) {
            $key = $systemKey ?: $fieldKey;
            if (! is_string($key) || $key === '') {
                throw ValidationException::withMessages([
                    'fields' => ['Sistem alanı için system_key zorunludur.'],
                ]);
            }

            if (! empty($patch['delete'])) {
                throw ValidationException::withMessages([
                    'fields' => ['Sistem alanları silinemez; gizleyebilirsiniz (is_hidden).'],
                ]);
            }

            $base = CustomFieldDefinition::withoutGlobalScopes()
                ->whereNull('company_id')
                ->where('entity_type', $entityType)
                ->where('is_system', true)
                ->where(function ($q) use ($key) {
                    $q->where('system_key', $key)->orWhere('field_key', $key);
                })
                ->first();

            if (! $base) {
                throw ValidationException::withMessages([
                    'fields' => ["Bilinmeyen sistem alanı: {$key}"],
                ]);
            }

            $override = CustomFieldDefinition::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('entity_type', $entityType)
                ->where('is_system', true)
                ->where('system_key', $base->system_key)
                ->first();

            if (! $override) {
                $override = $base->replicate(['company_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at']);
                $override->company_id = $companyId;
                $override->created_by = $userId;
            }

            if (array_key_exists('label_override', $patch)) {
                $override->label_override = $patch['label_override'];
            }
            if (array_key_exists('is_hidden', $patch)) {
                $override->is_hidden = (bool) $patch['is_hidden'];
            }
            if (array_key_exists('is_required_override', $patch)) {
                $override->is_required_override = $patch['is_required_override'];
            }
            if (array_key_exists('sort_order', $patch)) {
                $override->sort_order = (int) $patch['sort_order'];
            }
            if (array_key_exists('field_permission', $patch)) {
                $perm = $patch['field_permission'];
                if ($perm !== null && ! in_array($perm, [
                    CustomFieldDefinition::FIELD_PERMISSION_READONLY,
                    CustomFieldDefinition::FIELD_PERMISSION_HIDDEN,
                ], true)) {
                    throw ValidationException::withMessages([
                        'fields' => ['field_permission yalnızca null, readonly veya hidden olabilir.'],
                    ]);
                }
                $override->field_permission = $perm;
            }

            $override->updated_by = $userId;
            $override->save();

            return;
        }

        // Custom alan metadata (label / required / sort) — silme ayrı endpoint
        if ($id) {
            $custom = CustomFieldDefinition::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('entity_type', $entityType)
                ->where('is_system', false)
                ->find($id);

            if (! $custom) {
                throw ValidationException::withMessages([
                    'fields' => ['Özel alan bulunamadı.'],
                ]);
            }

            if (! empty($patch['delete'])) {
                throw ValidationException::withMessages([
                    'fields' => ['Özel alan silme bu uçtan yapılmaz; custom-fields DELETE kullanın.'],
                ]);
            }

            if (array_key_exists('field_label', $patch) && is_string($patch['field_label'])) {
                $custom->field_label = $patch['field_label'];
            }
            if (array_key_exists('label_override', $patch)) {
                $custom->label_override = $patch['label_override'];
            }
            if (array_key_exists('is_hidden', $patch)) {
                $custom->is_hidden = (bool) $patch['is_hidden'];
            }
            if (array_key_exists('is_required', $patch)) {
                $custom->is_required = (bool) $patch['is_required'];
            }
            if (array_key_exists('is_required_override', $patch)) {
                $custom->is_required_override = $patch['is_required_override'];
            }
            if (array_key_exists('sort_order', $patch)) {
                $custom->sort_order = (int) $patch['sort_order'];
            }
            $custom->updated_by = $userId;
            $custom->save();
        }
    }

    /** @var list<string> */
    public const SUPPORTED_ENTITIES = [
        CustomFieldDefinition::ENTITY_EMPLOYEE,
        CustomFieldDefinition::ENTITY_LEAVE_REQUEST,
    ];

    private function assertSupportedEntity(string $entityType): void
    {
        if (! in_array($entityType, self::SUPPORTED_ENTITIES, true)) {
            throw ValidationException::withMessages([
                'entity_type' => ['Desteklenen form tanımları: employee, leave_request.'],
            ]);
        }
    }
}

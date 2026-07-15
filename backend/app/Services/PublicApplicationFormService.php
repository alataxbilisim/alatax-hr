<?php

namespace App\Services;

use App\Enums\JobPositionStatus;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\JobPosition;

/**
 * Public kariyer formu — application_forms.fields + sistem alanları (auth’suz, sızdırma yok).
 */
class PublicApplicationFormService
{
    /** @var list<string> */
    public const SYSTEM_COLUMN_KEYS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'cv',
        'consent_kvkk',
    ];

    /** Sistem + form_data ortak anahtarlar — application_forms custom listesinde tekrarlanmaz. */
    /** @var list<string> */
    public const RESERVED_FIELD_KEYS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'cv',
        'cover_letter',
        'consent_kvkk',
    ];

    public function __construct(
        protected FormFieldCatalogService $catalog,
        protected RequestTypeFormFieldsAdapter $fieldsAdapter,
    ) {}

    /**
     * Aktif ilan + tenant eşleşmesi; bulunamazsa null.
     */
    public function resolveActivePosition(string $companySlug, string $positionSlug): ?JobPosition
    {
        $company = Company::query()->where('slug', $companySlug)->first();
        if ($company === null) {
            return null;
        }

        return JobPosition::query()
            ->with(['form'])
            ->where('slug', $positionSlug)
            ->where('company_id', $company->id)
            ->where('status', JobPositionStatus::Active)
            ->where(function ($q) {
                $q->whereNull('application_deadline')
                    ->orWhere('application_deadline', '>=', now()->toDateString());
            })
            ->first();
    }

    /**
     * Public FormEngine payload (hassas alan yok).
     *
     * @return array<string, mixed>
     */
    public function buildPublicFormPayload(JobPosition $position, Company $company): array
    {
        $form = $position->form;
        $rawFields = is_array($form?->fields) ? $form->fields : [];
        $hasCustomForm = $form !== null && $rawFields !== [];

        $systemFields = [];
        foreach ($this->catalog->jobApplicationSystemFieldCatalog() as $index => $item) {
            $key = $item['system_key'];
            $systemFields[] = [
                'id' => -(100 + $index),
                'company_id' => null,
                'entity_type' => CustomFieldDefinition::ENTITY_JOB_APPLICATION,
                'is_system' => true,
                'system_key' => $key,
                'field_key' => $key,
                'field_label' => $item['field_label'],
                'label_override' => null,
                'effective_label' => $item['field_label'],
                'field_type' => $item['field_type'],
                'field_options' => null,
                'is_required' => (bool) $item['is_required'],
                'is_required_override' => null,
                'effective_required' => (bool) $item['is_required'],
                'is_active' => true,
                'is_hidden' => false,
                'sort_order' => $item['sort_order'],
                'validation_rules' => null,
                'field_permission' => null,
                'placeholder' => null,
                'help_text' => null,
                'default_value' => null,
            ];
        }

        $customNormalized = $this->fieldsAdapter->normalizeFields($rawFields);
        $customFields = [];
        foreach ($customNormalized as $field) {
            if (in_array($field['field_key'], self::RESERVED_FIELD_KEYS, true)) {
                continue;
            }
            $field['entity_type'] = CustomFieldDefinition::ENTITY_JOB_APPLICATION;
            $customFields[] = $field;
        }

        $allFields = array_values(array_merge($systemFields, $customFields));

        $layoutRows = [];
        foreach ($allFields as $i => $f) {
            $layoutRows[] = [
                'sort_order' => $i,
                'fields' => [['field_key' => $f['field_key']]],
            ];
        }

        $layout = [
            'sections' => [
                [
                    'id' => 'candidate',
                    'label' => 'Başvuru',
                    'sort_order' => 0,
                    'rows' => $layoutRows,
                ],
            ],
        ];

        return [
            'has_custom_form' => $hasCustomForm,
            'company' => [
                'slug' => $company->slug,
                'name' => $company->name,
            ],
            'position' => [
                'slug' => $position->slug,
                'title' => $position->title,
                'description' => $position->description,
                'location' => $position->location,
                'department' => $position->department,
            ],
            'application_form' => $hasCustomForm ? [
                'id' => $form->id,
                'name' => $form->name,
            ] : null,
            'definition' => [
                'id' => $form?->id,
                'company_id' => null,
                'entity_type' => CustomFieldDefinition::ENTITY_JOB_APPLICATION,
                'name' => $form?->name ?? 'Başvuru Formu',
                'is_active' => true,
                'is_system_default' => ! $hasCustomForm,
                'layout' => $layout,
                'fields' => $allFields,
            ],
        ];
    }

    /**
     * form_data doğrulama — yalnızca application_forms custom alanları.
     *
     * @return array<string, mixed>
     */
    public function validateCustomFormData(JobPosition $position, mixed $formData): array
    {
        $rawFields = is_array($position->form?->fields) ? $position->form->fields : [];
        if ($rawFields === []) {
            return is_array($formData) ? $formData : [];
        }

        $filtered = [];
        foreach ($this->fieldsAdapter->normalizeFields($rawFields) as $field) {
            if (in_array($field['field_key'], self::RESERVED_FIELD_KEYS, true)) {
                continue;
            }
            $filtered[] = [
                'id' => $field['field_key'],
                'type' => $field['field_type'],
                'label' => $field['effective_label'],
                'required' => $field['effective_required'],
                'options' => $field['field_options'],
            ];
        }

        return $this->fieldsAdapter->validateFormData($filtered, $formData);
    }
}

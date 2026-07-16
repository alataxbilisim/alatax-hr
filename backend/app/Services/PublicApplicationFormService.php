<?php

namespace App\Services;

use App\Enums\JobPositionStatus;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\FormDefinition;
use App\Models\JobPosition;
use Illuminate\Validation\ValidationException;

/**
 * Public kariyer formu — form_definition_id (Form Engine) veya legacy application_forms (Z2).
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
        protected FormDefinitionService $formDefinitions,
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
            ->with(['form', 'formDefinition'])
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
        if ($position->form_definition_id) {
            return $this->buildFromFormDefinition($position, $company);
        }

        return $this->buildFromLegacyApplicationForm($position, $company);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFromFormDefinition(JobPosition $position, Company $company): array
    {
        $definition = $this->formDefinitions->getForEntity(
            CustomFieldDefinition::ENTITY_JOB_APPLICATION,
            (int) $company->id
        );

        // Pozisyonda saklanan id ile çözülen tanım uyumluysa onu kullan; layout şirket tanımından gelir
        if ($position->formDefinition && (int) $definition['id'] !== (int) $position->form_definition_id) {
            $bound = FormDefinition::withoutGlobalScopes()->find($position->form_definition_id);
            if ($bound && is_array($bound->layout)) {
                $definition['id'] = $bound->id;
                $definition['name'] = $bound->name;
                $definition['layout'] = $bound->layout;
                $definition['company_id'] = $bound->company_id;
                $definition['is_system_default'] = $bound->company_id === null;
            }
        }

        $hasCustom = collect($definition['fields'] ?? [])
            ->contains(fn ($f) => empty($f['is_system']));

        return [
            'has_custom_form' => true,
            'form_source' => 'form_definition',
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
            'application_form' => null,
            'definition' => $definition,
            'meta' => [
                'has_company_custom_fields' => $hasCustom,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFromLegacyApplicationForm(JobPosition $position, Company $company): array
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
            'form_source' => 'application_form',
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
     * form_data doğrulama — Form Engine custom alanları veya legacy application_forms.
     *
     * @return array<string, mixed>
     */
    public function validateCustomFormData(JobPosition $position, mixed $formData): array
    {
        if ($position->form_definition_id) {
            return $this->validateAgainstFormDefinition($position, $formData);
        }

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

    /**
     * @return array<string, mixed>
     */
    private function validateAgainstFormDefinition(JobPosition $position, mixed $formData): array
    {
        $data = is_array($formData) ? $formData : [];
        $definition = $this->formDefinitions->getForEntity(
            CustomFieldDefinition::ENTITY_JOB_APPLICATION,
            (int) $position->company_id
        );

        $errors = [];
        foreach ($definition['fields'] ?? [] as $field) {
            if (! empty($field['is_system'])) {
                continue;
            }
            $key = (string) ($field['field_key'] ?? '');
            if ($key === '' || in_array($key, self::RESERVED_FIELD_KEYS, true)) {
                continue;
            }
            $required = (bool) ($field['effective_required'] ?? $field['is_required'] ?? false);
            if ($required && (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '')) {
                $label = (string) ($field['effective_label'] ?? $field['field_label'] ?? $key);
                $errors[$key] = ["{$label} alanı zorunludur."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}

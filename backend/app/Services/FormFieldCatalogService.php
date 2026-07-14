<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\FormDefinition;
use App\Rules\TurkishNationalId;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Form Engine alan kataloğu — sistem (company_id null) + firma override/custom birleşimi.
 * LookupService birleşim desenini izler.
 */
class FormFieldCatalogService
{
    /**
     * Personel formu standart alanları (EmployeeForm ile hizalı).
     * system_key = field_key = API/DB kolon adı (name = portal kullanıcı adı).
     *
     * @return list<array<string, mixed>>
     */
    public function employeeSystemFieldCatalog(): array
    {
        return [
            // Genel
            ['system_key' => 'employee_code', 'field_label' => 'Sicil No', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => true, 'sort_order' => 10, 'section' => 'general'],
            ['system_key' => 'name', 'field_label' => 'Ad Soyad', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => true, 'sort_order' => 20, 'section' => 'general'],
            ['system_key' => 'branch_id', 'field_label' => 'Şube', 'field_type' => CustomFieldDefinition::TYPE_NUMBER, 'is_required' => false, 'sort_order' => 30, 'section' => 'general'],
            ['system_key' => 'department_id', 'field_label' => 'Departman', 'field_type' => CustomFieldDefinition::TYPE_NUMBER, 'is_required' => false, 'sort_order' => 40, 'section' => 'general'],
            ['system_key' => 'manager_id', 'field_label' => 'Yönetici', 'field_type' => CustomFieldDefinition::TYPE_NUMBER, 'is_required' => false, 'sort_order' => 50, 'section' => 'general'],
            ['system_key' => 'position', 'field_label' => 'Pozisyon', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 60, 'section' => 'general'],
            ['system_key' => 'title', 'field_label' => 'Unvan', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 70, 'section' => 'general'],
            ['system_key' => 'status', 'field_label' => 'Durum', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => true, 'sort_order' => 80, 'section' => 'general'],
            // Kişisel
            ['system_key' => 'birth_date', 'field_label' => 'Doğum Tarihi', 'field_type' => CustomFieldDefinition::TYPE_DATE, 'is_required' => false, 'sort_order' => 100, 'section' => 'personal'],
            [
                'system_key' => 'national_id',
                'field_label' => 'TC Kimlik No',
                'field_type' => CustomFieldDefinition::TYPE_TEXT,
                'is_required' => false,
                'sort_order' => 110,
                'section' => 'personal',
                'validation_rules' => ['tckn'],
                'field_permission' => null,
            ],
            ['system_key' => 'gender', 'field_label' => 'Cinsiyet', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 120, 'section' => 'personal'],
            ['system_key' => 'marital_status', 'field_label' => 'Medeni Durum', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 130, 'section' => 'personal'],
            ['system_key' => 'blood_type', 'field_label' => 'Kan Grubu', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 140, 'section' => 'personal'],
            ['system_key' => 'education_level', 'field_label' => 'Eğitim Seviyesi', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 150, 'section' => 'personal'],
            // İletişim
            ['system_key' => 'personal_email', 'field_label' => 'Kişisel E-posta', 'field_type' => CustomFieldDefinition::TYPE_EMAIL, 'is_required' => false, 'sort_order' => 200, 'section' => 'contact'],
            ['system_key' => 'personal_phone', 'field_label' => 'Kişisel Telefon', 'field_type' => CustomFieldDefinition::TYPE_PHONE, 'is_required' => false, 'sort_order' => 210, 'section' => 'contact'],
            ['system_key' => 'address', 'field_label' => 'Adres', 'field_type' => CustomFieldDefinition::TYPE_TEXTAREA, 'is_required' => false, 'sort_order' => 220, 'section' => 'contact'],
            ['system_key' => 'city', 'field_label' => 'İl', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 230, 'section' => 'contact'],
            ['system_key' => 'district', 'field_label' => 'İlçe', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 240, 'section' => 'contact'],
            ['system_key' => 'postal_code', 'field_label' => 'Posta Kodu', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 250, 'section' => 'contact'],
            ['system_key' => 'emergency_contact_name', 'field_label' => 'Acil Durum Kişisi', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 260, 'section' => 'contact'],
            ['system_key' => 'emergency_contact_phone', 'field_label' => 'Acil Durum Telefonu', 'field_type' => CustomFieldDefinition::TYPE_PHONE, 'is_required' => false, 'sort_order' => 270, 'section' => 'contact'],
            ['system_key' => 'emergency_contact_relation', 'field_label' => 'Yakınlık', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 280, 'section' => 'contact'],
            // İş
            ['system_key' => 'hire_date', 'field_label' => 'İşe Giriş Tarihi', 'field_type' => CustomFieldDefinition::TYPE_DATE, 'is_required' => false, 'sort_order' => 300, 'section' => 'work'],
            ['system_key' => 'contract_start_date', 'field_label' => 'Sözleşme Başlangıç', 'field_type' => CustomFieldDefinition::TYPE_DATE, 'is_required' => false, 'sort_order' => 310, 'section' => 'work'],
            ['system_key' => 'contract_end_date', 'field_label' => 'Sözleşme Bitiş', 'field_type' => CustomFieldDefinition::TYPE_DATE, 'is_required' => false, 'sort_order' => 320, 'section' => 'work'],
            ['system_key' => 'contract_type', 'field_label' => 'Sözleşme Tipi', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 330, 'section' => 'work'],
            ['system_key' => 'work_type', 'field_label' => 'Çalışma Tipi', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 340, 'section' => 'work'],
            // Maaş
            ['system_key' => 'gross_salary', 'field_label' => 'Brüt Maaş', 'field_type' => CustomFieldDefinition::TYPE_NUMBER, 'is_required' => false, 'sort_order' => 400, 'section' => 'salary', 'field_permission' => null],
            ['system_key' => 'net_salary', 'field_label' => 'Net Maaş', 'field_type' => CustomFieldDefinition::TYPE_NUMBER, 'is_required' => false, 'sort_order' => 410, 'section' => 'salary'],
            ['system_key' => 'currency', 'field_label' => 'Para Birimi', 'field_type' => CustomFieldDefinition::TYPE_SELECT, 'is_required' => false, 'sort_order' => 420, 'section' => 'salary'],
            ['system_key' => 'bank_name', 'field_label' => 'Banka', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 430, 'section' => 'salary'],
            ['system_key' => 'iban', 'field_label' => 'IBAN', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 440, 'section' => 'salary'],
            // SGK
            ['system_key' => 'sgk_number', 'field_label' => 'SGK Sicil No', 'field_type' => CustomFieldDefinition::TYPE_TEXT, 'is_required' => false, 'sort_order' => 500, 'section' => 'sgk'],
            ['system_key' => 'sgk_start_date', 'field_label' => 'SGK Başlangıç Tarihi', 'field_type' => CustomFieldDefinition::TYPE_DATE, 'is_required' => false, 'sort_order' => 510, 'section' => 'sgk'],
            ['system_key' => 'notes', 'field_label' => 'Notlar', 'field_type' => CustomFieldDefinition::TYPE_TEXTAREA, 'is_required' => false, 'sort_order' => 600, 'section' => 'sgk'],
        ];
    }

    public function defaultEmployeeLayout(): array
    {
        $sections = [
            'general' => ['id' => 'general', 'label' => 'Genel', 'sort_order' => 0, 'rows' => []],
            'personal' => ['id' => 'personal', 'label' => 'Kişisel', 'sort_order' => 1, 'rows' => []],
            'contact' => ['id' => 'contact', 'label' => 'İletişim', 'sort_order' => 2, 'rows' => []],
            'work' => ['id' => 'work', 'label' => 'İş Bilgileri', 'sort_order' => 3, 'rows' => []],
            'salary' => ['id' => 'salary', 'label' => 'Maaş', 'sort_order' => 4, 'rows' => []],
            'sgk' => ['id' => 'sgk', 'label' => 'SGK', 'sort_order' => 5, 'rows' => []],
            'custom' => ['id' => 'custom', 'label' => 'Özel Alanlar', 'sort_order' => 6, 'rows' => []],
        ];

        foreach ($this->employeeSystemFieldCatalog() as $field) {
            $sectionId = $field['section'];
            $sections[$sectionId]['rows'][] = [
                'sort_order' => count($sections[$sectionId]['rows']),
                'fields' => [
                    ['system_key' => $field['system_key']],
                ],
            ];
        }

        return [
            'sections' => array_values($sections),
        ];
    }

    /**
     * Idempotent sistem alan + varsayılan layout seed.
     *
     * @return array{fields: int, layouts: int}
     */
    public function seedSystemCatalog(): array
    {
        $fieldCount = 0;
        foreach ($this->employeeSystemFieldCatalog() as $item) {
            $section = $item['section'];
            unset($item['section']);

            CustomFieldDefinition::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => null,
                    'entity_type' => CustomFieldDefinition::ENTITY_EMPLOYEE,
                    'field_key' => $item['system_key'],
                ],
                [
                    'is_system' => true,
                    'system_key' => $item['system_key'],
                    'field_label' => $item['field_label'],
                    'field_type' => $item['field_type'],
                    'is_required' => $item['is_required'] ?? false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'sort_order' => $item['sort_order'],
                    'validation_rules' => $item['validation_rules'] ?? null,
                    'field_permission' => $item['field_permission'] ?? null,
                    'field_options' => null,
                    'label_override' => null,
                    'is_required_override' => null,
                    'help_text' => $section,
                ]
            );
            $fieldCount++;
        }

        FormDefinition::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => null,
                'entity_type' => CustomFieldDefinition::ENTITY_EMPLOYEE,
            ],
            [
                'name' => 'Personel Formu (Sistem)',
                'is_active' => true,
                'layout' => $this->defaultEmployeeLayout(),
            ]
        );

        return ['fields' => $fieldCount, 'layouts' => 1];
    }

    /**
     * Sistem + firma override birleşik sistem alanları (system_key key).
     *
     * @return Collection<string, CustomFieldDefinition>
     */
    public function mergedSystemFields(string $entityType, ?int $companyId): Collection
    {
        $defaults = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('entity_type', $entityType)
            ->where('is_system', true)
            ->get()
            ->keyBy(fn (CustomFieldDefinition $f) => $f->system_key ?: $f->field_key);

        $overrides = collect();
        if ($companyId !== null) {
            $overrides = CustomFieldDefinition::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('entity_type', $entityType)
                ->where('is_system', true)
                ->get()
                ->keyBy(fn (CustomFieldDefinition $f) => $f->system_key ?: $f->field_key);
        }

        $merged = collect();
        foreach ($defaults as $key => $row) {
            $merged->put($key, $overrides->get($key, $row));
        }
        foreach ($overrides as $key => $row) {
            if (! $merged->has($key)) {
                $merged->put($key, $row);
            }
        }

        return $merged;
    }

    /**
     * Firma custom alanları (is_system=false).
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    public function companyCustomFields(string $entityType, int $companyId): Collection
    {
        return CustomFieldDefinition::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('entity_type', $entityType)
            ->where('is_system', false)
            ->ordered()
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeField(CustomFieldDefinition $field): array
    {
        return [
            'id' => $field->id,
            'company_id' => $field->company_id,
            'entity_type' => $field->entity_type,
            'is_system' => (bool) $field->is_system,
            'system_key' => $field->system_key,
            'field_key' => $field->field_key,
            'field_label' => $field->field_label,
            'label_override' => $field->label_override,
            'effective_label' => $field->effectiveLabel(),
            'field_type' => $field->field_type,
            'field_options' => $field->field_options,
            'is_required' => (bool) $field->is_required,
            'is_required_override' => $field->is_required_override,
            'effective_required' => $field->effectiveRequired(),
            'is_active' => (bool) $field->is_active,
            'is_hidden' => (bool) $field->is_hidden,
            'sort_order' => (int) $field->sort_order,
            'validation_rules' => $field->validation_rules,
            'field_permission' => $field->field_permission,
            'placeholder' => $field->placeholder,
            'help_text' => $field->help_text,
            'default_value' => $field->default_value,
        ];
    }

    /**
     * Standart (sistem) alan değerlerini tanımdaki validation_rules ile doğrula.
     * Yalnızca $data içinde bulunan anahtarlar (strip sonrası çağrılmalı).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function validateStandardFields(string $entityType, ?int $companyId, array $data): void
    {
        $fields = $this->mergedSystemFields($entityType, $companyId)
            ->filter(fn (CustomFieldDefinition $f) => $f->is_active && ! $f->is_hidden);

        $errors = [];

        foreach ($fields as $key => $definition) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            $attribute = $key;

            if ($definition->effectiveRequired() && $this->isEmpty($value)) {
                $errors[$attribute][] = "{$definition->effectiveLabel()} zorunludur.";

                continue;
            }

            if ($this->isEmpty($value)) {
                continue;
            }

            foreach ($this->laravelRulesFromDefinition($definition) as $rule) {
                if ($rule instanceof TurkishNationalId) {
                    $failed = false;
                    $rule->validate($attribute, $value, function (string $message) use (&$failed, &$errors, $attribute) {
                        $failed = true;
                        $errors[$attribute][] = $message;
                    });
                    if ($failed) {
                        break;
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return list<mixed>
     */
    public function laravelRulesFromDefinition(CustomFieldDefinition $definition): array
    {
        $rules = [];
        foreach ($definition->validation_rules ?? [] as $rule) {
            if (! is_string($rule)) {
                continue;
            }
            if ($rule === 'tckn') {
                $rules[] = new TurkishNationalId;

                continue;
            }
            $rules[] = $rule;
        }

        return $rules;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }
}

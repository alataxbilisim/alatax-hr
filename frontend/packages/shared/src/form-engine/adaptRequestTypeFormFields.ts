import type { FormDefinitionPayload, FormFieldMeta, FormLayout } from './types';

/** request_types.form_fields ham öğe (esnek anahtarlar). */
export interface RequestTypeFormFieldRaw {
  id?: string | number;
  name?: string;
  key?: string;
  field_key?: string;
  type?: string;
  label?: string;
  required?: boolean;
  options?: Array<string | { value: string; label?: string }>;
  placeholder?: string;
  default_value?: string | null;
}

const ALLOWED_TYPES = new Set([
  'text',
  'email',
  'phone',
  'textarea',
  'select',
  'checkbox',
  'radio',
  'file',
  'date',
  'number',
]);

function resolveKey(raw: RequestTypeFormFieldRaw, index: number): string {
  if (raw.field_key) return String(raw.field_key);
  if (raw.key) return String(raw.key);
  if (raw.name) return String(raw.name);
  if (raw.id !== undefined && raw.id !== null && String(raw.id) !== '') {
    return String(raw.id);
  }
  return `field_${index}`;
}

function normalizeOptions(
  options: RequestTypeFormFieldRaw['options']
): Array<{ value: string; label: string }> | null {
  if (!options || options.length === 0) return null;
  const out: Array<{ value: string; label: string }> = [];
  for (const opt of options) {
    if (typeof opt === 'string' || typeof opt === 'number') {
      out.push({ value: String(opt), label: String(opt) });
      continue;
    }
    if (typeof opt === 'object' && opt !== null && 'value' in opt) {
      out.push({
        value: String(opt.value),
        label: String(opt.label ?? opt.value),
      });
    }
  }
  return out.length > 0 ? out : null;
}

/**
 * request_types.form_fields → FormEngine FormFieldMeta listesi.
 * Boş/null → boş dizi (form alansız çalışır).
 */
export function adaptRequestTypeFormFields(formFields: unknown): FormFieldMeta[] {
  if (!Array.isArray(formFields) || formFields.length === 0) {
    return [];
  }

  const fields: FormFieldMeta[] = [];
  formFields.forEach((raw, index) => {
    if (!raw || typeof raw !== 'object') return;
    const item = raw as RequestTypeFormFieldRaw;
    const key = resolveKey(item, index);
    let type = (item.type ?? 'text').toLowerCase();
    if (!ALLOWED_TYPES.has(type)) type = 'text';
    const label = item.label ?? key;
    const required = Boolean(item.required);

    fields.push({
      id: -(1000 + index),
      company_id: null,
      entity_type: 'employee_request',
      is_system: false,
      system_key: null,
      field_key: key,
      field_label: label,
      label_override: null,
      effective_label: label,
      field_type: type,
      field_options: normalizeOptions(item.options),
      is_required: required,
      is_required_override: null,
      effective_required: required,
      is_active: true,
      is_hidden: false,
      sort_order: (index + 1) * 10,
      validation_rules: null,
      field_permission: null,
      placeholder: item.placeholder ?? null,
      help_text: null,
      default_value: item.default_value ?? null,
    });
  });

  return fields;
}

/** FormEngine için geçici definition payload. */
export function buildRequestTypeFormDefinition(
  formFields: unknown,
  formName: string
): FormDefinitionPayload | null {
  const fields = adaptRequestTypeFormFields(formFields);
  if (fields.length === 0) {
    return null;
  }

  const layout: FormLayout = {
    sections: [
      {
        id: 'dynamic',
        label: formName,
        sort_order: 0,
        rows: fields.map((f, i) => ({
          sort_order: i,
          fields: [{ field_key: f.field_key }],
        })),
      },
    ],
  };

  return {
    id: null,
    company_id: 0,
    entity_type: 'employee_request',
    name: formName,
    is_active: true,
    is_system_default: true,
    layout,
    fields,
  };
}

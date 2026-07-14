import React from 'react';
import { Controller, type Control, type FieldErrors } from 'react-hook-form';
import { CustomFieldRenderer, type CustomFieldDefinition } from '../components/CustomFieldRenderer';
import { Select, type SelectOption } from '../components/Select';
import type { CustomFieldValue } from '../types/modules';
import type { FormEngineValues, FormFieldMeta } from './types';
import { isFieldReadonly } from './buildZodSchema';

function toCustomValue(value: unknown): CustomFieldValue {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return value;
  }
  if (Array.isArray(value) && value.every((item) => typeof item === 'string')) {
    return value;
  }
  return String(value);
}

interface FormEngineFieldProps {
  field: FormFieldMeta;
  control: Control<FormEngineValues>;
  errors: FieldErrors<FormEngineValues>;
  selectOptions?: SelectOption[];
  readonlyBadge?: string;
  selectPlaceholder?: string;
}

const SELECT_SYSTEM_KEYS = new Set([
  'status',
  'gender',
  'marital_status',
  'blood_type',
  'education_level',
  'contract_type',
  'work_type',
  'currency',
  'emergency_contact_relation',
  'position',
  'department_id',
  'branch_id',
  'manager_id',
]);

function shouldUseSelect(field: FormFieldMeta, hasOptions: boolean): boolean {
  if (!hasOptions) return false;
  if (field.field_type === 'select' || field.field_type === 'lookup') return true;
  if (SELECT_SYSTEM_KEYS.has(field.field_key)) return true;
  return false;
}

export const FormEngineField: React.FC<FormEngineFieldProps> = ({
  field,
  control,
  errors,
  selectOptions,
  readonlyBadge,
  selectPlaceholder,
}) => {
  const readonly = isFieldReadonly(field);
  const key = field.field_key;
  const error = errors[key];
  const errorMessage =
    error && typeof error === 'object' && 'message' in error
      ? String(error.message ?? '')
      : '';

  const options: SelectOption[] =
    selectOptions ??
    (field.field_options ?? []).map((o) => ({ value: o.value, label: o.label }));

  const useSelect = shouldUseSelect(field, options.length > 0);

  return (
    <div className="form-group">
      {useSelect && (
        <label className="form-label" htmlFor={`fe-${key}`}>
          {field.effective_label}
          {field.effective_required && !readonly && (
            <span className="text-danger ms-1">*</span>
          )}
          {readonly && readonlyBadge && (
            <span className="text-muted ms-1" style={{ fontSize: '0.75rem' }}>
              {readonlyBadge}
            </span>
          )}
        </label>
      )}

      <Controller
        name={key}
        control={control}
        render={({ field: rhf }) => {
          if (useSelect) {
            return (
              <Select
                id={`fe-${key}`}
                value={rhf.value === null || rhf.value === undefined ? '' : String(rhf.value)}
                onChange={(v) => {
                  if (key.endsWith('_id')) {
                    rhf.onChange(v ? Number(v) : null);
                  } else {
                    rhf.onChange(v || null);
                  }
                }}
                options={options}
                allowEmpty={!field.effective_required}
                placeholder={selectPlaceholder}
                disabled={readonly}
                error={Boolean(errorMessage)}
                aria-label={field.effective_label}
              />
            );
          }

          if (field.field_type === 'file') {
            return (
              <div>
                <label className="form-label" htmlFor={`fe-${key}`}>
                  {field.effective_label}
                  {field.effective_required && !readonly && (
                    <span className="text-danger ms-1">*</span>
                  )}
                </label>
                <input
                  id={`fe-${key}`}
                  type="file"
                  className="form-control"
                  disabled={readonly}
                  onChange={(e) => {
                    const file = e.target.files?.[0] ?? null;
                    rhf.onChange(file);
                  }}
                />
                {rhf.value instanceof File && (
                  <small className="form-text text-muted">{rhf.value.name}</small>
                )}
              </div>
            );
          }

          const customDef: CustomFieldDefinition = {
            id: field.id,
            field_key: field.field_key,
            field_label: field.effective_label,
            field_type: mapType(field.field_type),
            field_options: field.field_options ?? undefined,
            is_required: field.effective_required && !readonly,
            placeholder: field.placeholder ?? undefined,
            help_text: undefined,
            default_value: field.default_value ?? undefined,
          };

          return (
            <CustomFieldRenderer
              fields={[customDef]}
              values={{ [key]: toCustomValue(rhf.value) }}
              onChange={(_, value) => rhf.onChange(value)}
              readonly={readonly}
            />
          );
        }}
      />

      {field.help_text && !errorMessage && (
        <small className="form-text text-muted">{field.help_text}</small>
      )}
      {errorMessage && (
        <small className="form-text text-danger">{errorMessage}</small>
      )}
    </div>
  );
};

function mapType(fieldType: string): string {
  if (fieldType === 'tckn') return 'text';
  if (fieldType === 'decimal') return 'number';
  if (fieldType === 'lookup') return 'select';
  if (fieldType === 'multiselect') return 'select';
  return fieldType;
}

export default FormEngineField;

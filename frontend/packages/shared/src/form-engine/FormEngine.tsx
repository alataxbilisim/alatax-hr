import React, { useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { SelectOption } from '../components/Select';
import type {
  FormDefinitionPayload,
  FormEngineSubmitPayload,
  FormEngineValues,
  FormFieldMeta,
  FormLayoutSection,
} from './types';
import { buildZodSchema, getVisibleFields, isFieldVisible } from './buildZodSchema';
import { buildSubmitPayload } from './buildSubmitPayload';
import { FormEngineField } from './FormEngineField';

export interface FormEngineProps {
  definition: FormDefinitionPayload;
  defaultValues?: FormEngineValues;
  onSubmit: (payload: FormEngineSubmitPayload, values: FormEngineValues) => void | Promise<void>;
  /** field_key → Select options (lookup / FK) */
  selectOptionsByKey?: Record<string, SelectOption[]>;
  submitLabel?: string;
  cancelLabel?: string;
  savingLabel?: string;
  onCancel?: () => void;
  loading?: boolean;
  messages?: {
    required?: string;
    email?: string;
    tckn?: string;
    number?: string;
    readonlyBadge?: string;
    selectPlaceholder?: string;
  };
}

function fieldByRef(
  fields: FormFieldMeta[],
  ref: { system_key?: string; field_key?: string; custom_field_id?: number }
): FormFieldMeta | undefined {
  if (ref.system_key) {
    return fields.find((f) => f.system_key === ref.system_key || f.field_key === ref.system_key);
  }
  if (ref.custom_field_id) {
    return fields.find((f) => f.id === ref.custom_field_id);
  }
  if (ref.field_key) {
    return fields.find((f) => f.field_key === ref.field_key);
  }
  return undefined;
}

function buildDefaultValues(fields: FormFieldMeta[], initial?: FormEngineValues): FormEngineValues {
  const values: FormEngineValues = { ...(initial ?? {}) };
  for (const field of getVisibleFields(fields)) {
    if (values[field.field_key] === undefined) {
      if (field.default_value !== null && field.default_value !== undefined && field.default_value !== '') {
        values[field.field_key] = field.default_value;
      } else if (field.field_type === 'checkbox') {
        values[field.field_key] = false;
      } else {
        values[field.field_key] = '';
      }
    }
  }
  return values;
}

export const FormEngine: React.FC<FormEngineProps> = ({
  definition,
  defaultValues,
  onSubmit,
  selectOptionsByKey = {},
  submitLabel = 'Kaydet',
  cancelLabel = 'İptal',
  savingLabel = 'Kaydediliyor...',
  onCancel,
  loading = false,
  messages = {},
}) => {
  const msg = useMemo(
    () => ({
      required: messages.required ?? 'Bu alan zorunludur',
      email: messages.email ?? 'Geçerli bir e-posta girin',
      tckn: messages.tckn ?? 'Geçerli bir TCKN giriniz',
      number: messages.number ?? 'Sayısal değer girin',
    }),
    [messages]
  );

  const schema = useMemo(
    () => buildZodSchema(definition.fields, msg),
    [definition.fields, msg]
  );

  const formDefaults = useMemo(
    () => buildDefaultValues(definition.fields, defaultValues),
    [definition.fields, defaultValues]
  );

  const {
    control,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormEngineValues>({
    resolver: zodResolver(schema),
    defaultValues: formDefaults,
  });

  useEffect(() => {
    reset(formDefaults);
  }, [formDefaults, reset]);

  const sections: FormLayoutSection[] = useMemo(() => {
    const layoutSections = [...(definition.layout?.sections ?? [])].sort(
      (a, b) => a.sort_order - b.sort_order
    );
    if (layoutSections.length > 0) return layoutSections;

    // Layout yoksa tek bölüm: tüm görünür alanlar
    return [
      {
        id: 'all',
        label: definition.name,
        sort_order: 0,
        rows: getVisibleFields(definition.fields).map((f, i) => ({
          sort_order: i,
          fields: [{ field_key: f.field_key, system_key: f.system_key ?? undefined }],
        })),
      },
    ];
  }, [definition]);

  const onValid = async (values: FormEngineValues) => {
    const payload = buildSubmitPayload(values, definition.fields);
    await onSubmit(payload, values);
  };

  return (
    <form onSubmit={handleSubmit(onValid)} noValidate>
      {sections.map((section) => {
        const rows = [...section.rows].sort((a, b) => a.sort_order - b.sort_order);
        const sectionFields = rows
          .flatMap((row) =>
            row.fields
              .map((ref) => fieldByRef(definition.fields, ref))
              .filter((f): f is FormFieldMeta => Boolean(f && isFieldVisible(f)))
          );

        if (sectionFields.length === 0) return null;

        return (
          <div key={section.id} className="card" style={{ marginBottom: 'var(--space-4, 1rem)' }}>
            <div className="card-header">
              <h3 className="card-title" style={{ margin: 0 }}>
                {section.label}
              </h3>
            </div>
            <div className="card-body">
              <div className="form-grid form-grid-2">
                {sectionFields.map((field) => (
                  <FormEngineField
                    key={`${field.id}-${field.field_key}`}
                    field={field}
                    control={control}
                    errors={errors}
                    selectOptions={selectOptionsByKey[field.field_key]}
                    readonlyBadge={messages?.readonlyBadge}
                    selectPlaceholder={messages?.selectPlaceholder}
                  />
                ))}
              </div>
            </div>
          </div>
        );
      })}

      <div className="form-actions-sticky">
        {onCancel && (
          <button type="button" className="btn btn-secondary" onClick={onCancel} disabled={loading || isSubmitting}>
            {cancelLabel}
          </button>
        )}
        <button type="submit" className="btn btn-primary" disabled={loading || isSubmitting}>
          {loading || isSubmitting ? savingLabel : submitLabel}
        </button>
      </div>
    </form>
  );
};

export default FormEngine;

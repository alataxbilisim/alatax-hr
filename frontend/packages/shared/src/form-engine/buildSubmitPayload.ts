import type { FormEngineSubmitPayload, FormEngineValues, FormFieldMeta } from './types';
import { isFieldReadonly, isFieldVisible } from './buildZodSchema';

/**
 * Submit payload: hidden alan yok; readonly alan gönderilmez (değişmez).
 * Sistem alanları `system`, custom alanlar `custom_fields`.
 */
export function buildSubmitPayload(
  values: FormEngineValues,
  fields: FormFieldMeta[]
): FormEngineSubmitPayload {
  const system: Record<string, unknown> = {};
  const custom_fields: Record<string, unknown> = {};

  for (const field of fields) {
    if (!isFieldVisible(field)) continue;
    if (isFieldReadonly(field)) continue;

    const key = field.field_key;
    if (!(key in values)) continue;

    const raw = values[key];
    if (field.is_system) {
      system[key] = normalizeValue(raw, field.field_type);
    } else {
      custom_fields[key] = normalizeValue(raw, field.field_type);
    }
  }

  return { system, custom_fields };
}

function normalizeValue(raw: unknown, fieldType: string): unknown {
  if (raw === undefined) return null;
  if (fieldType === 'file') {
    return raw instanceof File ? raw : raw === null || raw === '' ? null : raw;
  }
  if (fieldType === 'number' || fieldType === 'decimal') {
    if (raw === '' || raw === null) return null;
    const n = typeof raw === 'number' ? raw : Number(raw);
    return Number.isFinite(n) ? n : null;
  }
  if (fieldType === 'checkbox') {
    return raw === true || raw === 'true' || raw === '1' || raw === 1;
  }
  return raw;
}

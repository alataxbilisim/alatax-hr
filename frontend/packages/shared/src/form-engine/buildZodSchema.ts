import { z } from 'zod';
import type { FormFieldMeta } from './types';
import { isValidTurkishNationalId } from './tckn';

export function isFieldVisible(field: FormFieldMeta): boolean {
  if (!field.is_active) return false;
  if (field.is_hidden) return false;
  if (field.field_permission === 'hidden') return false;
  return true;
}

export function isFieldReadonly(field: FormFieldMeta): boolean {
  return field.field_permission === 'readonly';
}

/**
 * Görünür alanlardan zod şeması üretir (backend validation_rules ile hizalı).
 */
export function buildZodSchema(
  fields: FormFieldMeta[],
  messages: {
    required: string;
    email: string;
    tckn: string;
    number: string;
  }
): z.ZodObject<Record<string, z.ZodTypeAny>> {
  const shape: Record<string, z.ZodTypeAny> = {};

  for (const field of fields) {
    if (!isFieldVisible(field)) continue;

    const key = field.field_key;
    const required = field.effective_required && !isFieldReadonly(field);
    const rules = field.validation_rules ?? [];
    const hasTckn = rules.includes('tckn') || field.field_type === 'tckn';

    let schema: z.ZodTypeAny;

    switch (field.field_type) {
      case 'number':
      case 'decimal':
        schema = z.union([z.number(), z.string()]).optional().nullable();
        if (required) {
          schema = z.union([
            z.number(),
            z.string().min(1, messages.required),
          ]);
        }
        break;
      case 'checkbox':
        schema = z.union([z.boolean(), z.string()]).optional().nullable();
        break;
      case 'multiselect':
        schema = z.array(z.string()).optional().nullable();
        if (required) {
          schema = z.array(z.string()).min(1, messages.required);
        }
        break;
      case 'file':
        schema = z.union([z.instanceof(File), z.null()]).optional();
        if (required) {
          schema = z.instanceof(File, { message: messages.required });
        }
        break;
      case 'email':
        schema = z.string().optional().nullable().or(z.literal(''));
        if (required) {
          schema = z.string().min(1, messages.required).email(messages.email);
        } else {
          schema = z
            .string()
            .optional()
            .nullable()
            .refine((v) => !v || z.string().email().safeParse(v).success, {
              message: messages.email,
            });
        }
        break;
      default:
        schema = z.union([z.string(), z.number(), z.boolean()]).optional().nullable();
        if (required) {
          schema = z
            .union([z.string(), z.number(), z.boolean()])
            .refine((v) => v !== null && v !== undefined && String(v).trim() !== '', {
              message: messages.required,
            });
        }
        break;
    }

    if (hasTckn) {
      schema = z
        .union([z.string(), z.null(), z.undefined()])
        .refine(
          (v) => {
            if (v === null || v === undefined || v === '') {
              return !required;
            }
            return isValidTurkishNationalId(String(v));
          },
          { message: messages.tckn }
        );
    }

    shape[key] = schema;
  }

  return z.object(shape);
}

export function getVisibleFields(fields: FormFieldMeta[]): FormFieldMeta[] {
  return fields.filter(isFieldVisible).sort((a, b) => a.sort_order - b.sort_order);
}

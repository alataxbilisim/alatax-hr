/** Form Engine — API / layout sözleşmesi (FAZ 4A-2) */

export type FormFieldPermission = 'readonly' | 'hidden' | null;

export interface FormFieldOption {
  value: string;
  label: string;
}

export interface FormFieldMeta {
  id: number;
  company_id: number | null;
  entity_type: string;
  is_system: boolean;
  system_key: string | null;
  field_key: string;
  field_label: string;
  label_override: string | null;
  effective_label: string;
  field_type: string;
  field_options?: FormFieldOption[] | null;
  is_required: boolean;
  is_required_override: boolean | null;
  effective_required: boolean;
  is_active: boolean;
  is_hidden: boolean;
  sort_order: number;
  validation_rules?: string[] | null;
  field_permission: FormFieldPermission;
  placeholder?: string | null;
  help_text?: string | null;
  default_value?: string | null;
}

export interface FormLayoutFieldRef {
  system_key?: string;
  field_key?: string;
  custom_field_id?: number;
}

export interface FormLayoutRow {
  sort_order: number;
  fields: FormLayoutFieldRef[];
}

export interface FormLayoutSection {
  id: string;
  label: string;
  sort_order: number;
  rows: FormLayoutRow[];
}

export interface FormLayout {
  sections: FormLayoutSection[];
}

export interface FormDefinitionPayload {
  id: number | null;
  company_id: number;
  entity_type: string;
  name: string;
  is_active: boolean;
  is_system_default: boolean;
  layout: FormLayout;
  fields: FormFieldMeta[];
}

export type FormEngineValues = Record<string, unknown>;

export interface FormEngineSubmitPayload {
  /** Sistem kolonları (employees tablosu) */
  system: Record<string, unknown>;
  /** Özel alan jsonb */
  custom_fields: Record<string, unknown>;
}

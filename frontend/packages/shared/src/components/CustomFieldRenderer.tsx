import React from 'react';
import type { CustomFieldValue } from '../types/modules';

export interface CustomFieldDefinition {
  id: number;
  field_key: string;
  field_label: string;
  field_type: string;
  field_options?: Array<{ value: string; label: string }>;
  is_required: boolean;
  placeholder?: string;
  help_text?: string;
  default_value?: string;
}

interface CustomFieldRendererProps {
  fields: CustomFieldDefinition[];
  values: Record<string, CustomFieldValue>;
  onChange: (key: string, value: CustomFieldValue) => void;
  readonly?: boolean;
}

function toInputValue(value: CustomFieldValue | undefined, fallback = ''): string | number {
  if (value === null || value === undefined) return fallback;
  if (typeof value === 'boolean' || Array.isArray(value)) return fallback;
  return value;
}

export const CustomFieldRenderer: React.FC<CustomFieldRendererProps> = ({
  fields,
  values,
  onChange,
  readonly = false,
}) => {
  const renderField = (field: CustomFieldDefinition) => {
    const raw = values[field.field_key];
    const value = toInputValue(raw, field.default_value ?? '');

    switch (field.field_type) {
      case 'text':
      case 'email':
      case 'phone':
      case 'url':
        return (
          <input
            type={field.field_type}
            className="form-control"
            value={value}
            onChange={(e) => onChange(field.field_key, e.target.value)}
            placeholder={field.placeholder}
            required={field.is_required}
            disabled={readonly}
          />
        );

      case 'number':
        return (
          <input
            type="number"
            className="form-control"
            value={value}
            onChange={(e) => onChange(field.field_key, e.target.value)}
            placeholder={field.placeholder}
            required={field.is_required}
            disabled={readonly}
          />
        );

      case 'date':
        return (
          <input
            type="date"
            className="form-control"
            value={value}
            onChange={(e) => onChange(field.field_key, e.target.value)}
            required={field.is_required}
            disabled={readonly}
          />
        );

      case 'textarea':
        return (
          <textarea
            className="form-control"
            value={value}
            onChange={(e) => onChange(field.field_key, e.target.value)}
            placeholder={field.placeholder}
            required={field.is_required}
            disabled={readonly}
            rows={4}
          />
        );

      case 'select':
        return (
          <select
            className="form-select"
            value={value}
            onChange={(e) => onChange(field.field_key, e.target.value)}
            required={field.is_required}
            disabled={readonly}
          >
            <option value="">Seçiniz...</option>
            {field.field_options?.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        );

      case 'checkbox':
        return (
          <div className="form-check">
            <input
              type="checkbox"
              className="form-check-input"
              checked={raw === true || raw === 'true' || raw === '1'}
              onChange={(e) => onChange(field.field_key, e.target.checked)}
              disabled={readonly}
            />
            <label className="form-check-label">
              {field.placeholder || 'Evet'}
            </label>
          </div>
        );

      case 'radio':
        return (
          <div>
            {field.field_options?.map((option) => (
              <div key={option.value} className="form-check">
                <input
                  type="radio"
                  className="form-check-input"
                  name={field.field_key}
                  value={option.value}
                  checked={raw === option.value}
                  onChange={(e) => onChange(field.field_key, e.target.value)}
                  disabled={readonly}
                />
                <label className="form-check-label">
                  {option.label}
                </label>
              </div>
            ))}
          </div>
        );

      case 'file':
        return (
          <input
            type="file"
            className="form-control"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) {
                // Şimdilik path/id string; upload sonrası gerçek path yazılacak
                onChange(field.field_key, file.name);
              }
            }}
            disabled={readonly}
          />
        );

      default:
        return (
          <input
            type="text"
            className="form-control"
            value={value}
            onChange={(e) => onChange(field.field_key, e.target.value)}
            placeholder={field.placeholder}
            required={field.is_required}
            disabled={readonly}
          />
        );
    }
  };

  if (fields.length === 0) {
    return null;
  }

  return (
    <>
      {fields.map((field) => (
        <div key={field.id} className="form-group">
          <label className="form-label">
            {field.field_label}
            {field.is_required && <span className="text-danger ms-1">*</span>}
          </label>
          {renderField(field)}
          {field.help_text && (
            <small className="form-text text-muted">{field.help_text}</small>
          )}
        </div>
      ))}
    </>
  );
};

export default CustomFieldRenderer;

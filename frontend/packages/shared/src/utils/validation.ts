/**
 * Form Validation Utilities
 * Standart validation fonksiyonları ve helper'lar
 */

import i18n from '../i18n';

// Validation Rule Types
export type ValidationRule<T = string> = (value: T, formData?: Record<string, unknown>) => string | null;

export interface ValidationSchema {
  [field: string]: ValidationRule[];
}

export interface ValidationErrors {
  [field: string]: string;
}

// ============================================
// COMMON VALIDATION RULES
// ============================================

/**
 * Required field validation
 */
export const required = (message?: string): ValidationRule => 
  (value) => {
    if (value === null || value === undefined || value === '') {
      return message ?? i18n.t('validation:required');
    }
    if (Array.isArray(value) && value.length === 0) {
      return message ?? i18n.t('validation:required');
    }
    return null;
  };

/**
 * Email format validation
 */
export const email = (message?: string): ValidationRule => 
  (value) => {
    if (!value) return null;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(String(value)) ? null : (message ?? i18n.t('validation:email'));
  };

/**
 * Minimum length validation
 */
export const minLength = (min: number, message?: string): ValidationRule => 
  (value) => {
    if (!value) return null;
    const len = String(value).length;
    return len >= min ? null : (message || i18n.t('validation:min_length', { min }));
  };

/**
 * Maximum length validation
 */
export const maxLength = (max: number, message?: string): ValidationRule => 
  (value) => {
    if (!value) return null;
    const len = String(value).length;
    return len <= max ? null : (message || i18n.t('validation:max_length', { max }));
  };

/**
 * Minimum value validation (for numbers)
 */
export const minValue = (min: number, message?: string): ValidationRule<number> => 
  (value) => {
    if (value === null || value === undefined) return null;
    return value >= min ? null : (message || i18n.t('validation:min_value', { min }));
  };

/**
 * Maximum value validation (for numbers)
 */
export const maxValue = (max: number, message?: string): ValidationRule<number> => 
  (value) => {
    if (value === null || value === undefined) return null;
    return value <= max ? null : (message || i18n.t('validation:max_value', { max }));
  };

/**
 * Pattern validation (regex)
 */
export const pattern = (regex: RegExp, message?: string): ValidationRule => 
  (value) => {
    if (!value) return null;
    return regex.test(String(value)) ? null : (message ?? i18n.t('validation:pattern'));
  };

/**
 * Phone number validation (Turkish format)
 */
export const phone = (message?: string): ValidationRule => 
  (value) => {
    if (!value) return null;
    // Turkish phone: 05XX XXX XX XX or +90 5XX XXX XX XX
    const phoneRegex = /^(\+90|0)?[5][0-9]{9}$/;
    const cleaned = String(value).replace(/[\s\-\(\)]/g, '');
    return phoneRegex.test(cleaned) ? null : (message ?? i18n.t('validation:phone'));
  };

/**
 * URL validation
 */
export const url = (message?: string): ValidationRule => 
  (value) => {
    if (!value) return null;
    try {
      new URL(String(value));
      return null;
    } catch {
      return message ?? i18n.t('validation:url');
    }
  };

/**
 * Date validation
 */
export const date = (message = 'Geçerli bir tarih girin'): ValidationRule => 
  (value) => {
    if (!value) return null;
    const d = new Date(String(value));
    return isNaN(d.getTime()) ? message : null;
  };

/**
 * Date must be after another date
 */
export const dateAfter = (otherField: string, message?: string): ValidationRule => 
  (value, formData) => {
    if (!value || !formData || !formData[otherField]) return null;
    const thisDate = new Date(String(value));
    const otherDate = new Date(String(formData[otherField]));
    return thisDate > otherDate ? null : (message || 'Bu tarih diğerinden sonra olmalı');
  };

/**
 * Date must be before another date
 */
export const dateBefore = (otherField: string, message?: string): ValidationRule => 
  (value, formData) => {
    if (!value || !formData || !formData[otherField]) return null;
    const thisDate = new Date(String(value));
    const otherDate = new Date(String(formData[otherField]));
    return thisDate < otherDate ? null : (message || 'Bu tarih diğerinden önce olmalı');
  };

/**
 * Match another field (e.g., password confirmation)
 */
export const matches = (otherField: string, message = 'Alanlar eşleşmiyor'): ValidationRule => 
  (value, formData) => {
    if (!formData) return null;
    return value === formData[otherField] ? null : message;
  };

/**
 * Numeric only validation
 */
export const numeric = (message = 'Sadece sayı girin'): ValidationRule => 
  (value) => {
    if (!value) return null;
    return /^-?\d*\.?\d+$/.test(String(value)) ? null : message;
  };

/**
 * Integer only validation
 */
export const integer = (message = 'Tam sayı girin'): ValidationRule => 
  (value) => {
    if (!value) return null;
    return /^-?\d+$/.test(String(value)) ? null : message;
  };

/**
 * Alphanumeric validation
 */
export const alphanumeric = (message = 'Sadece harf ve rakam kullanın'): ValidationRule => 
  (value) => {
    if (!value) return null;
    return /^[a-zA-Z0-9]+$/.test(String(value)) ? null : message;
  };

/**
 * No whitespace validation
 */
export const noWhitespace = (message = 'Boşluk karakteri kullanılamaz'): ValidationRule => 
  (value) => {
    if (!value) return null;
    return /\s/.test(String(value)) ? message : null;
  };

/**
 * Password strength validation
 */
export const strongPassword = (message?: string): ValidationRule => 
  (value) => {
    if (!value) return null;
    const str = String(value);
    if (str.length < 8) return message || 'Şifre en az 8 karakter olmalı';
    if (!/[A-Z]/.test(str)) return message || 'Şifre en az bir büyük harf içermeli';
    if (!/[a-z]/.test(str)) return message || 'Şifre en az bir küçük harf içermeli';
    if (!/[0-9]/.test(str)) return message || 'Şifre en az bir rakam içermeli';
    return null;
  };

// ============================================
// VALIDATION HELPERS
// ============================================

/**
 * Validate a single field against its rules
 */
export function validateField<T = string>(
  value: T,
  rules: ValidationRule<T>[],
  formData?: Record<string, unknown>
): string | null {
  for (const rule of rules) {
    const error = rule(value, formData);
    if (error) return error;
  }
  return null;
}

/**
 * Validate an entire form against a schema
 */
export function validateForm(
  formData: Record<string, unknown>,
  schema: ValidationSchema
): ValidationErrors {
  const errors: ValidationErrors = {};

  // Null/undefined schema kontrolü
  if (!schema || typeof schema !== 'object') {
    return errors;
  }

  for (const [field, rules] of Object.entries(schema)) {
    const value = formData[field];
    const error = validateField(value as string, rules, formData);
    if (error) {
      errors[field] = error;
    }
  }

  return errors;
}

/**
 * Check if there are any validation errors
 */
export function hasErrors(errors: ValidationErrors): boolean {
  return Object.keys(errors).length > 0;
}

/**
 * Get the first error message from errors object
 */
export function getFirstError(errors: ValidationErrors): string | null {
  const keys = Object.keys(errors);
  return keys.length > 0 ? errors[keys[0]] : null;
}

/**
 * Combine multiple validation rules into one
 */
export function combine<T = string>(...rules: ValidationRule<T>[]): ValidationRule<T> {
  return (value, formData) => {
    for (const rule of rules) {
      const error = rule(value, formData);
      if (error) return error;
    }
    return null;
  };
}

/**
 * Make a rule conditional
 */
export function when<T = string>(
  condition: (value: T, formData?: Record<string, unknown>) => boolean,
  rule: ValidationRule<T>
): ValidationRule<T> {
  return (value, formData) => {
    if (!condition(value, formData)) return null;
    return rule(value, formData);
  };
}


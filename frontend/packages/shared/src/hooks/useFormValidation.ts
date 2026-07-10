/**
 * Form Validation Hook
 * Standart form validation için reusable hook
 */

import { useState, useCallback, useMemo } from 'react';
import { 
  ValidationSchema, 
  ValidationErrors, 
  validateForm, 
  validateField,
  hasErrors 
} from '../utils/validation';

export interface UseFormValidationOptions<T> {
  initialValues: T;
  schema?: ValidationSchema;
  validateOnChange?: boolean;
  validateOnBlur?: boolean;
}

export interface UseFormValidationReturn<T> {
  values: T;
  errors: ValidationErrors;
  touched: Record<keyof T, boolean>;
  isValid: boolean;
  isDirty: boolean;
  
  // Actions
  setFieldValue: <K extends keyof T>(field: K, value: T[K]) => void;
  setFieldTouched: (field: keyof T, touched?: boolean) => void;
  setValues: (values: Partial<T>) => void;
  setErrors: (errors: ValidationErrors) => void;
  
  // Validation
  validateField: (field: keyof T) => string | null;
  validate: () => boolean;
  
  // Form handlers
  handleChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => void;
  handleBlur: (e: React.FocusEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => void;
  
  // Reset
  reset: () => void;
  resetField: (field: keyof T) => void;
  
  // Helpers
  getFieldProps: (field: keyof T) => {
    name: string;
    value: T[keyof T];
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => void;
    onBlur: (e: React.FocusEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => void;
  };
  getFieldError: (field: keyof T) => string | undefined;
  isFieldTouched: (field: keyof T) => boolean;
  isFieldValid: (field: keyof T) => boolean;
}

export function useFormValidation<T extends object>(
  options: UseFormValidationOptions<T>
): UseFormValidationReturn<T> {
  const { 
    initialValues, 
    schema = {}, 
    validateOnChange = false, 
    validateOnBlur = true 
  } = options;

  const [values, setValuesState] = useState<T>(initialValues);
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [touched, setTouched] = useState<Record<keyof T, boolean>>(
    {} as Record<keyof T, boolean>
  );
  const [isDirty, setIsDirty] = useState(false);

  // Set a single field value
  const setFieldValue = useCallback(<K extends keyof T>(field: K, value: T[K]) => {
    setValuesState(prev => ({ ...prev, [field]: value }));
    setIsDirty(true);

    if (validateOnChange) {
      const rules = schema[field as string];
      if (rules) {
        const error = validateField(value as string, rules, { ...values, [field]: value } as Record<string, unknown>);
        setErrors(prev => {
          if (error) {
            return { ...prev, [field]: error };
          }
          const { [field as string]: _, ...rest } = prev;
          return rest;
        });
      }
    }
  }, [schema, validateOnChange, values]);

  // Set field as touched
  const setFieldTouched = useCallback((field: keyof T, isTouched = true) => {
    setTouched(prev => ({ ...prev, [field]: isTouched }));
  }, []);

  // Set multiple values at once
  const setValuesMethod = useCallback((newValues: Partial<T>) => {
    setValuesState(prev => ({ ...prev, ...newValues }));
    setIsDirty(true);
  }, []);

  // Validate a single field
  const validateFieldMethod = useCallback((field: keyof T): string | null => {
    // Schema null/undefined kontrolü
    if (!schema || typeof schema !== 'object') {
      return null;
    }
    
    const rules = schema[field as string];
    if (!rules) return null;

    const error = validateField(values[field] as string, rules, values as Record<string, unknown>);
    setErrors(prev => {
      if (error) {
        return { ...prev, [field]: error };
      }
      const { [field as string]: _, ...rest } = prev;
      return rest;
    });

    return error;
  }, [schema, values]);

  // Validate entire form
  const validate = useCallback((): boolean => {
    const validationErrors = validateForm(values as Record<string, unknown>, schema);
    setErrors(validationErrors);
    
    // Mark all fields as touched
    const allTouched = Object.keys(values).reduce((acc, key) => {
      acc[key as keyof T] = true;
      return acc;
    }, {} as Record<keyof T, boolean>);
    setTouched(allTouched);

    return !hasErrors(validationErrors);
  }, [values, schema]);

  // Handle input change
  const handleChange = useCallback((
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;
    
    const fieldValue = type === 'checkbox' ? checked : 
                       type === 'number' ? (value === '' ? '' : Number(value)) : 
                       value;

    setFieldValue(name as keyof T, fieldValue as T[keyof T]);
  }, [setFieldValue]);

  // Handle input blur
  const handleBlur = useCallback((
    e: React.FocusEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name } = e.target;
    setFieldTouched(name as keyof T, true);

    if (validateOnBlur) {
      validateFieldMethod(name as keyof T);
    }
  }, [setFieldTouched, validateOnBlur, validateFieldMethod]);

  // Reset form to initial values
  const reset = useCallback(() => {
    setValuesState(initialValues);
    setErrors({});
    setTouched({} as Record<keyof T, boolean>);
    setIsDirty(false);
  }, [initialValues]);

  // Reset a single field
  const resetField = useCallback((field: keyof T) => {
    setValuesState(prev => ({ ...prev, [field]: initialValues[field] }));
    setErrors(prev => {
      const { [field as string]: _, ...rest } = prev;
      return rest;
    });
    setTouched(prev => ({ ...prev, [field]: false }));
  }, [initialValues]);

  // Get props for a field (for spreading onto inputs)
  const getFieldProps = useCallback((field: keyof T) => ({
    name: field as string,
    value: values[field],
    onChange: handleChange,
    onBlur: handleBlur,
  }), [values, handleChange, handleBlur]);

  // Get error message for a field (only if touched)
  const getFieldError = useCallback((field: keyof T): string | undefined => {
    return touched[field] ? errors[field as string] : undefined;
  }, [errors, touched]);

  // Check if field is touched
  const isFieldTouched = useCallback((field: keyof T): boolean => {
    return !!touched[field];
  }, [touched]);

  // Check if field is valid (no error)
  const isFieldValid = useCallback((field: keyof T): boolean => {
    return !errors[field as string];
  }, [errors]);

  // Compute isValid
  const isValid = useMemo(() => !hasErrors(errors), [errors]);

  return {
    values,
    errors,
    touched,
    isValid,
    isDirty,
    
    setFieldValue,
    setFieldTouched,
    setValues: setValuesMethod,
    setErrors,
    
    validateField: validateFieldMethod,
    validate,
    
    handleChange,
    handleBlur,
    
    reset,
    resetField,
    
    getFieldProps,
    getFieldError,
    isFieldTouched,
    isFieldValid,
  };
}

export default useFormValidation;


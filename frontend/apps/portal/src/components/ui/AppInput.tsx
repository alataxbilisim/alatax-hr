import React from 'react';

export interface AppInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: boolean;
  hint?: string;
}

const AppInput: React.FC<AppInputProps> = ({
  label,
  error = false,
  hint,
  id,
  className = '',
  ...rest
}) => {
  const inputId = id || rest.name;

  return (
    <div className="portal-input-wrap">
      {label && (
        <label className="portal-input-label" htmlFor={inputId}>
          {label}
        </label>
      )}
      <input
        id={inputId}
        className={`portal-input ${error ? 'portal-input--error' : ''} ${className}`.trim()}
        {...rest}
      />
      {hint && (
        <span className="portal-input-label" style={{ color: error ? 'var(--portal-danger)' : undefined }}>
          {hint}
        </span>
      )}
    </div>
  );
};

export default AppInput;

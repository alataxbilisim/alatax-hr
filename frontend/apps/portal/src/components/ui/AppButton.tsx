import React from 'react';

type Variant = 'primary' | 'ghost' | 'danger';

export interface AppButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: 'md' | 'lg';
  fullWidth?: boolean;
}

const AppButton: React.FC<AppButtonProps> = ({
  variant = 'primary',
  size = 'md',
  fullWidth = false,
  className = '',
  type = 'button',
  children,
  ...rest
}) => {
  const classes = [
    'portal-btn',
    `portal-btn--${variant}`,
    size === 'lg' ? 'portal-btn--lg' : '',
    fullWidth ? 'portal-btn--lg' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <button type={type} className={classes} {...rest}>
      {children}
    </button>
  );
};

export default AppButton;

import React from 'react';
import { Select, type SelectProps } from '@shared/components';

export type AppSelectProps = SelectProps & {
  label?: string;
};

/** Radix Select — mobil dokunma yüksekliği ile sarılı. */
const AppSelect: React.FC<AppSelectProps> = ({ label, className = '', ...rest }) => (
  <div className="portal-input-wrap portal-select-wrap">
    {label && <span className="portal-input-label">{label}</span>}
    <Select className={className} {...rest} />
  </div>
);

export default AppSelect;

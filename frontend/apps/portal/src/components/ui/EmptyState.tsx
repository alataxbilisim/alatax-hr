import React from 'react';

export interface EmptyStateProps {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: React.ReactNode;
}

const EmptyState: React.FC<EmptyStateProps> = ({ icon, title, description, action }) => (
  <div className="portal-empty">
    {icon && <div className="portal-empty__icon">{icon}</div>}
    <h3 className="portal-empty__title">{title}</h3>
    {description && <p className="portal-empty__desc">{description}</p>}
    {action}
  </div>
);

export default EmptyState;

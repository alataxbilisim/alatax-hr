import React from 'react';

export interface AppCardProps {
  title?: string;
  children: React.ReactNode;
  flush?: boolean;
  className?: string;
  action?: React.ReactNode;
}

const AppCard: React.FC<AppCardProps> = ({
  title,
  children,
  flush = false,
  className = '',
  action,
}) => (
  <section className={`portal-card ${flush ? 'portal-card--flush' : ''} ${className}`.trim()}>
    {(title || action) && (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 'var(--sp-2)', marginBottom: flush ? 0 : undefined, padding: flush ? 'var(--sp-4) var(--sp-4) 0' : undefined }}>
        {title ? <h2 className="portal-card__title" style={{ marginBottom: 0 }}>{title}</h2> : <span />}
        {action}
      </div>
    )}
    {flush ? <div style={{ padding: 'var(--sp-2) var(--sp-4) var(--sp-4)' }}>{children}</div> : children}
  </section>
);

export default AppCard;

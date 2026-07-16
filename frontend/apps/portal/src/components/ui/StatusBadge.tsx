import React from 'react';

type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export interface StatusBadgeProps {
  tone?: Tone;
  children: React.ReactNode;
}

const StatusBadge: React.FC<StatusBadgeProps> = ({ tone = 'neutral', children }) => (
  <span className={`portal-badge portal-badge--${tone}`}>{children}</span>
);

export default StatusBadge;

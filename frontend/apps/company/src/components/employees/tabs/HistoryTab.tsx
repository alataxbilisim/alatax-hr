import React from 'react';
import { BsClockHistory, BsPerson } from 'react-icons/bs';

export interface ActivityLog {
  id: number;
  action: string;
  description?: string;
  old_values?: Record<string, unknown>;
  new_values?: Record<string, unknown>;
  created_at: string;
  causer?: {
    id: number;
    name: string;
  };
}

interface HistoryTabProps {
  activities: ActivityLog[];
}

const actionLabels: Record<string, { label: string; color: string }> = {
  create: { label: 'Oluşturuldu', color: 'var(--success)' },
  update: { label: 'Güncellendi', color: 'var(--info)' },
  delete: { label: 'Silindi', color: 'var(--danger)' },
  import: { label: 'Import', color: 'var(--warning)' },
};

const HistoryTab: React.FC<HistoryTabProps> = ({ activities }) => {
  const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('tr-TR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatRelativeTime = (date: string) => {
    const now = new Date();
    const then = new Date(date);
    const diff = now.getTime() - then.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 7) return formatDateTime(date);
    if (days > 0) return `${days} gün önce`;
    if (hours > 0) return `${hours} saat önce`;
    if (minutes > 0) return `${minutes} dakika önce`;
    return 'Az önce';
  };

  if (activities.length === 0) {
    return (
      <div className="card">
        <div className="card-body" style={{ textAlign: 'center', padding: '3rem' }}>
          <BsClockHistory size={48} style={{ color: 'var(--text-tertiary)', marginBottom: '1rem' }} />
          <p style={{ color: 'var(--text-tertiary)' }}>Henüz aktivite kaydı bulunmuyor</p>
        </div>
      </div>
    );
  }

  return (
    <div className="card">
      <div className="card-body" style={{ padding: 0 }}>
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          {activities.map((activity, index) => {
            const actionInfo = actionLabels[activity.action] || { label: activity.action, color: 'var(--text-tertiary)' };
            
            return (
              <div
                key={activity.id}
                style={{
                  display: 'flex',
                  gap: '1rem',
                  padding: '1rem 1.5rem',
                  borderBottom: index < activities.length - 1 ? '1px solid var(--border-color)' : 'none',
                }}
              >
                {/* Timeline Dot */}
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                  <div
                    style={{
                      width: 12,
                      height: 12,
                      borderRadius: '50%',
                      background: actionInfo.color,
                      flexShrink: 0,
                    }}
                  />
                  {index < activities.length - 1 && (
                    <div
                      style={{
                        width: 2,
                        flex: 1,
                        background: 'var(--border-color)',
                        marginTop: '0.5rem',
                      }}
                    />
                  )}
                </div>

                {/* Content */}
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.25rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <span
                        className="badge"
                        style={{
                          background: `${actionInfo.color}20`,
                          color: actionInfo.color,
                        }}
                      >
                        {actionInfo.label}
                      </span>
                      {activity.causer && (
                        <span style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                          <BsPerson size={12} />
                          {activity.causer.name}
                        </span>
                      )}
                    </div>
                    <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', whiteSpace: 'nowrap' }}>
                      {formatRelativeTime(activity.created_at)}
                    </span>
                  </div>

                  {activity.description && (
                    <p style={{ margin: 0, fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
                      {activity.description}
                    </p>
                  )}

                  {/* Değişiklik detayları (opsiyonel) */}
                  {activity.action === 'update' && activity.old_values && activity.new_values && (
                    <div style={{ marginTop: '0.5rem', fontSize: '0.75rem' }}>
                      {Object.keys(activity.new_values)
                        .filter(key => activity.old_values![key] !== activity.new_values![key])
                        .slice(0, 3)
                        .map(key => (
                          <div key={key} style={{ color: 'var(--text-tertiary)' }}>
                            <strong>{key}:</strong> {String(activity.old_values![key] || '-')} → {String(activity.new_values![key] || '-')}
                          </div>
                        ))}
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

export default HistoryTab;


import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from '../i18n';
import { notificationsApi } from '../services/api';
import type { Notification } from '../types';
import { BsBell, BsCheck2All } from 'react-icons/bs';

interface NotificationBellProps {
  /** Dropdown menü class (layout CSS ile uyum) */
  menuClassName?: string;
}

export const NotificationBell: React.FC<NotificationBellProps> = ({ menuClassName }) => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const ref = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await notificationsApi.list({ per_page: 10 });
      const data = response.data.data;
      setItems(data.notifications ?? []);
      setUnreadCount(data.unread_count ?? 0);
    } catch {
      // sessiz — zil kırılmasın
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
    const timer = window.setInterval(() => {
      void load();
    }, 60_000);
    return () => window.clearInterval(timer);
  }, [load]);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);

  const handleOpen = () => {
    setOpen((v) => !v);
    if (!open) {
      void load();
    }
  };

  const handleMarkAll = async () => {
    try {
      await notificationsApi.markAllAsRead();
      setItems((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
      setUnreadCount(0);
    } catch {
      // ignore
    }
  };

  const handleClickItem = async (notif: Notification) => {
    if (!notif.read_at) {
      try {
        await notificationsApi.markAsRead(notif.id);
        setItems((prev) =>
          prev.map((n) => (n.id === notif.id ? { ...n, read_at: new Date().toISOString() } : n))
        );
        setUnreadCount((c) => Math.max(0, c - 1));
      } catch {
        // ignore
      }
    }
    setOpen(false);
    const link = notif.link;
    if (link && link.startsWith('/')) {
      navigate(link);
    }
  };

  return (
    <div className={`dropdown ${open ? 'open' : ''}`} ref={ref} style={{ position: 'relative' }}>
      <button
        type="button"
        className={`header-btn${unreadCount > 0 ? ' has-notification' : ''}`}
        title={t('notifications.title')}
        aria-label={t('notifications.title')}
        onClick={handleOpen}
      >
        <BsBell size={18} />
        {unreadCount > 0 && (
          <span className="header-btn-badge">{unreadCount > 99 ? '99+' : unreadCount}</span>
        )}
      </button>

      {open && (
        <div
          className={menuClassName ?? 'dropdown-menu'}
          style={{
            width: 320,
            right: 0,
            left: 'auto',
            opacity: 1,
            visibility: 'visible',
            transform: 'translateY(4px)',
            maxHeight: 420,
            overflowY: 'auto',
          }}
        >
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              padding: '0.5rem 0.75rem',
              borderBottom: '1px solid var(--border-primary)',
            }}
          >
            <strong style={{ fontSize: 'var(--fs-body)' }}>{t('notifications.title')}</strong>
            {unreadCount > 0 && (
              <button type="button" className="btn btn-sm btn-link" onClick={() => void handleMarkAll()}>
                <BsCheck2All /> {t('notifications.markAllRead')}
              </button>
            )}
          </div>

          {loading && items.length === 0 ? (
            <div style={{ padding: '1rem', color: 'var(--text-tertiary)', textAlign: 'center' }}>
              {t('loading')}
            </div>
          ) : items.length === 0 ? (
            <div style={{ padding: '1.25rem', color: 'var(--text-tertiary)', textAlign: 'center' }}>
              {t('notifications.empty')}
            </div>
          ) : (
            items.map((notif) => (
              <button
                key={notif.id}
                type="button"
                className="dropdown-item"
                onClick={() => void handleClickItem(notif)}
                style={{
                  display: 'block',
                  width: '100%',
                  textAlign: 'left',
                  whiteSpace: 'normal',
                  background: !notif.read_at ? 'var(--primary-soft, transparent)' : 'transparent',
                  borderLeft: !notif.read_at ? '3px solid var(--color-primary, var(--primary))' : '3px solid transparent',
                  padding: '0.75rem',
                }}
              >
                <div style={{ fontWeight: 500, marginBottom: 2 }}>{notif.title || t('notifications.title')}</div>
                <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-secondary)' }}>
                  {notif.message}
                </div>
                {notif.created_at && (
                  <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)', marginTop: 4 }}>
                    {new Date(notif.created_at).toLocaleString('tr-TR')}
                  </div>
                )}
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
};

export default NotificationBell;

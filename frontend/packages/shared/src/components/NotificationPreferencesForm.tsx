import React from 'react';
import { useTranslation } from '../i18n';

export type NotificationChannelGroup = 'approvals' | 'requests' | 'tasks' | 'documents';

export interface NotificationPrefsValue {
  in_app: {
    approvals: boolean;
    requests: boolean;
    tasks: boolean;
    documents: boolean;
  };
  email: {
    approvals: boolean;
    requests: boolean;
    tasks: boolean;
    documents: boolean;
    reminders: boolean;
  };
}

export const defaultNotificationPrefs = (): NotificationPrefsValue => ({
  in_app: {
    approvals: true,
    requests: true,
    tasks: true,
    documents: true,
  },
  email: {
    approvals: true,
    requests: true,
    tasks: true,
    documents: true,
    reminders: false,
  },
});

interface NotificationPreferencesFormProps {
  value: NotificationPrefsValue;
  onChange: (next: NotificationPrefsValue) => void;
  disabled?: boolean;
}

const GROUPS: NotificationChannelGroup[] = ['approvals', 'requests', 'tasks', 'documents'];

/**
 * Ortak bildirim tercih matrisi — kategori × kanal (in-app / e-posta).
 * Güvenlik satırı kilitli (bilgi amaçlı).
 */
export const NotificationPreferencesForm: React.FC<NotificationPreferencesFormProps> = ({
  value,
  onChange,
  disabled = false,
}) => {
  const { t } = useTranslation('common');

  const setInApp = (group: NotificationChannelGroup, checked: boolean) => {
    onChange({
      ...value,
      in_app: { ...value.in_app, [group]: checked },
    });
  };

  const setEmail = (group: NotificationChannelGroup | 'reminders', checked: boolean) => {
    onChange({
      ...value,
      email: { ...value.email, [group]: checked },
    });
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
      <p style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)', margin: 0 }}>
        {t('account.notifMatrixHint')}
      </p>

      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 'var(--fs-body)' }}>
          <thead>
            <tr>
              <th style={{ textAlign: 'left', padding: 'var(--space-2)', color: 'var(--text-secondary)' }}>
                {t('account.notifCategory')}
              </th>
              <th style={{ textAlign: 'center', padding: 'var(--space-2)', color: 'var(--text-secondary)' }}>
                {t('account.notifChannelInApp')}
              </th>
              <th style={{ textAlign: 'center', padding: 'var(--space-2)', color: 'var(--text-secondary)' }}>
                {t('account.notifChannelEmail')}
              </th>
            </tr>
          </thead>
          <tbody>
            {GROUPS.map((group) => (
              <tr key={group}>
                <td style={{ padding: 'var(--space-2)' }}>{t(`account.notifGroup.${group}`)}</td>
                <td style={{ textAlign: 'center', padding: 'var(--space-2)' }}>
                  <input
                    type="checkbox"
                    checked={value.in_app[group]}
                    disabled={disabled}
                    onChange={(e) => setInApp(group, e.target.checked)}
                    aria-label={`${group}-in-app`}
                  />
                </td>
                <td style={{ textAlign: 'center', padding: 'var(--space-2)' }}>
                  <input
                    type="checkbox"
                    checked={value.email[group]}
                    disabled={disabled}
                    onChange={(e) => setEmail(group, e.target.checked)}
                    aria-label={`${group}-email`}
                  />
                </td>
              </tr>
            ))}
            <tr>
              <td style={{ padding: 'var(--space-2)' }}>{t('account.notifGroup.reminders')}</td>
              <td style={{ textAlign: 'center', padding: 'var(--space-2)', color: 'var(--text-tertiary)' }}>
                {t('account.notifAlwaysOn')}
              </td>
              <td style={{ textAlign: 'center', padding: 'var(--space-2)' }}>
                <input
                  type="checkbox"
                  checked={value.email.reminders}
                  disabled={disabled}
                  onChange={(e) => setEmail('reminders', e.target.checked)}
                  aria-label="reminders-email"
                />
              </td>
            </tr>
            <tr>
              <td style={{ padding: 'var(--space-2)' }}>{t('account.notifGroup.security')}</td>
              <td style={{ textAlign: 'center', padding: 'var(--space-2)', color: 'var(--text-tertiary)' }}>
                {t('account.notifLocked')}
              </td>
              <td style={{ textAlign: 'center', padding: 'var(--space-2)', color: 'var(--text-tertiary)' }}>
                {t('account.notifLocked')}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default NotificationPreferencesForm;

import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import {
  notificationTemplatesApi,
  type NotificationTemplateRow,
} from '@shared/services/api';
import toast from 'react-hot-toast';

/**
 * Stüdyo — Bildirim Şablonları (4C-2).
 */
const NotificationTemplatesPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [rows, setRows] = useState<NotificationTemplateRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [editingKey, setEditingKey] = useState<string | null>(null);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await notificationTemplatesApi.list();
      const list = res.data.data.templates;
      setRows(Array.isArray(list) ? list : []);
    } catch {
      toast.error(t('notifTemplates.loadError'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const startEdit = (row: NotificationTemplateRow) => {
    setEditingKey(row.event_key);
    setSubject(row.override?.subject ?? row.default_subject);
    setBody(row.override?.body ?? row.default_body);
  };

  const handleSave = async () => {
    if (!editingKey) return;
    setSaving(true);
    try {
      await notificationTemplatesApi.upsert(editingKey, { subject, body });
      toast.success(t('notifTemplates.saveSuccess'));
      setEditingKey(null);
      await load();
    } catch {
      toast.error(t('notifTemplates.saveError'));
    } finally {
      setSaving(false);
    }
  };

  const handleReset = async (eventKey: string) => {
    setSaving(true);
    try {
      await notificationTemplatesApi.clear(eventKey);
      toast.success(t('notifTemplates.resetSuccess'));
      if (editingKey === eventKey) setEditingKey(null);
      await load();
    } catch {
      toast.error(t('notifTemplates.resetError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)' }}>
        <h1 className="page-title">{t('notifTemplates.listTitle')}</h1>
        <p className="page-subtitle">{t('notifTemplates.listSubtitle')}</p>
      </div>

      {loading ? (
        <p>{t('loading')}</p>
      ) : (
        <div className="card">
          <div className="card-body" style={{ overflowX: 'auto' }}>
            <table className="table" style={{ width: '100%' }}>
              <thead>
                <tr>
                  <th>{t('notifTemplates.event')}</th>
                  <th>{t('notifTemplates.group')}</th>
                  <th>{t('notifTemplates.variables')}</th>
                  <th>{t('notifTemplates.subject')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <React.Fragment key={row.event_key}>
                    <tr>
                      <td>
                        <code>{row.event_key}</code>
                        <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
                          {row.override
                            ? t('notifTemplates.hasOverride')
                            : t('notifTemplates.systemDefault')}
                        </div>
                      </td>
                      <td>{row.group}</td>
                      <td style={{ fontSize: 'var(--fs-caption)' }}>
                        {row.variables.map((v) => `{{${v}}}`).join(', ')}
                      </td>
                      <td style={{ maxWidth: 240 }}>
                        {row.override?.subject ?? row.default_subject}
                      </td>
                      <td style={{ whiteSpace: 'nowrap' }}>
                        <button
                          type="button"
                          className="btn btn-secondary btn-sm"
                          onClick={() => startEdit(row)}
                        >
                          {t('notifTemplates.edit')}
                        </button>
                        {row.override ? (
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            style={{ marginLeft: 'var(--space-2)' }}
                            disabled={saving}
                            onClick={() => void handleReset(row.event_key)}
                          >
                            {t('notifTemplates.reset')}
                          </button>
                        ) : null}
                      </td>
                    </tr>
                    {editingKey === row.event_key ? (
                      <tr>
                        <td colSpan={5}>
                          <div
                            style={{
                              display: 'flex',
                              flexDirection: 'column',
                              gap: 'var(--space-2)',
                              padding: 'var(--space-3)',
                              background: 'var(--bg-secondary)',
                              borderRadius: 'var(--radius-md)',
                            }}
                          >
                            <label className="form-label" htmlFor={`subj-${row.event_key}`}>
                              {t('notifTemplates.subject')}
                            </label>
                            <input
                              id={`subj-${row.event_key}`}
                              className="form-control"
                              value={subject}
                              onChange={(e) => setSubject(e.target.value)}
                            />
                            <label className="form-label" htmlFor={`body-${row.event_key}`}>
                              {t('notifTemplates.body')}
                            </label>
                            <textarea
                              id={`body-${row.event_key}`}
                              className="form-control"
                              rows={4}
                              value={body}
                              onChange={(e) => setBody(e.target.value)}
                            />
                            <p style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
                              {t('notifTemplates.defaultPreview')}: {row.default_subject}
                            </p>
                            <div style={{ display: 'flex', gap: 'var(--space-2)' }}>
                              <button
                                type="button"
                                className="btn btn-primary btn-sm"
                                disabled={saving}
                                onClick={() => void handleSave()}
                              >
                                {t('notifTemplates.save')}
                              </button>
                              <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={() => setEditingKey(null)}
                              >
                                {t('notifTemplates.cancel')}
                              </button>
                            </div>
                          </div>
                        </td>
                      </tr>
                    ) : null}
                  </React.Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

export default NotificationTemplatesPage;

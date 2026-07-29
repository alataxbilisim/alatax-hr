import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { reportAccessLogsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { Link } from 'react-router-dom';

interface AccessLogRow {
  id: number;
  user_id: number;
  action: string;
  dataset_key?: string | null;
  row_count: number;
  contains_sensitive: boolean;
  sensitive_fields?: string[] | null;
  filters_hash?: string | null;
  created_at: string;
  user?: { id: number; name: string; email: string };
}

function isAccessLogRow(value: unknown): value is AccessLogRow {
  if (typeof value !== 'object' || value === null) return false;
  return 'id' in value && 'action' in value && typeof value.id === 'number';
}

const ReportAccessLogsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [items, setItems] = useState<AccessLogRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [onlySensitive, setOnlySensitive] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await reportAccessLogsApi.list({
        per_page: 50,
        contains_sensitive: onlySensitive ? true : undefined,
      });
      const raw: unknown = res.data.data;
      let list: AccessLogRow[] = [];
      if (Array.isArray(raw)) list = raw.filter(isAccessLogRow);
      else if (typeof raw === 'object' && raw !== null && 'data' in raw && Array.isArray(raw.data)) {
        list = raw.data.filter(isAccessLogRow);
      }
      setItems(list);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportAccessLogs.loadError')));
    } finally {
      setLoading(false);
    }
  }, [onlySensitive, t]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="page-container animate-fade-in">
      <div className="page-header">
        <Link to="/audit-logs" className="btn btn-ghost btn-sm">
          ← {t('reportAccessLogs.back')}
        </Link>
        <h1 className="page-title">{t('reportAccessLogs.title')}</h1>
        <p className="page-subtitle">{t('reportAccessLogs.subtitle')}</p>
      </div>
      <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)', marginBottom: 'var(--sp-3)' }}>
        <input
          type="checkbox"
          checked={onlySensitive}
          onChange={(e) => setOnlySensitive(e.target.checked)}
        />
        {t('reportAccessLogs.onlySensitive')}
      </label>
      {loading ? (
        <p>{t('loading')}</p>
      ) : items.length === 0 ? (
        <p>{t('reportAccessLogs.empty')}</p>
      ) : (
        <table className="data-table">
          <thead>
            <tr>
              <th>{t('reportAccessLogs.colWhen')}</th>
              <th>{t('reportAccessLogs.colUser')}</th>
              <th>{t('reportAccessLogs.colAction')}</th>
              <th>{t('reportAccessLogs.colDataset')}</th>
              <th>{t('reportAccessLogs.colRows')}</th>
              <th>{t('reportAccessLogs.colSensitive')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((row) => (
              <tr key={row.id}>
                <td>{new Date(row.created_at).toLocaleString()}</td>
                <td>{row.user?.name ?? row.user_id}</td>
                <td>{row.action}</td>
                <td>{row.dataset_key ?? '—'}</td>
                <td>{row.row_count}</td>
                <td>{row.contains_sensitive ? t('reportAccessLogs.yes') : t('reportAccessLogs.no')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default ReportAccessLogsPage;

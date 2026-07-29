import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks';
import {
  reportsApi,
  type SavedReportPayload,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { BsPlus, BsPencil, BsTrash, BsPlay, BsCopy } from 'react-icons/bs';
import {
  filterReportsByTab,
  isSavedReportPayload,
  type ListTab,
} from './reportHelpers';
import './reports.css';

interface AuthState {
  auth: {
    user: { id: number } | null;
  };
}

const ReportsListPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const { canCreate, canEdit, canDelete, hasPermission } = usePermission();
  const userId = useSelector((s: AuthState) => s.auth.user?.id ?? 0);

  const canCreateR = canCreate('reports', 'definitions');
  const canEditR = canEdit('reports', 'definitions');
  const canDeleteR = canDelete('reports', 'definitions');
  const canRun = hasPermission('reports', 'definitions', 'run');

  const [tab, setTab] = useState<ListTab>('mine');
  const [search, setSearch] = useState('');
  const [items, setItems] = useState<SavedReportPayload[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await reportsApi.list({ per_page: 100 });
      const raw: unknown = res.data.data;
      const list = Array.isArray(raw)
        ? raw.filter(isSavedReportPayload)
        : Array.isArray((raw as { data?: unknown } | null)?.data)
          ? ((raw as { data: unknown[] }).data).filter(isSavedReportPayload)
          : [];
      setItems(list);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.loadError')));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const filtered = useMemo(() => {
    const byTab = filterReportsByTab(items, tab, userId);
    const q = search.trim().toLowerCase();
    if (!q) return byTab;
    return byTab.filter(
      (r) =>
        r.name.toLowerCase().includes(q) ||
        (r.description ?? '').toLowerCase().includes(q) ||
        (r.dataset_key ?? '').toLowerCase().includes(q)
    );
  }, [items, tab, userId, search]);

  const handleDelete = async (id: number) => {
    if (!window.confirm(t('reportEngine.deleteConfirm'))) return;
    try {
      await reportsApi.remove(id);
      toast.success(t('reportEngine.deleteSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.deleteError')));
    }
  };

  const handleCopy = async (report: SavedReportPayload) => {
    if (!canCreateR || !report.dataset_key) return;
    try {
      await reportsApi.create({
        name: `${report.name} (${t('reportEngine.copySuffix')})`,
        description: report.description,
        dataset_key: report.dataset_key,
        config: report.config ?? undefined,
        is_shared: false,
      });
      toast.success(t('reportEngine.copySuccess'));
      setTab('mine');
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.copyError')));
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('reportEngine.listTitle')}</h1>
          <p className="page-subtitle">{t('reportEngine.listSubtitle')}</p>
        </div>
        <div className="page-header-actions">
          {canCreateR ? (
            <Link to="/reports/new" className="btn btn-primary">
              <BsPlus /> {t('reportEngine.newReport')}
            </Link>
          ) : null}
        </div>
      </div>

      <div className="report-tabs" role="tablist">
        {(
          [
            ['mine', 'reportEngine.tabMine'],
            ['shared', 'reportEngine.tabShared'],
            ['system', 'reportEngine.tabSystem'],
          ] as const
        ).map(([key, labelKey]) => (
          <button
            key={key}
            type="button"
            role="tab"
            className={tab === key ? 'is-active' : ''}
            onClick={() => setTab(key)}
          >
            {t(labelKey)}
          </button>
        ))}
      </div>

      <div style={{ marginBottom: 'var(--sp-3)' }}>
        <input
          type="search"
          className="form-control"
          placeholder={t('reportEngine.searchPlaceholder')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          style={{ maxWidth: 360 }}
        />
      </div>

      {loading ? (
        <p style={{ color: 'var(--text-secondary)' }}>{t('loading')}</p>
      ) : filtered.length === 0 ? (
        <p style={{ color: 'var(--text-tertiary)' }}>{t('reportEngine.empty')}</p>
      ) : (
        <table className="report-list-table">
          <thead>
            <tr>
              <th>{t('reportEngine.colName')}</th>
              <th>{t('reportEngine.colDataset')}</th>
              <th>{t('reportEngine.colUpdated')}</th>
              <th>{t('reportEngine.colActions')}</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((r) => (
              <tr key={r.id}>
                <td>
                  <strong>{r.name}</strong>
                  {r.description ? (
                    <div style={{ color: 'var(--text-tertiary)', fontSize: 'var(--fs-caption)' }}>
                      {r.description}
                    </div>
                  ) : null}
                </td>
                <td>{r.dataset_key ?? '—'}</td>
                <td>
                  {r.updated_at
                    ? new Date(r.updated_at).toLocaleString('tr-TR')
                    : '—'}
                </td>
                <td>
                  <div className="report-list-actions">
                    {canRun ? (
                      <button
                        type="button"
                        className="btn btn-sm btn-ghost"
                        title={t('reportEngine.run')}
                        onClick={() => navigate(`/reports/${r.id}`)}
                      >
                        <BsPlay />
                      </button>
                    ) : null}
                    {canEditR && !r.is_system && r.user_id === userId ? (
                      <button
                        type="button"
                        className="btn btn-sm btn-ghost"
                        title={t('reportEngine.edit')}
                        onClick={() => navigate(`/reports/${r.id}/edit`)}
                      >
                        <BsPencil />
                      </button>
                    ) : null}
                    {canCreateR ? (
                      <button
                        type="button"
                        className="btn btn-sm btn-ghost"
                        title={t('reportEngine.copy')}
                        onClick={() => void handleCopy(r)}
                      >
                        <BsCopy />
                      </button>
                    ) : null}
                    {canDeleteR && !r.is_system && (r.user_id === userId || canDeleteR) ? (
                      <button
                        type="button"
                        className="btn btn-sm btn-ghost"
                        title={t('reportEngine.delete')}
                        onClick={() => void handleDelete(r.id)}
                      >
                        <BsTrash />
                      </button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default ReportsListPage;

import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks';
import { dashboardsApi, type DashboardPayload } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { BsPlus, BsTrash } from 'react-icons/bs';
import {
  filterDashboardsByTab,
  isDashboardPayload,
  type DashboardListTab,
} from './dashboardHelpers';
import './dashboards.css';

interface AuthState {
  auth: {
    user: { id: number } | null;
  };
}

const DashboardsListPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const moduleKey = searchParams.get('module_key') ?? undefined;
  const { canCreate, canDelete } = usePermission();
  const userId = useSelector((s: AuthState) => s.auth.user?.id ?? 0);

  const canCreateD = canCreate('reports', 'dashboards');
  const canDeleteD = canDelete('reports', 'dashboards');

  const [tab, setTab] = useState<DashboardListTab>('mine');
  const [items, setItems] = useState<DashboardPayload[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await dashboardsApi.list({ per_page: 100, module_key: moduleKey });
      const raw: unknown = res.data.data;
      let list: DashboardPayload[] = [];
      if (Array.isArray(raw)) {
        list = raw.filter(isDashboardPayload);
      } else if (typeof raw === 'object' && raw !== null && 'data' in raw) {
        const inner = raw.data;
        if (Array.isArray(inner)) list = inner.filter(isDashboardPayload);
      }
      setItems(list);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.loadError')));
    } finally {
      setLoading(false);
    }
  }, [t, moduleKey]);

  useEffect(() => {
    void load();
  }, [load]);

  const filtered = useMemo(
    () => filterDashboardsByTab(items, tab, userId),
    [items, tab, userId]
  );

  const handleCreate = async () => {
    if (!canCreateD || creating) return;
    try {
      setCreating(true);
      const res = await dashboardsApi.create({
        name: t('dashboards.newName'),
        layout: { widgets: [] },
        global_filters: { fields: ['hire_date', 'department_id', 'branch_id'] },
      });
      const created = res.data.data;
      if (isDashboardPayload(created)) {
        toast.success(t('dashboards.createSuccess'));
        navigate(`/dashboards/${created.id}?edit=1`);
        return;
      }
      toast.error(t('dashboards.createError'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.createError')));
    } finally {
      setCreating(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!canDeleteD) return;
    if (!window.confirm(t('dashboards.deleteConfirm'))) return;
    try {
      await dashboardsApi.remove(id);
      toast.success(t('dashboards.deleteSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.deleteError')));
    }
  };

  return (
    <div className="dash-page page-container">
      <div className="dash-toolbar">
        <div>
          <h1 className="page-title">{t('dashboards.listTitle')}</h1>
          <p className="page-subtitle">{t('dashboards.listSubtitle')}</p>
        </div>
        {canCreateD ? (
          <button type="button" className="btn btn-primary" onClick={() => void handleCreate()} disabled={creating}>
            <BsPlus /> {t('dashboards.new')}
          </button>
        ) : null}
      </div>

      <div className="dash-tabs">
        {(
          [
            { key: 'mine', labelKey: 'dashboards.tabMine' },
            { key: 'shared', labelKey: 'dashboards.tabShared' },
            { key: 'system', labelKey: 'dashboards.tabSystem' },
          ] satisfies { key: DashboardListTab; labelKey: string }[]
        ).map(({ key, labelKey }) => (
          <button
            key={key}
            type="button"
            className={`btn btn-sm ${tab === key ? 'btn-primary' : 'btn-ghost'}`}
            onClick={() => setTab(key)}
          >
            {t(labelKey)}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="dash-empty">{t('loading')}</div>
      ) : filtered.length === 0 ? (
        <div className="dash-empty">{t('dashboards.empty')}</div>
      ) : (
        <table className="data-table">
          <thead>
            <tr>
              <th>{t('dashboards.colName')}</th>
              <th>{t('dashboards.colUpdated')}</th>
              <th>{t('dashboards.colActions')}</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((d) => (
              <tr key={d.id}>
                <td>
                  <Link to={`/dashboards/${d.id}`}>{d.name}</Link>
                  {d.description ? (
                    <div className="dash-widget__meta">{d.description}</div>
                  ) : null}
                </td>
                <td>{d.updated_at ? new Date(d.updated_at).toLocaleString() : '—'}</td>
                <td>
                  <button
                    type="button"
                    className="btn btn-sm btn-ghost"
                    onClick={() => navigate(`/dashboards/${d.id}`)}
                  >
                    {t('dashboards.open')}
                  </button>
                  {canDeleteD && d.owner_id === userId && !d.is_system ? (
                    <button
                      type="button"
                      className="btn btn-sm btn-ghost"
                      onClick={() => void handleDelete(d.id)}
                      aria-label={t('dashboards.delete')}
                    >
                      <BsTrash />
                    </button>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default DashboardsListPage;

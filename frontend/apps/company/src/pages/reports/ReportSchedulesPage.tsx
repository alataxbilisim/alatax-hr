import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks';
import {
  reportsApi,
  type ReportSchedulePayload,
  type SavedReportPayload,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import './reports.css';

function isSchedule(v: unknown): v is ReportSchedulePayload {
  return typeof v === 'object' && v !== null && 'id' in v && 'name' in v && 'cadence' in v;
}

function isSavedReport(v: unknown): v is SavedReportPayload {
  return typeof v === 'object' && v !== null && 'id' in v && 'name' in v && 'dataset_key' in v;
}

const ReportSchedulesPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { canCreate, canEdit, canDelete, hasPermission } = usePermission();
  const canView = hasPermission('reports', 'schedules', 'view');
  const canCreateS = canCreate('reports', 'schedules');
  const canEditS = canEdit('reports', 'schedules');
  const canDeleteS = canDelete('reports', 'schedules');

  const [items, setItems] = useState<ReportSchedulePayload[]>([]);
  const [reports, setReports] = useState<SavedReportPayload[]>([]);
  const [loading, setLoading] = useState(true);
  const [name, setName] = useState('');
  const [reportId, setReportId] = useState<number | ''>('');
  const [cadence, setCadence] = useState<'daily' | 'weekly' | 'monthly'>('daily');
  const [hour, setHour] = useState(8);

  const load = useCallback(async () => {
    if (!canView) return;
    try {
      setLoading(true);
      const [schedRes, repRes] = await Promise.all([
        reportsApi.schedules.list({ per_page: 100 }),
        reportsApi.list({ per_page: 100 }),
      ]);
      const rawSched: unknown = schedRes.data.data;
      const schedList = Array.isArray(rawSched)
        ? rawSched.filter(isSchedule)
        : Array.isArray((rawSched as { data?: unknown } | null)?.data)
          ? ((rawSched as { data: unknown[] }).data).filter(isSchedule)
          : [];
      setItems(schedList);

      const rawRep: unknown = repRes.data.data;
      const repList = Array.isArray(rawRep)
        ? rawRep.filter(isSavedReport)
        : Array.isArray((rawRep as { data?: unknown } | null)?.data)
          ? ((rawRep as { data: unknown[] }).data).filter(isSavedReport)
          : [];
      setReports(repList);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportSchedules.loadError')));
    } finally {
      setLoading(false);
    }
  }, [canView, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleCreatePrompt = async () => {
    if (!canCreateS || name.trim() === '' || reportId === '') {
      toast.error(t('reportSchedules.validation'));
      return;
    }
    const raw = window.prompt(t('reportSchedules.recipientUserIdPrompt'));
    const uid = raw ? Number(raw) : NaN;
    if (!Number.isFinite(uid) || uid <= 0) {
      toast.error(t('reportSchedules.validation'));
      return;
    }
    try {
      await reportsApi.schedules.create({
        name: name.trim(),
        report_id: reportId,
        cadence,
        hour,
        minute: 0,
        format: 'link',
        recipients: [{ user_id: uid }],
      });
      toast.success(t('reportSchedules.createSuccess'));
      setName('');
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportSchedules.createError')));
    }
  };

  const handleRun = async (id: number) => {
    if (!canEditS) return;
    try {
      await reportsApi.schedules.runNow(id);
      toast.success(t('reportSchedules.runSuccess'));
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportSchedules.runError')));
    }
  };

  const handleDelete = async (id: number) => {
    if (!canDeleteS) return;
    if (!window.confirm(t('reportSchedules.deleteConfirm'))) return;
    try {
      await reportsApi.schedules.remove(id);
      toast.success(t('reportSchedules.deleteSuccess'));
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportSchedules.deleteError')));
    }
  };

  if (!canView) {
    return <div className="report-page">{t('reportSchedules.forbidden')}</div>;
  }

  return (
    <div className="report-page">
      <div className="report-page__header">
        <div>
          <h1>{t('reportSchedules.title')}</h1>
          <p className="report-page__subtitle">{t('reportSchedules.subtitle')}</p>
        </div>
        <Link to="/reports" className="btn btn--ghost">
          {t('reportSchedules.backToReports')}
        </Link>
      </div>

      {canCreateS && (
        <div className="report-card" style={{ marginBottom: 'var(--space-4)' }}>
          <h2>{t('reportSchedules.new')}</h2>
          <div className="report-form-row">
            <label>
              {t('reportSchedules.name')}
              <input value={name} onChange={(e) => setName(e.target.value)} />
            </label>
            <label>
              {t('reportSchedules.report')}
              <select
                value={reportId === '' ? '' : String(reportId)}
                onChange={(e) => setReportId(e.target.value ? Number(e.target.value) : '')}
              >
                <option value="">{t('reportSchedules.selectReport')}</option>
                {reports.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              {t('reportSchedules.cadence')}
              <select
                value={cadence}
                onChange={(e) => {
                  const v = e.target.value;
                  if (v === 'daily' || v === 'weekly' || v === 'monthly') setCadence(v);
                }}
              >
                <option value="daily">{t('reportSchedules.cadenceDaily')}</option>
                <option value="weekly">{t('reportSchedules.cadenceWeekly')}</option>
                <option value="monthly">{t('reportSchedules.cadenceMonthly')}</option>
              </select>
            </label>
            <label>
              {t('reportSchedules.hour')}
              <input
                type="number"
                min={0}
                max={23}
                value={hour}
                onChange={(e) => setHour(Number(e.target.value))}
              />
            </label>
            <button type="button" className="btn btn--primary" onClick={() => void handleCreatePrompt()}>
              {t('reportSchedules.create')}
            </button>
          </div>
          <p className="report-page__hint">{t('reportSchedules.linkOnlyHint')}</p>
        </div>
      )}

      {loading ? (
        <p>{t('loading')}</p>
      ) : items.length === 0 ? (
        <p>{t('reportSchedules.empty')}</p>
      ) : (
        <table className="data-table">
          <thead>
            <tr>
              <th>{t('reportSchedules.colName')}</th>
              <th>{t('reportSchedules.colCadence')}</th>
              <th>{t('reportSchedules.colStatus')}</th>
              <th>{t('reportSchedules.colNext')}</th>
              <th>{t('reportSchedules.colActions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((s) => (
              <tr key={s.id}>
                <td>{s.name}</td>
                <td>{s.cadence}</td>
                <td>
                  {s.active === false
                    ? t('reportSchedules.inactive')
                    : (s.last_status ?? '—')}
                </td>
                <td>{s.next_run_at ?? '—'}</td>
                <td>
                  {canEditS && (
                    <button type="button" className="btn btn--sm" onClick={() => void handleRun(s.id)}>
                      {t('reportSchedules.runNow')}
                    </button>
                  )}
                  {canDeleteS && (
                    <button
                      type="button"
                      className="btn btn--sm btn--danger"
                      onClick={() => void handleDelete(s.id)}
                    >
                      {t('reportSchedules.delete')}
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default ReportSchedulesPage;

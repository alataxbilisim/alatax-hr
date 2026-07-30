import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { kvkkApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { usePermission } from '@shared/hooks';
import toast from 'react-hot-toast';

type Tab = 'inventory' | 'notices' | 'consents' | 'requests';

const TAB_KEYS: Tab[] = ['inventory', 'notices', 'consents', 'requests'];

interface ActivityRow {
  id: number;
  key: string;
  name: string;
  legal_basis: string;
  data_subject_group: string;
  transfer_abroad: boolean;
  transfer_abroad_note?: string | null;
  purpose?: string | null;
}

interface NoticeRow {
  id: number;
  audience: string;
  version: number;
  title: string;
  is_active: boolean;
  published_at?: string | null;
}

interface ConsentRow {
  id: number;
  subject_type: string;
  subject_id: number;
  consent_type: string;
  granted: boolean;
  withdrawn_at?: string | null;
  granted_at?: string | null;
}

interface MissingRow {
  user_id: number;
  employee_id: number;
  employee_code?: string | null;
  notice_version: number;
}

interface RequestRow {
  id: number;
  applicant_name: string;
  status: string;
  due_date?: string | null;
  days_remaining?: number;
  is_overdue?: boolean;
  identity_verified: boolean;
  request_types: string[];
}

interface RequestSummary {
  open_count: number;
  due_soon_count: number;
  overdue_count: number;
  avg_response_days: number | null;
}

function isActivityRow(v: unknown): v is ActivityRow {
  return typeof v === 'object' && v !== null && 'id' in v && 'name' in v;
}

function isNoticeRow(v: unknown): v is NoticeRow {
  return typeof v === 'object' && v !== null && 'id' in v && 'version' in v;
}

function isConsentRow(v: unknown): v is ConsentRow {
  return typeof v === 'object' && v !== null && 'id' in v && 'consent_type' in v;
}

function isMissingRow(v: unknown): v is MissingRow {
  return typeof v === 'object' && v !== null && 'user_id' in v && 'employee_id' in v;
}

function isRequestRow(v: unknown): v is RequestRow {
  return typeof v === 'object' && v !== null && 'id' in v && 'applicant_name' in v && 'status' in v;
}

function isRequestSummary(v: unknown): v is RequestSummary {
  return typeof v === 'object' && v !== null && 'open_count' in v;
}

const KvkkPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { canEdit, hasPermission } = usePermission();
  const canEditKvkk = canEdit('management', 'kvkk');
  const canViewRequests = hasPermission('management', 'kvkk_requests', 'view')
    || hasPermission('management', 'kvkk', 'view');
  const canRespondRequests = hasPermission('management', 'kvkk_requests', 'respond');

  const [tab, setTab] = useState<Tab>('inventory');
  const [loading, setLoading] = useState(true);
  const [activities, setActivities] = useState<ActivityRow[]>([]);
  const [notices, setNotices] = useState<NoticeRow[]>([]);
  const [consents, setConsents] = useState<ConsentRow[]>([]);
  const [missing, setMissing] = useState<MissingRow[]>([]);
  const [requests, setRequests] = useState<RequestRow[]>([]);
  const [requestSummary, setRequestSummary] = useState<RequestSummary | null>(null);
  const [selectedRequestId, setSelectedRequestId] = useState<number | null>(null);
  const [responseBody, setResponseBody] = useState('');

  const [draftTitle, setDraftTitle] = useState('');
  const [draftBody, setDraftBody] = useState('');
  const [draftAudience, setDraftAudience] = useState('employee');

  const load = useCallback(async () => {
    try {
      setLoading(true);
      if (tab === 'inventory') {
        const res = await kvkkApi.activities.list({ per_page: 100 });
        const raw: unknown = res.data.data;
        setActivities(Array.isArray(raw) ? raw.filter(isActivityRow) : []);
      } else if (tab === 'notices') {
        const res = await kvkkApi.notices.list({ per_page: 100 });
        const raw: unknown = res.data.data;
        setNotices(Array.isArray(raw) ? raw.filter(isNoticeRow) : []);
      } else if (tab === 'requests') {
        const [listRes, sumRes] = await Promise.all([
          kvkkApi.dataSubjectRequests.list({ per_page: 50 }),
          kvkkApi.dataSubjectRequests.summary(),
        ]);
        const raw: unknown = listRes.data.data;
        setRequests(Array.isArray(raw) ? raw.filter(isRequestRow) : []);
        const sum: unknown = sumRes.data.data;
        setRequestSummary(isRequestSummary(sum) ? sum : null);
      } else {
        const [cRes, mRes] = await Promise.all([
          kvkkApi.consents.list({ per_page: 100 }),
          kvkkApi.consents.missing(),
        ]);
        const cRaw: unknown = cRes.data.data;
        const mRaw: unknown = mRes.data.data;
        setConsents(Array.isArray(cRaw) ? cRaw.filter(isConsentRow) : []);
        setMissing(Array.isArray(mRaw) ? mRaw.filter(isMissingRow) : []);
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('kvkk.loadError')));
    } finally {
      setLoading(false);
    }
  }, [tab, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const exportInventory = async () => {
    try {
      const res = await kvkkApi.activities.export();
      const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'kvkk_veri_envanteri.csv';
      a.click();
      URL.revokeObjectURL(url);
      toast.success(t('kvkk.exportSuccess'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('kvkk.saveError')));
    }
  };

  const createDraft = async () => {
    try {
      await kvkkApi.notices.create({
        audience: draftAudience,
        title: draftTitle,
        body: draftBody,
      });
      toast.success(t('kvkk.saveSuccess'));
      setDraftTitle('');
      setDraftBody('');
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('kvkk.saveError')));
    }
  };

  const publishNotice = async (id: number) => {
    try {
      await kvkkApi.notices.publish(id);
      toast.success(t('kvkk.saveSuccess'));
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('kvkk.saveError')));
    }
  };

  const verifyRequest = async (id: number) => {
    try {
      await kvkkApi.dataSubjectRequests.verifyIdentity(id, { verification_method: 'manual_id_check' });
      toast.success(t('kvkk.requests.verifySuccess'));
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('kvkk.saveError')));
    }
  };

  const buildExport = async (id: number) => {
    try {
      const res = await kvkkApi.dataSubjectRequests.buildExport(id);
      const uuid = res.data.data?.package?.uuid;
      toast.success(t('kvkk.requests.exportReady'));
      if (typeof uuid === 'string') {
        const dl = await kvkkApi.dataSubjectRequests.downloadExport(id, uuid);
        const blob = new Blob([dl.data], { type: 'application/zip' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kvkk-paket-${uuid}.zip`;
        a.click();
        URL.revokeObjectURL(url);
      }
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('kvkk.saveError')));
    }
  };

  const respondRequest = async (id: number, template: 'accept' | 'partial' | 'reject') => {
    try {
      await kvkkApi.dataSubjectRequests.respond(id, {
        template,
        body: responseBody || t('kvkk.requests.defaultResponse'),
      });
      toast.success(t('kvkk.saveSuccess'));
      setSelectedRequestId(null);
      setResponseBody('');
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('kvkk.saveError')));
    }
  };

  return (
    <div className="page-container animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('kvkk.title')}</h1>
          <p className="page-subtitle">{t('kvkk.subtitle')}</p>
          <p className="text-muted" style={{ fontSize: 'var(--fs-sm)' }}>
            {t('kvkk.legalDisclaimer')}
          </p>
        </div>
      </div>

      <div className="tabs" style={{ display: 'flex', gap: 'var(--sp-2)', marginBottom: 'var(--sp-4)' }}>
        {TAB_KEYS.map((key) => (
          <button
            key={key}
            type="button"
            className={`btn btn--sm ${tab === key ? 'btn--primary' : 'btn--ghost'}`}
            onClick={() => setTab(key)}
          >
            {t(`kvkk.tabs.${key}`)}
          </button>
        ))}
      </div>

      {loading ? (
        <p>{t('loading')}</p>
      ) : null}

      {!loading && tab === 'inventory' ? (
        <div>
          <p style={{ marginBottom: 'var(--sp-3)', color: 'var(--color-text-muted)' }}>
            {t('kvkk.onPremNote')}
          </p>
          {canEditKvkk ? (
            <button type="button" className="btn btn--sm btn--ghost" onClick={() => void exportInventory()}>
              {t('kvkk.export')}
            </button>
          ) : null}
          <div className="table-wrap" style={{ marginTop: 'var(--sp-3)' }}>
            <table className="data-table">
              <thead>
                <tr>
                  <th>{t('kvkk.activityName')}</th>
                  <th>{t('kvkk.legalBasis')}</th>
                  <th>{t('kvkk.subjectGroup')}</th>
                  <th>{t('kvkk.transferAbroad')}</th>
                </tr>
              </thead>
              <tbody>
                {activities.map((a) => (
                  <tr key={a.id}>
                    <td>{a.name}</td>
                    <td>{a.legal_basis}</td>
                    <td>{a.data_subject_group}</td>
                    <td>{a.transfer_abroad ? '✓' : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {!loading && tab === 'notices' ? (
        <div>
          <p className="text-muted" style={{ marginBottom: 'var(--sp-3)' }}>
            {t('kvkk.publishedImmutable')}
          </p>
          {canEditKvkk ? (
            <div
              style={{
                display: 'grid',
                gap: 'var(--sp-2)',
                marginBottom: 'var(--sp-4)',
                maxWidth: '40rem',
              }}
            >
              <select
                className="form-control"
                value={draftAudience}
                onChange={(e) => setDraftAudience(e.target.value)}
              >
                <option value="employee">employee</option>
                <option value="candidate">candidate</option>
                <option value="visitor">visitor</option>
                <option value="contractor">contractor</option>
              </select>
              <input
                className="form-control"
                placeholder={t('kvkk.noticeTitle')}
                value={draftTitle}
                onChange={(e) => setDraftTitle(e.target.value)}
              />
              <textarea
                className="form-control"
                rows={5}
                placeholder={t('kvkk.noticeBody')}
                value={draftBody}
                onChange={(e) => setDraftBody(e.target.value)}
              />
              <button type="button" className="btn btn--primary" onClick={() => void createDraft()}>
                {t('kvkk.newDraft')}
              </button>
            </div>
          ) : null}
          <ul style={{ listStyle: 'none', padding: 0 }}>
            {notices.map((n) => (
              <li
                key={n.id}
                style={{
                  padding: 'var(--sp-3)',
                  borderBottom: '1px solid var(--color-border)',
                  display: 'flex',
                  justifyContent: 'space-between',
                  gap: 'var(--sp-3)',
                }}
              >
                <div>
                  <strong>
                    {n.title} — {t('kvkk.version', { version: n.version })}
                  </strong>
                  <div className="text-muted">
                    {n.audience}
                    {n.is_active ? ' · active' : ''}
                    {n.published_at ? ` · ${n.published_at}` : ' · draft'}
                  </div>
                </div>
                {canEditKvkk && !n.published_at ? (
                  <button
                    type="button"
                    className="btn btn--sm btn--primary"
                    onClick={() => void publishNotice(n.id)}
                  >
                    {t('kvkk.publish')}
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {!loading && tab === 'consents' ? (
        <div>
          <h2 style={{ fontSize: 'var(--fs-lg)', marginBottom: 'var(--sp-2)' }}>
            {t('kvkk.missingList')}
          </h2>
          <ul style={{ marginBottom: 'var(--sp-4)' }}>
            {missing.length === 0 ? <li>—</li> : null}
            {missing.map((m) => (
              <li key={`${m.user_id}-${m.employee_id}`}>
                {m.employee_code ?? m.employee_id} (user {m.user_id}) — v{m.notice_version}
              </li>
            ))}
          </ul>
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>{t('kvkk.consentType')}</th>
                  <th>subject</th>
                  <th>{t('kvkk.granted')}</th>
                  <th>{t('kvkk.withdrawn')}</th>
                </tr>
              </thead>
              <tbody>
                {consents.map((c) => (
                  <tr key={c.id}>
                    <td>{c.consent_type}</td>
                    <td>
                      {c.subject_type}:{c.subject_id}
                    </td>
                    <td>{c.granted ? '✓' : '—'}</td>
                    <td>{c.withdrawn_at ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {!loading && tab === 'requests' && canViewRequests ? (
        <div>
          {requestSummary ? (
            <div
              style={{
                display: 'flex',
                gap: 'var(--sp-4)',
                marginBottom: 'var(--sp-4)',
                flexWrap: 'wrap',
              }}
            >
              <span>{t('kvkk.requests.open')}: {requestSummary.open_count}</span>
              <span>{t('kvkk.requests.dueSoon')}: {requestSummary.due_soon_count}</span>
              <span style={{ color: 'var(--color-danger, #b91c1c)' }}>
                {t('kvkk.requests.overdue')}: {requestSummary.overdue_count}
              </span>
              <span>
                {t('kvkk.requests.avgDays')}: {requestSummary.avg_response_days ?? '—'}
              </span>
            </div>
          ) : null}
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{t('kvkk.requests.applicant')}</th>
                  <th>{t('kvkk.requests.status')}</th>
                  <th>{t('kvkk.requests.due')}</th>
                  <th>{t('kvkk.requests.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {requests.map((r) => (
                  <tr key={r.id} style={r.is_overdue ? { background: 'var(--color-danger-bg, #fef2f2)' } : undefined}>
                    <td>{r.id}</td>
                    <td>{r.applicant_name}</td>
                    <td>{r.status}</td>
                    <td>
                      {r.due_date}
                      {typeof r.days_remaining === 'number'
                        ? ` (${r.days_remaining} ${t('kvkk.requests.daysLeft')})`
                        : ''}
                    </td>
                    <td style={{ display: 'flex', gap: 'var(--sp-1)', flexWrap: 'wrap' }}>
                      {!r.identity_verified && canEditKvkk ? (
                        <button type="button" className="btn btn--sm btn--ghost" onClick={() => void verifyRequest(r.id)}>
                          {t('kvkk.requests.verify')}
                        </button>
                      ) : null}
                      {r.identity_verified && canRespondRequests ? (
                        <button type="button" className="btn btn--sm btn--ghost" onClick={() => void buildExport(r.id)}>
                          {t('kvkk.requests.export')}
                        </button>
                      ) : null}
                      {canRespondRequests ? (
                        <button
                          type="button"
                          className="btn btn--sm btn--primary"
                          onClick={() => setSelectedRequestId(r.id)}
                        >
                          {t('kvkk.requests.respond')}
                        </button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {selectedRequestId !== null ? (
            <div style={{ marginTop: 'var(--sp-4)', maxWidth: '40rem' }}>
              <h3>{t('kvkk.requests.respond')} #{selectedRequestId}</h3>
              <textarea
                className="form-control"
                rows={4}
                value={responseBody}
                onChange={(e) => setResponseBody(e.target.value)}
                placeholder={t('kvkk.requests.responsePlaceholder')}
              />
              <div style={{ display: 'flex', gap: 'var(--sp-2)', marginTop: 'var(--sp-2)' }}>
                <button type="button" className="btn btn--primary" onClick={() => void respondRequest(selectedRequestId, 'accept')}>
                  {t('kvkk.requests.accept')}
                </button>
                <button type="button" className="btn btn--ghost" onClick={() => void respondRequest(selectedRequestId, 'partial')}>
                  {t('kvkk.requests.partial')}
                </button>
                <button type="button" className="btn btn--ghost" onClick={() => void respondRequest(selectedRequestId, 'reject')}>
                  {t('kvkk.requests.reject')}
                </button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
};

export default KvkkPage;

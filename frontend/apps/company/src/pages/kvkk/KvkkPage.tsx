import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { kvkkApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { usePermission } from '@shared/hooks';
import toast from 'react-hot-toast';

type Tab = 'inventory' | 'notices' | 'consents';

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

const KvkkPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { canEdit } = usePermission();
  const canEditKvkk = canEdit('management', 'kvkk');

  const [tab, setTab] = useState<Tab>('inventory');
  const [loading, setLoading] = useState(true);
  const [activities, setActivities] = useState<ActivityRow[]>([]);
  const [notices, setNotices] = useState<NoticeRow[]>([]);
  const [consents, setConsents] = useState<ConsentRow[]>([]);
  const [missing, setMissing] = useState<MissingRow[]>([]);

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
        {(['inventory', 'notices', 'consents'] as Tab[]).map((key) => (
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
    </div>
  );
};

export default KvkkPage;

import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { BsArrowLeft } from 'react-icons/bs';
import { salaryReviewsApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks/usePermission';
import toast from 'react-hot-toast';
import { Modal } from '../../components/ui';

interface PeriodDetail {
  id: number;
  name: string;
  effective_date: string;
  scope_type: string;
  status: string;
  notes?: string | null;
}

interface ReviewItem {
  id: number;
  employee_id: number;
  employee_name?: string | null;
  employee_code?: string | null;
  position?: string | null;
  current_amount: string | number;
  proposed_amount: string | number;
  increase_percent?: string | number | null;
  currency: string;
  change_reason?: string | null;
  note?: string | null;
  band?: {
    status: 'below' | 'within' | 'above' | null;
  } | null;
}

const formatMoney = (amount: string | number | null | undefined, currency = 'TRY') => {
  if (amount === null || amount === undefined) return '—';
  return `${Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ${currency}`;
};

const SalaryReviewDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { canEdit } = usePermission();
  const allowEdit = canEdit('employees', 'salary');

  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState<PeriodDetail | null>(null);
  const [items, setItems] = useState<ReviewItem[]>([]);
  const [reasons, setReasons] = useState<LookupItem[]>([]);
  const [drafts, setDrafts] = useState<Record<number, { proposed_amount: string; change_reason: string }>>({});
  const [rejectOpen, setRejectOpen] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [acting, setActing] = useState(false);

  const load = useCallback(async () => {
    if (!id) return;
    try {
      setLoading(true);
      const res = await salaryReviewsApi.get(Number(id));
      const data = res.data.data;
      setPeriod(data.period);
      setItems(data.items || []);
      const next: Record<number, { proposed_amount: string; change_reason: string }> = {};
      for (const item of (data.items || []) as ReviewItem[]) {
        next[item.id] = {
          proposed_amount: String(item.proposed_amount ?? ''),
          change_reason: item.change_reason || 'annual_raise',
        };
      }
      setDrafts(next);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryReviews.loadError')));
      navigate('/employees/salary-reviews');
    } finally {
      setLoading(false);
    }
  }, [id, navigate, t]);

  useEffect(() => {
    void load();
    void lookupsApi.forType('salary_change_reason')
      .then((r) => setReasons(r.data.data ?? []))
      .catch(() => setReasons([]));
  }, [load]);

  const isDraft = period?.status === 'draft' || period?.status === 'rejected';
  const isPending = period?.status === 'pending_approval';

  const saveItem = async (itemId: number) => {
    if (!id || !period) return;
    const draft = drafts[itemId];
    if (!draft) return;
    try {
      await salaryReviewsApi.updateItem(Number(id), itemId, {
        proposed_amount: Number(draft.proposed_amount),
        change_reason: draft.change_reason,
      });
      toast.success(t('salaryReviews.itemSaved'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryReviews.itemSaveError')));
    }
  };

  const handleSubmit = async () => {
    if (!id) return;
    try {
      setActing(true);
      await salaryReviewsApi.submit(Number(id));
      toast.success(t('salaryReviews.submitSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryReviews.submitError')));
    } finally {
      setActing(false);
    }
  };

  const handleApprove = async () => {
    if (!id) return;
    try {
      setActing(true);
      await salaryReviewsApi.approve(Number(id));
      toast.success(t('salaryReviews.approveSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryReviews.approveError')));
    } finally {
      setActing(false);
    }
  };

  const handleReject = async () => {
    if (!id) return;
    if (!rejectReason.trim()) {
      toast.error(t('salaryReviews.rejectRequired'));
      return;
    }
    try {
      setActing(true);
      await salaryReviewsApi.reject(Number(id), { reason: rejectReason.trim() });
      toast.success(t('salaryReviews.rejectSuccess'));
      setRejectOpen(false);
      setRejectReason('');
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryReviews.rejectError')));
    } finally {
      setActing(false);
    }
  };

  if (loading || !period) {
    return <div className="text-center py-4">{t('loading')}</div>;
  }

  const statusKey = `salaryReviews.status.${period.status}`;
  const statusText = t(statusKey) === statusKey ? period.status : t(statusKey);

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => navigate('/employees/salary-reviews')}
            title={t('salaryReviews.backToList')}
            aria-label={t('salaryReviews.backToList')}
          >
            <BsArrowLeft />
          </button>
          <div>
            <h1 className="page-title">{period.name}</h1>
            <p className="page-subtitle">
              {t('salaryReviews.colEffective')}: {new Date(period.effective_date).toLocaleDateString('tr-TR')}
              {' · '}
              {statusText}
            </p>
          </div>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: 'var(--sp-2)' }}>
          {allowEdit && isDraft && (
            <button type="button" className="btn btn-primary btn-sm" disabled={acting} onClick={() => void handleSubmit()}>
              {t('salaryReviews.submit')}
            </button>
          )}
          {allowEdit && isPending && (
            <>
              <button type="button" className="btn btn-primary btn-sm" disabled={acting} onClick={() => void handleApprove()}>
                {t('salaryReviews.approve')}
              </button>
              <button type="button" className="btn btn-danger btn-sm" disabled={acting} onClick={() => setRejectOpen(true)}>
                {t('salaryReviews.reject')}
              </button>
            </>
          )}
        </div>
      </div>

      <div className="card">
        <div className="card-body" style={{ overflowX: 'auto' }}>
          <table className="table" style={{ width: '100%' }}>
            <thead>
              <tr>
                <th>{t('salaryReviews.colEmployee')}</th>
                <th>{t('salaryReviews.colCurrent')}</th>
                <th>{t('salaryReviews.colProposed')}</th>
                <th>{t('salaryReviews.colIncrease')}</th>
                <th>{t('salaryReviews.colReason')}</th>
                <th>{t('salaryReviews.colBand')}</th>
                {allowEdit && isDraft && <th />}
              </tr>
            </thead>
            <tbody>
              {items.map((item) => {
                const draft = drafts[item.id] ?? {
                  proposed_amount: String(item.proposed_amount),
                  change_reason: item.change_reason || '',
                };
                const bandStatus = item.band?.status
                  ? t(`salary.bandStatus.${item.band.status}`)
                  : '—';
                return (
                  <tr key={item.id}>
                    <td>
                      <div style={{ fontWeight: 500 }}>{item.employee_name || '—'}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                        {item.employee_code} {item.position ? `· ${item.position}` : ''}
                      </div>
                    </td>
                    <td>{formatMoney(item.current_amount, item.currency)}</td>
                    <td>
                      {allowEdit && isDraft ? (
                        <input
                          type="number"
                          min={0}
                          step="0.01"
                          className="form-control"
                          style={{ width: 120 }}
                          value={draft.proposed_amount}
                          onChange={(e) =>
                            setDrafts((prev) => ({
                              ...prev,
                              [item.id]: { ...draft, proposed_amount: e.target.value },
                            }))
                          }
                        />
                      ) : (
                        formatMoney(item.proposed_amount, item.currency)
                      )}
                    </td>
                    <td>{item.increase_percent != null ? `${Number(item.increase_percent).toFixed(1)}%` : '—'}</td>
                    <td>
                      {allowEdit && isDraft ? (
                        <Select
                          value={draft.change_reason}
                          onChange={(v) =>
                            setDrafts((prev) => ({
                              ...prev,
                              [item.id]: { ...draft, change_reason: v },
                            }))
                          }
                          options={reasons.map((r) => ({ value: r.value, label: r.label }))}
                          aria-label={t('salary.changeReason')}
                        />
                      ) : (
                        reasons.find((r) => r.value === item.change_reason)?.label ?? item.change_reason ?? '—'
                      )}
                    </td>
                    <td>{bandStatus}</td>
                    {allowEdit && isDraft && (
                      <td>
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          onClick={() => void saveItem(item.id)}
                        >
                          {t('salaryReviews.saveItem')}
                        </button>
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      <Modal
        isOpen={rejectOpen}
        onClose={() => setRejectOpen(false)}
        title={t('salaryReviews.reject')}
        size="sm"
      >
        <div className="form-group">
          <label className="form-label">{t('salaryReviews.rejectReason')}</label>
          <textarea
            className="form-control"
            rows={3}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </div>
        <div className="modal-footer" style={{ marginTop: 'var(--sp-4)', display: 'flex', gap: 'var(--sp-2)', justifyContent: 'flex-end' }}>
          <button type="button" className="btn btn-ghost" onClick={() => setRejectOpen(false)}>
            {t('cancel')}
          </button>
          <button type="button" className="btn btn-danger" disabled={acting} onClick={() => void handleReject()}>
            {t('salaryReviews.reject')}
          </button>
        </div>
      </Modal>
    </div>
  );
};

export default SalaryReviewDetailPage;

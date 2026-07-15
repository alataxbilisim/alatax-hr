import React, { useEffect, useState } from 'react';
import { portalApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { BsCashCoin } from 'react-icons/bs';

interface SalaryRecordRow {
  id: number;
  effective_date: string;
  amount: string | number;
  currency: string;
  change_reason: string;
  note?: string | null;
}

const SalaryPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(true);
  const [current, setCurrent] = useState<{
    amount: string | number;
    currency: string;
    effective_date?: string | null;
  } | null>(null);
  const [records, setRecords] = useState<SalaryRecordRow[]>([]);
  const [reasons, setReasons] = useState<LookupItem[]>([]);

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(true);
        const [salaryRes, reasonsRes] = await Promise.all([
          portalApi.salary.me(),
          lookupsApi.forType('salary_change_reason').catch(() => null),
        ]);
        const data = salaryRes.data.data;
        setCurrent(data.current);
        setRecords(data.records ?? []);
        if (reasonsRes) {
          setReasons(reasonsRes.data.data ?? []);
        }
      } catch (error: unknown) {
        toast.error(getErrorMessage(error, t('salary.portalLoadError')));
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, [t]);

  const formatMoney = (amount: string | number | null | undefined, currency = 'TRY') => {
    if (amount === null || amount === undefined) return '—';
    return `${Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ${currency}`;
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('salary.portalTitle')}</h1>
          <p className="page-subtitle">{t('salary.portalSubtitle')}</p>
        </div>
      </div>

      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : (
        <>
          <div className="card" style={{ marginBottom: 'var(--sp-4)' }}>
            <div className="card-body">
              <h3 style={{ marginTop: 0 }}>{t('salary.current')}</h3>
              <p style={{ fontSize: '1.5rem', fontWeight: 600, margin: 0 }}>
                {formatMoney(current?.amount, current?.currency)}
              </p>
              {current?.effective_date && (
                <p style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>
                  {t('salary.effectiveDate')}: {new Date(current.effective_date).toLocaleDateString('tr-TR')}
                </p>
              )}
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h3 className="card-title">{t('salary.history')}</h3>
            </div>
            <div className="card-body">
              {records.length === 0 ? (
                <div className="empty-state">
                  <BsCashCoin size={48} className="text-muted mb-3" />
                  <p>{t('salary.portalEmpty')}</p>
                </div>
              ) : (
                <table className="table" style={{ width: '100%' }}>
                  <thead>
                    <tr>
                      <th>{t('salary.effectiveDate')}</th>
                      <th>{t('salary.amount')}</th>
                      <th>{t('salary.changeReason')}</th>
                      <th>{t('salary.note')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {records.map((row) => (
                      <tr key={row.id}>
                        <td>{new Date(row.effective_date).toLocaleDateString('tr-TR')}</td>
                        <td>{formatMoney(row.amount, row.currency)}</td>
                        <td>
                          {reasons.find((r) => r.value === row.change_reason)?.label ?? row.change_reason}
                        </td>
                        <td>{row.note || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default SalaryPage;

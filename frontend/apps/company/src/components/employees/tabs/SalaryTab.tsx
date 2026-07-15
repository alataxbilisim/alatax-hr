import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { employeesApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { Select } from '@shared/components';
import { usePermission } from '@shared/hooks/usePermission';
import toast from 'react-hot-toast';

interface SalaryRecordRow {
  id: number;
  effective_date: string;
  amount: string | number;
  currency: string;
  change_reason: string;
  note?: string | null;
  creator?: { id: number; name: string } | null;
}

interface BandInfo {
  band: {
    min_amount: string | number;
    mid_amount: string | number;
    max_amount: string | number;
    currency: string;
    position_name?: string;
  } | null;
  status: 'below' | 'within' | 'above' | null;
  ratio: number | null;
}

interface SalaryTabProps {
  employeeId: number;
}

const SalaryTab: React.FC<SalaryTabProps> = ({ employeeId }) => {
  const { t } = useTranslation('common');
  const { canEdit } = usePermission();
  const canEditSalary = canEdit('employees', 'salary');
  const [loading, setLoading] = useState(true);
  const [current, setCurrent] = useState<{ amount: string | number; currency: string; effective_date?: string | null } | null>(null);
  const [records, setRecords] = useState<SalaryRecordRow[]>([]);
  const [band, setBand] = useState<BandInfo | null>(null);
  const [reasons, setReasons] = useState<LookupItem[]>([]);
  const [form, setForm] = useState({
    effective_date: new Date().toISOString().slice(0, 10),
    amount: '',
    change_reason: '',
    note: '',
  });
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await employeesApi.getSalary(employeeId);
      const data = res.data.data;
      setCurrent(data.current);
      setRecords(data.records ?? []);
      setBand(data.band ?? null);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salary.loadError')));
    } finally {
      setLoading(false);
    }
  }, [employeeId, t]);

  useEffect(() => {
    void load();
    void lookupsApi.forType('salary_change_reason')
      .then((r) => setReasons(r.data.data ?? []))
      .catch(() => setReasons([]));
  }, [load]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.change_reason || !form.amount) {
      toast.error(t('salary.formRequired'));
      return;
    }
    try {
      setSaving(true);
      await employeesApi.createSalary(employeeId, {
        effective_date: form.effective_date,
        amount: Number(form.amount),
        change_reason: form.change_reason,
        note: form.note.trim() || undefined,
      });
      toast.success(t('salary.saveSuccess'));
      setForm((prev) => ({ ...prev, amount: '', note: '', change_reason: '' }));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salary.saveError')));
    } finally {
      setSaving(false);
    }
  };

  const formatMoney = (amount: string | number | null | undefined, currency = 'TRY') => {
    if (amount === null || amount === undefined) return '—';
    return `${Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ${currency}`;
  };

  if (loading) {
    return <div className="text-center py-4">{t('loading')}</div>;
  }

  const bandStatusLabel = band?.status
    ? t(`salary.bandStatus.${band.status}`)
    : null;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-4)' }}>
      <div className="card">
        <div className="card-body">
          <h3 style={{ marginTop: 0 }}>{t('salary.current')}</h3>
          <p style={{ fontSize: '1.25rem', fontWeight: 600, margin: 0 }}>
            {formatMoney(current?.amount, current?.currency)}
          </p>
          {current?.effective_date && (
            <p style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>
              {t('salary.effectiveDate')}: {new Date(current.effective_date).toLocaleDateString('tr-TR')}
            </p>
          )}
          {band?.band && (
            <div style={{ marginTop: 'var(--sp-3)' }}>
              <div style={{ fontSize: '0.875rem', marginBottom: 'var(--sp-2)' }}>
                {t('salary.bandTitle')} — {bandStatusLabel}
              </div>
              <div style={{
                height: 10,
                background: 'var(--bg-tertiary)',
                borderRadius: 4,
                position: 'relative',
                overflow: 'hidden',
              }}>
                <div style={{
                  position: 'absolute',
                  left: `${Math.round((band.ratio ?? 0) * 100)}%`,
                  top: 0,
                  bottom: 0,
                  width: 4,
                  background: 'var(--accent)',
                  transform: 'translateX(-50%)',
                }} />
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: 4 }}>
                <span>{formatMoney(band.band.min_amount, band.band.currency)}</span>
                <span>{formatMoney(band.band.mid_amount, band.band.currency)}</span>
                <span>{formatMoney(band.band.max_amount, band.band.currency)}</span>
              </div>
            </div>
          )}
        </div>
      </div>

      {canEditSalary && (
        <form className="card" onSubmit={(e) => void handleSubmit(e)}>
          <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-3)' }}>
            <h3 style={{ margin: 0 }}>{t('salary.addRecord')}</h3>
            <div className="form-row" style={{ display: 'flex', gap: 'var(--sp-3)', flexWrap: 'wrap' }}>
              <div className="form-group" style={{ flex: 1, minWidth: 160 }}>
                <label className="form-label">{t('salary.effectiveDate')} *</label>
                <input
                  type="date"
                  className="form-input"
                  value={form.effective_date}
                  onChange={(e) => setForm({ ...form, effective_date: e.target.value })}
                  required
                />
              </div>
              <div className="form-group" style={{ flex: 1, minWidth: 160 }}>
                <label className="form-label">{t('salary.amount')} *</label>
                <input
                  type="number"
                  min={0}
                  step="0.01"
                  className="form-input"
                  value={form.amount}
                  onChange={(e) => setForm({ ...form, amount: e.target.value })}
                  required
                />
              </div>
              <div className="form-group" style={{ flex: 1, minWidth: 180 }}>
                <label className="form-label">{t('salary.changeReason')} *</label>
                <Select
                  value={form.change_reason}
                  onChange={(v) => setForm({ ...form, change_reason: v })}
                  options={reasons.map((r) => ({ value: r.value, label: r.label }))}
                  placeholder={t('salary.selectReason')}
                  aria-label={t('salary.changeReason')}
                />
              </div>
            </div>
            <div className="form-group">
              <label className="form-label">{t('salary.note')}</label>
              <input
                type="text"
                className="form-input"
                value={form.note}
                onChange={(e) => setForm({ ...form, note: e.target.value })}
              />
            </div>
            <button type="submit" className="btn btn-primary" disabled={saving} style={{ alignSelf: 'flex-start' }}>
              {saving ? t('loading') : t('salary.save')}
            </button>
          </div>
        </form>
      )}

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">{t('salary.history')}</h3>
        </div>
        <div className="card-body">
          {records.length === 0 ? (
            <p style={{ color: 'var(--text-tertiary)' }}>{t('salary.historyEmpty')}</p>
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
                    <td>{reasons.find((r) => r.value === row.change_reason)?.label ?? row.change_reason}</td>
                    <td>{row.note || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
};

export default SalaryTab;

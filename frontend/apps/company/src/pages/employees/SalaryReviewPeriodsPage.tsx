import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { BsPlus, BsEye, BsArrowLeft, BsCashStack } from 'react-icons/bs';
import { salaryReviewsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks/usePermission';
import toast from 'react-hot-toast';
import { Modal, DataTable } from '../../components/ui';
import type { Column } from '../../components/ui/DataTable';

interface PeriodRow {
  id: number;
  name: string;
  effective_date: string;
  scope_type: string;
  status: string;
  items_count?: number;
}

const SalaryReviewPeriodsPage: React.FC = () => {
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { canEdit } = usePermission();
  const allowEdit = canEdit('employees', 'salary');

  const [rows, setRows] = useState<PeriodRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('');
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    name: '',
    effective_date: new Date().toISOString().slice(0, 10),
    scope_type: 'company',
    notes: '',
  });

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const response = await salaryReviewsApi.list({
        per_page: 50,
        status: statusFilter || undefined,
      });
      setRows(response.data.data || []);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryReviews.loadError')));
    } finally {
      setLoading(false);
    }
  }, [statusFilter, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleCreate = async () => {
    if (!form.name.trim() || !form.effective_date) {
      toast.error(t('salaryReviews.formRequired'));
      return;
    }
    try {
      setSaving(true);
      const res = await salaryReviewsApi.create({
        name: form.name.trim(),
        effective_date: form.effective_date,
        scope_type: form.scope_type,
        notes: form.notes.trim() || undefined,
      });
      toast.success(t('salaryReviews.createSuccess'));
      setFormOpen(false);
      const id = res.data.data?.id as number | undefined;
      if (id) {
        navigate(`/employees/salary-reviews/${id}`);
      } else {
        await load();
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryReviews.createError')));
    } finally {
      setSaving(false);
    }
  };

  const scopeLabel = (scope: string) => {
    if (scope === 'department') return t('salaryReviews.scopeDepartment');
    if (scope === 'branch') return t('salaryReviews.scopeBranch');
    return t('salaryReviews.scopeCompany');
  };

  const statusLabel = (status: string) => {
    const key = `salaryReviews.status.${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
  };

  const columns: Column<PeriodRow>[] = useMemo(
    () => [
      {
        key: 'name',
        title: t('salaryReviews.colName'),
        render: (row) => <span style={{ fontWeight: 500 }}>{row.name}</span>,
      },
      {
        key: 'effective',
        title: t('salaryReviews.colEffective'),
        render: (row) => new Date(row.effective_date).toLocaleDateString('tr-TR'),
      },
      {
        key: 'scope',
        title: t('salaryReviews.colScope'),
        render: (row) => scopeLabel(row.scope_type),
      },
      {
        key: 'status',
        title: t('salaryReviews.colStatus'),
        render: (row) => <span className="badge badge-muted">{statusLabel(row.status)}</span>,
      },
      {
        key: 'items',
        title: t('salaryReviews.colItems'),
        render: (row) => row.items_count ?? '—',
      },
      {
        key: 'actions',
        title: t('salaryReviews.colActions'),
        align: 'right',
        width: '72px',
        render: (row) => (
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => navigate(`/employees/salary-reviews/${row.id}`)}
            title={t('salaryReviews.detailTitle')}
            aria-label={t('salaryReviews.detailTitle')}
          >
            <BsEye />
          </button>
        ),
      },
    ],
    [navigate, t]
  );

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => navigate('/employees')}
            title={t('salaryReviews.backToList')}
            aria-label={t('salaryReviews.backToList')}
          >
            <BsArrowLeft />
          </button>
          <h1 className="page-title">{t('salaryReviews.title')}</h1>
          {rows.length > 0 && (
            <span className="page-subtitle">{t('salaryReviews.count', { count: rows.length })}</span>
          )}
        </div>
        <div className="page-header-actions">
          {allowEdit && (
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setFormOpen(true)}>
              <BsPlus /> {t('salaryReviews.new')}
            </button>
          )}
        </div>
      </div>

      <div className="list-filter-bar">
        <Select
          value={statusFilter}
          onChange={setStatusFilter}
          allowEmpty
          placeholder={t('salaryReviews.colStatus')}
          options={[
            { value: 'draft', label: t('salaryReviews.status.draft') },
            { value: 'pending_approval', label: t('salaryReviews.status.pending_approval') },
            { value: 'approved', label: t('salaryReviews.status.approved') },
            { value: 'rejected', label: t('salaryReviews.status.rejected') },
          ]}
          aria-label={t('salaryReviews.colStatus')}
        />
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        emptyMessage={t('salaryReviews.empty')}
        emptyIcon={<BsCashStack size={32} />}
      />

      <Modal
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        title={t('salaryReviews.createTitle')}
        size="md"
      >
        <div style={{ display: 'grid', gap: 'var(--sp-3)' }}>
          <div className="form-group">
            <label className="form-label">{t('salaryReviews.fieldName')}</label>
            <input
              type="text"
              className="form-control"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('salaryReviews.fieldEffective')}</label>
            <input
              type="date"
              className="form-control"
              value={form.effective_date}
              onChange={(e) => setForm((f) => ({ ...f, effective_date: e.target.value }))}
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('salaryReviews.fieldScope')}</label>
            <Select
              value={form.scope_type}
              onChange={(v) => setForm((f) => ({ ...f, scope_type: v || 'company' }))}
              options={[
                { value: 'company', label: t('salaryReviews.scopeCompany') },
                { value: 'department', label: t('salaryReviews.scopeDepartment') },
                { value: 'branch', label: t('salaryReviews.scopeBranch') },
              ]}
              aria-label={t('salaryReviews.fieldScope')}
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('salaryReviews.fieldNotes')}</label>
            <textarea
              className="form-control"
              rows={2}
              value={form.notes}
              onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
            />
          </div>
        </div>
        <div className="modal-footer" style={{ marginTop: 'var(--sp-4)', display: 'flex', gap: 'var(--sp-2)', justifyContent: 'flex-end' }}>
          <button type="button" className="btn btn-ghost" onClick={() => setFormOpen(false)}>
            {t('cancel')}
          </button>
          <button type="button" className="btn btn-primary" onClick={() => void handleCreate()} disabled={saving}>
            {t('save')}
          </button>
        </div>
      </Modal>
    </div>
  );
};

export default SalaryReviewPeriodsPage;

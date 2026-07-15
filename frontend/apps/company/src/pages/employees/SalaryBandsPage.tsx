import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BsPlus,
  BsCashCoin,
  BsPencil,
  BsTrash,
  BsArrowLeft,
} from 'react-icons/bs';
import { salaryBandsApi, positionsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks/usePermission';
import toast from 'react-hot-toast';
import { Modal, ConfirmDialog, DataTable } from '../../components/ui';
import type { Column } from '../../components/ui/DataTable';

interface PositionOption {
  id: number;
  name: string;
  code?: string;
}

interface BandRow {
  id: number;
  position_id: number;
  position?: PositionOption | null;
  min_amount: string | number;
  mid_amount: string | number;
  max_amount: string | number;
  currency: string;
  is_active: boolean;
}

interface BandFormData {
  position_id: number | null;
  min_amount: string;
  mid_amount: string;
  max_amount: string;
  currency: string;
  is_active: boolean;
}

const emptyForm = (): BandFormData => ({
  position_id: null,
  min_amount: '',
  mid_amount: '',
  max_amount: '',
  currency: 'TRY',
  is_active: true,
});

const formatMoney = (amount: string | number, currency = 'TRY') =>
  `${Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ${currency}`;

const SalaryBandsPage: React.FC = () => {
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { canEdit } = usePermission();
  const allowEdit = canEdit('employees', 'salary');

  const [rows, setRows] = useState<BandRow[]>([]);
  const [positions, setPositions] = useState<PositionOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [selected, setSelected] = useState<BandRow | null>(null);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<BandFormData>(emptyForm());

  const loadBands = useCallback(async () => {
    try {
      setLoading(true);
      const response = await salaryBandsApi.list({ per_page: 100 });
      setRows(response.data.data || []);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryBands.loadError')));
    } finally {
      setLoading(false);
    }
  }, [t]);

  const loadPositions = useCallback(async () => {
    try {
      const response = await positionsApi.getAll({ per_page: 200, active_only: true });
      setPositions(response.data.data || []);
    } catch {
      setPositions([]);
    }
  }, []);

  useEffect(() => {
    void loadPositions();
    void loadBands();
  }, [loadBands, loadPositions]);

  const handleOpenForm = (row?: BandRow) => {
    if (row) {
      setSelected(row);
      setFormData({
        position_id: row.position_id,
        min_amount: String(row.min_amount),
        mid_amount: String(row.mid_amount),
        max_amount: String(row.max_amount),
        currency: row.currency || 'TRY',
        is_active: row.is_active,
      });
    } else {
      setSelected(null);
      setFormData(emptyForm());
    }
    setFormModalOpen(true);
  };

  const handleSave = async () => {
    if (!formData.position_id || !formData.min_amount || !formData.mid_amount || !formData.max_amount) {
      toast.error(t('salaryBands.formRequired'));
      return;
    }

    try {
      setSaving(true);
      const payload = {
        position_id: formData.position_id,
        min_amount: Number(formData.min_amount),
        mid_amount: Number(formData.mid_amount),
        max_amount: Number(formData.max_amount),
        currency: formData.currency || 'TRY',
        is_active: formData.is_active,
      };

      if (selected) {
        await salaryBandsApi.update(selected.id, payload);
        toast.success(t('salaryBands.updateSuccess'));
      } else {
        await salaryBandsApi.create(payload);
        toast.success(t('salaryBands.createSuccess'));
      }

      setFormModalOpen(false);
      await loadBands();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryBands.saveError')));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!selected) return;
    try {
      await salaryBandsApi.delete(selected.id);
      toast.success(t('salaryBands.deleteSuccess'));
      setDeleteDialogOpen(false);
      setSelected(null);
      await loadBands();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('salaryBands.deleteError')));
    }
  };

  const columns: Column<BandRow>[] = useMemo(
    () => [
      {
        key: 'position',
        title: t('salaryBands.colPosition'),
        render: (row) => (
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
            <BsCashCoin style={{ color: 'var(--primary)', flexShrink: 0 }} />
            <span style={{ fontWeight: 500 }}>{row.position?.name || '—'}</span>
          </div>
        ),
      },
      {
        key: 'min',
        title: t('salaryBands.colMin'),
        render: (row) => formatMoney(row.min_amount, row.currency),
      },
      {
        key: 'mid',
        title: t('salaryBands.colMid'),
        render: (row) => formatMoney(row.mid_amount, row.currency),
      },
      {
        key: 'max',
        title: t('salaryBands.colMax'),
        render: (row) => formatMoney(row.max_amount, row.currency),
      },
      {
        key: 'status',
        title: t('salaryBands.colStatus'),
        render: (row) => (
          <span className={`badge ${row.is_active ? 'badge-success' : 'badge-muted'}`}>
            {row.is_active ? t('salaryBands.active') : t('salaryBands.inactive')}
          </span>
        ),
      },
      {
        key: 'actions',
        title: t('salaryBands.colActions'),
        align: 'right',
        width: '96px',
        render: (row) => (
          <div className="table-actions">
            {allowEdit && (
              <>
                <button
                  type="button"
                  className="btn btn-ghost btn-icon"
                  onClick={() => handleOpenForm(row)}
                  title={t('salaryBands.edit')}
                  aria-label={t('salaryBands.edit')}
                >
                  <BsPencil />
                </button>
                <button
                  type="button"
                  className="btn btn-ghost btn-icon"
                  onClick={() => {
                    setSelected(row);
                    setDeleteDialogOpen(true);
                  }}
                  title={t('salaryBands.delete')}
                  aria-label={t('salaryBands.delete')}
                  style={{ color: 'var(--danger)' }}
                >
                  <BsTrash />
                </button>
              </>
            )}
          </div>
        ),
      },
    ],
    [allowEdit, t]
  );

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => navigate('/employees')}
            title={t('salaryBands.backToList')}
            aria-label={t('salaryBands.backToList')}
          >
            <BsArrowLeft />
          </button>
          <h1 className="page-title">{t('salaryBands.title')}</h1>
          {rows.length > 0 && (
            <span className="page-subtitle">{t('salaryBands.count', { count: rows.length })}</span>
          )}
        </div>
        <div className="page-header-actions">
          {allowEdit && (
            <button type="button" className="btn btn-primary btn-sm" onClick={() => handleOpenForm()}>
              <BsPlus /> {t('salaryBands.new')}
            </button>
          )}
        </div>
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        emptyMessage={t('salaryBands.empty')}
        emptyIcon={<BsCashCoin size={32} />}
      />

      <Modal
        isOpen={formModalOpen}
        onClose={() => setFormModalOpen(false)}
        title={selected ? t('salaryBands.editTitle') : t('salaryBands.createTitle')}
        size="md"
      >
        <div className="form-grid" style={{ display: 'grid', gap: 'var(--sp-3)' }}>
          <div className="form-group">
            <label className="form-label">{t('salaryBands.fieldPosition')}</label>
            <Select
              value={formData.position_id ? String(formData.position_id) : ''}
              onChange={(v) =>
                setFormData((f) => ({ ...f, position_id: v ? Number(v) : null }))
              }
              options={positions.map((p) => ({ value: String(p.id), label: p.name }))}
              placeholder={t('salaryBands.selectPosition')}
              aria-label={t('salaryBands.fieldPosition')}
            />
          </div>
          <div className="form-row" style={{ display: 'flex', gap: 'var(--sp-3)', flexWrap: 'wrap' }}>
            <div className="form-group" style={{ flex: 1, minWidth: 120 }}>
              <label className="form-label">{t('salaryBands.fieldMin')}</label>
              <input
                type="number"
                min={0}
                step="0.01"
                className="form-control"
                value={formData.min_amount}
                onChange={(e) => setFormData((f) => ({ ...f, min_amount: e.target.value }))}
              />
            </div>
            <div className="form-group" style={{ flex: 1, minWidth: 120 }}>
              <label className="form-label">{t('salaryBands.fieldMid')}</label>
              <input
                type="number"
                min={0}
                step="0.01"
                className="form-control"
                value={formData.mid_amount}
                onChange={(e) => setFormData((f) => ({ ...f, mid_amount: e.target.value }))}
              />
            </div>
            <div className="form-group" style={{ flex: 1, minWidth: 120 }}>
              <label className="form-label">{t('salaryBands.fieldMax')}</label>
              <input
                type="number"
                min={0}
                step="0.01"
                className="form-control"
                value={formData.max_amount}
                onChange={(e) => setFormData((f) => ({ ...f, max_amount: e.target.value }))}
              />
            </div>
          </div>
          <div className="form-group">
            <label className="form-label">{t('salaryBands.fieldCurrency')}</label>
            <input
              type="text"
              className="form-control"
              maxLength={3}
              value={formData.currency}
              onChange={(e) => setFormData((f) => ({ ...f, currency: e.target.value.toUpperCase() }))}
            />
          </div>
          <div className="form-group">
            <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => setFormData((f) => ({ ...f, is_active: e.target.checked }))}
              />
              {t('salaryBands.fieldActive')}
            </label>
          </div>
        </div>
        <div className="modal-footer" style={{ marginTop: 'var(--sp-4)', display: 'flex', gap: 'var(--sp-2)', justifyContent: 'flex-end' }}>
          <button type="button" className="btn btn-ghost" onClick={() => setFormModalOpen(false)}>
            {t('cancel')}
          </button>
          <button type="button" className="btn btn-primary" onClick={() => void handleSave()} disabled={saving}>
            {t('save')}
          </button>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={() => void handleDelete()}
        title={t('salaryBands.deleteTitle')}
        message={t('salaryBands.deleteConfirm')}
      />
    </div>
  );
};

export default SalaryBandsPage;

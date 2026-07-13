import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BsPlus,
  BsSearch,
  BsBriefcase,
  BsPencil,
  BsTrash,
  BsArrowLeft,
} from 'react-icons/bs';
import { positionsApi, departmentsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks/usePermission';
import toast from 'react-hot-toast';
import { Modal, ConfirmDialog, DataTable } from '../../components/ui';
import type { Column } from '../../components/ui/DataTable';

interface DepartmentOption {
  id: number;
  name: string;
}

interface PositionRow {
  id: number;
  code: string;
  name: string;
  department_id?: number | null;
  department?: DepartmentOption | null;
  sgk_occupation_code?: string | null;
  description?: string | null;
  is_active: boolean;
  is_system: boolean;
  sort_order?: number;
}

interface PositionFormData {
  code: string;
  name: string;
  department_id: number | null;
  sgk_occupation_code: string;
  description: string;
  is_active: boolean;
}

const emptyForm = (): PositionFormData => ({
  code: '',
  name: '',
  department_id: null,
  sgk_occupation_code: '',
  description: '',
  is_active: true,
});

const PositionsPage: React.FC = () => {
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { canCreate, canEdit, canDelete } = usePermission();

  const allowCreate = canCreate('employees', 'positions');
  const allowEdit = canEdit('employees', 'positions');
  const allowDelete = canDelete('employees', 'positions');

  const [rows, setRows] = useState<PositionRow[]>([]);
  const [departments, setDepartments] = useState<DepartmentOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [selected, setSelected] = useState<PositionRow | null>(null);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<PositionFormData>(emptyForm());

  const loadPositions = useCallback(async () => {
    try {
      setLoading(true);
      const response = await positionsApi.getAll({
        search: search || undefined,
        per_page: 100,
      });
      setRows(response.data.data || []);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('positions.loadError')));
    } finally {
      setLoading(false);
    }
  }, [search, t]);

  const loadDepartments = useCallback(async () => {
    try {
      const response = await departmentsApi.getAll({ active_only: true });
      setDepartments(response.data.data || []);
    } catch {
      // departman yoksa formda boş kalır
    }
  }, []);

  useEffect(() => {
    loadDepartments();
  }, [loadDepartments]);

  useEffect(() => {
    const timer = setTimeout(() => {
      loadPositions();
    }, 200);
    return () => clearTimeout(timer);
  }, [loadPositions]);

  const handleOpenForm = (row?: PositionRow) => {
    if (row) {
      setSelected(row);
      setFormData({
        code: row.code,
        name: row.name,
        department_id: row.department_id ?? null,
        sgk_occupation_code: row.sgk_occupation_code || '',
        description: row.description || '',
        is_active: row.is_active,
      });
    } else {
      setSelected(null);
      setFormData(emptyForm());
    }
    setFormModalOpen(true);
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.error(t('positions.nameRequired'));
      return;
    }

    try {
      setSaving(true);
      const payload = {
        code: formData.code.trim() || undefined,
        name: formData.name.trim(),
        department_id: formData.department_id,
        sgk_occupation_code: formData.sgk_occupation_code.trim() || null,
        description: formData.description.trim() || null,
        is_active: formData.is_active,
      };

      if (selected) {
        await positionsApi.update(selected.id, payload);
        toast.success(t('positions.updateSuccess'));
      } else {
        await positionsApi.create(payload);
        toast.success(t('positions.createSuccess'));
      }

      setFormModalOpen(false);
      loadPositions();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('positions.saveError')));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!selected) return;
    try {
      await positionsApi.delete(selected.id);
      toast.success(t('positions.deleteSuccess'));
      setDeleteDialogOpen(false);
      setSelected(null);
      loadPositions();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('positions.deleteError')));
    }
  };

  const columns: Column<PositionRow>[] = useMemo(
    () => [
      {
        key: 'name',
        title: t('positions.colName'),
        render: (row) => (
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
            <BsBriefcase style={{ color: 'var(--primary)', flexShrink: 0 }} />
            <div>
              <div style={{ fontWeight: 500 }}>{row.name}</div>
              <div style={{ fontSize: 'var(--fs-xs)', color: 'var(--text-muted)' }}>{row.code}</div>
            </div>
          </div>
        ),
      },
      {
        key: 'sgk',
        title: t('positions.colSgk'),
        render: (row) => row.sgk_occupation_code || '—',
      },
      {
        key: 'department',
        title: t('positions.colDepartment'),
        render: (row) => row.department?.name || '—',
      },
      {
        key: 'status',
        title: t('positions.colStatus'),
        render: (row) => (
          <span className={`badge ${row.is_active ? 'badge-success' : 'badge-muted'}`}>
            {row.is_active ? t('positions.active') : t('positions.inactive')}
            {row.is_system ? ` · ${t('positions.system')}` : ''}
          </span>
        ),
      },
      {
        key: 'actions',
        title: t('positions.colActions'),
        align: 'right',
        width: '96px',
        render: (row) => (
          <div className="table-actions">
            {allowEdit && (
              <button
                type="button"
                className="btn btn-ghost btn-icon"
                onClick={() => handleOpenForm(row)}
                title={t('positions.edit')}
                aria-label={t('positions.edit')}
              >
                <BsPencil />
              </button>
            )}
            {allowDelete && (
              <button
                type="button"
                className="btn btn-ghost btn-icon"
                onClick={() => {
                  setSelected(row);
                  setDeleteDialogOpen(true);
                }}
                title={t('positions.delete')}
                aria-label={t('positions.delete')}
                disabled={row.is_system}
                style={{ color: 'var(--danger)' }}
              >
                <BsTrash />
              </button>
            )}
          </div>
        ),
      },
    ],
    [allowEdit, allowDelete, t]
  );

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => navigate('/employees')}
            title={t('positions.backToList')}
            aria-label={t('positions.backToList')}
          >
            <BsArrowLeft />
          </button>
          <h1 className="page-title">{t('positions.title')}</h1>
          {rows.length > 0 && (
            <span className="page-subtitle">{t('positions.count', { count: rows.length })}</span>
          )}
        </div>
        <div className="page-header-actions">
          {allowCreate && (
            <button type="button" className="btn btn-primary btn-sm" onClick={() => handleOpenForm()}>
              <BsPlus /> {t('positions.new')}
            </button>
          )}
        </div>
      </div>

      <div className="list-filter-bar">
        <div className="list-filter-search input-group">
          <span className="input-icon"><BsSearch /></span>
          <input
            type="text"
            className="form-control"
            placeholder={t('positions.searchPlaceholder')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            style={{ paddingLeft: '2.25rem' }}
          />
        </div>
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        emptyMessage={t('positions.empty')}
        emptyIcon={<BsBriefcase size={32} />}
      />

      <Modal
        isOpen={formModalOpen}
        onClose={() => setFormModalOpen(false)}
        title={selected ? t('positions.editTitle') : t('positions.createTitle')}
        size="md"
      >
        <div className="form-grid" style={{ display: 'grid', gap: 'var(--sp-3)' }}>
          <div className="form-group">
            <label className="form-label">{t('positions.fieldName')}</label>
            <input
              type="text"
              className="form-control"
              value={formData.name}
              onChange={(e) => setFormData((f) => ({ ...f, name: e.target.value }))}
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('positions.fieldCode')}</label>
            <input
              type="text"
              className="form-control"
              value={formData.code}
              onChange={(e) => setFormData((f) => ({ ...f, code: e.target.value }))}
              disabled={!!selected?.is_system}
              placeholder={t('positions.fieldCodeHint')}
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('positions.fieldSgk')}</label>
            <input
              type="text"
              className="form-control"
              value={formData.sgk_occupation_code}
              onChange={(e) => setFormData((f) => ({ ...f, sgk_occupation_code: e.target.value }))}
              placeholder="2512.05"
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('positions.fieldDepartment')}</label>
            <Select
              value={formData.department_id ? String(formData.department_id) : ''}
              onChange={(v) =>
                setFormData((f) => ({ ...f, department_id: v ? Number(v) : null }))
              }
              options={departments.map((d) => ({ value: String(d.id), label: d.name }))}
              allowEmpty
              placeholder={t('positions.selectDepartment')}
              aria-label={t('positions.fieldDepartment')}
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('positions.fieldDescription')}</label>
            <textarea
              className="form-control"
              rows={3}
              value={formData.description}
              onChange={(e) => setFormData((f) => ({ ...f, description: e.target.value }))}
            />
          </div>
          <div className="form-group">
            <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => setFormData((f) => ({ ...f, is_active: e.target.checked }))}
              />
              {t('positions.fieldActive')}
            </label>
          </div>
        </div>
        <div className="modal-footer" style={{ marginTop: 'var(--sp-4)', display: 'flex', gap: 'var(--sp-2)', justifyContent: 'flex-end' }}>
          <button type="button" className="btn btn-ghost" onClick={() => setFormModalOpen(false)}>
            {t('cancel')}
          </button>
          <button type="button" className="btn btn-primary" onClick={handleSave} disabled={saving}>
            {t('save')}
          </button>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={handleDelete}
        title={t('positions.deleteTitle')}
        message={t('positions.deleteConfirm', { name: selected?.name ?? '' })}
      />
    </div>
  );
};

export default PositionsPage;

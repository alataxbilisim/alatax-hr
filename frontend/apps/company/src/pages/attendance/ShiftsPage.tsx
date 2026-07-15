import React, { useCallback, useEffect, useState } from 'react';
import { shiftsApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { DataTable } from '../../components/ui';
import { BsPlus, BsPencil, BsTrash } from 'react-icons/bs';

interface ShiftRow {
  id: number;
  name: string;
  code?: string | null;
  start_time: string;
  end_time: string;
  break_duration_minutes: number;
  is_night_shift: boolean;
  is_active: boolean;
  color?: string | null;
}

interface ShiftForm {
  name: string;
  code: string;
  start_time: string;
  end_time: string;
  break_duration_minutes: number;
  is_night_shift: boolean;
  is_active: boolean;
}

const emptyForm = (): ShiftForm => ({
  name: '',
  code: '',
  start_time: '09:00',
  end_time: '18:00',
  break_duration_minutes: 60,
  is_night_shift: false,
  is_active: true,
});

function formatTime(value: string): string {
  return value.length >= 5 ? value.slice(0, 5) : value;
}

const ShiftsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [rows, setRows] = useState<ShiftRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<ShiftForm>(emptyForm());
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await shiftsApi.list({ per_page: 100 });
      const data = res.data.data;
      const list: ShiftRow[] = Array.isArray(data) ? data : data?.data || [];
      setRows(list);
    } catch {
      toast.error(t('shifts.loadFailed'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm());
    setModalOpen(true);
  };

  const openEdit = (row: ShiftRow) => {
    setEditingId(row.id);
    setForm({
      name: row.name,
      code: row.code || '',
      start_time: formatTime(row.start_time),
      end_time: formatTime(row.end_time),
      break_duration_minutes: row.break_duration_minutes ?? 0,
      is_night_shift: row.is_night_shift,
      is_active: row.is_active,
    });
    setModalOpen(true);
  };

  const save = async () => {
    if (!form.name.trim()) {
      toast.error(t('shifts.nameRequired'));
      return;
    }
    try {
      setSaving(true);
      const payload = {
        name: form.name.trim(),
        code: form.code.trim() || null,
        start_time: form.start_time,
        end_time: form.end_time,
        break_duration_minutes: form.break_duration_minutes,
        is_night_shift: form.is_night_shift,
        is_active: form.is_active,
      };
      if (editingId) {
        await shiftsApi.update(editingId, payload);
        toast.success(t('shifts.updateSuccess'));
      } else {
        await shiftsApi.create(payload);
        toast.success(t('shifts.createSuccess'));
      }
      setModalOpen(false);
      await load();
    } catch {
      toast.error(t('shifts.saveFailed'));
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id: number) => {
    if (!window.confirm(t('shifts.deleteConfirm'))) return;
    try {
      await shiftsApi.delete(id);
      toast.success(t('shifts.deleteSuccess'));
      await load();
    } catch {
      toast.error(t('shifts.deleteFailed'));
    }
  };

  const columns = [
    { key: 'name', title: t('shifts.name'), render: (row: ShiftRow) => row.name },
    { key: 'code', title: t('shifts.code'), render: (row: ShiftRow) => row.code || '—' },
    {
      key: 'start_time',
      title: t('shifts.start'),
      render: (row: ShiftRow) => formatTime(row.start_time),
    },
    {
      key: 'end_time',
      title: t('shifts.end'),
      render: (row: ShiftRow) => formatTime(row.end_time),
    },
    {
      key: 'break',
      title: t('shifts.breakMinutes'),
      render: (row: ShiftRow) => String(row.break_duration_minutes ?? 0),
    },
    {
      key: 'active',
      title: t('shifts.active'),
      render: (row: ShiftRow) => (row.is_active ? t('attendance.yes') : t('attendance.no')),
    },
    {
      key: 'actions',
      title: '',
      render: (row: ShiftRow) => (
        <div style={{ display: 'flex', gap: '0.35rem' }}>
          <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => openEdit(row)}>
            <BsPencil />
          </button>
          <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => void remove(row.id)}>
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="page-container">
      <div
        className="page-header"
        style={{ marginBottom: '1rem', display: 'flex', justifyContent: 'space-between', gap: '1rem' }}
      >
        <h1 style={{ margin: 0 }}>{t('shifts.title')}</h1>
        <button type="button" className="btn btn-primary" onClick={openCreate}>
          <BsPlus /> {t('shifts.create')}
        </button>
      </div>

      <DataTable columns={columns} data={rows} loading={loading} />

      {modalOpen && (
        <div className="modal-overlay" onClick={() => setModalOpen(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 480 }}>
            <h2 style={{ marginTop: 0 }}>{editingId ? t('shifts.edit') : t('shifts.create')}</h2>
            <div className="form-group">
              <label className="form-label">{t('shifts.name')}</label>
              <input
                className="form-control"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label className="form-label">{t('shifts.code')}</label>
              <input
                className="form-control"
                value={form.code}
                onChange={(e) => setForm({ ...form, code: e.target.value })}
              />
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
              <div className="form-group">
                <label className="form-label">{t('shifts.start')}</label>
                <input
                  type="time"
                  className="form-control"
                  value={form.start_time}
                  onChange={(e) => setForm({ ...form, start_time: e.target.value })}
                />
              </div>
              <div className="form-group">
                <label className="form-label">{t('shifts.end')}</label>
                <input
                  type="time"
                  className="form-control"
                  value={form.end_time}
                  onChange={(e) => setForm({ ...form, end_time: e.target.value })}
                />
              </div>
            </div>
            <div className="form-group">
              <label className="form-label">{t('shifts.breakMinutes')}</label>
              <input
                type="number"
                min={0}
                className="form-control"
                value={form.break_duration_minutes}
                onChange={(e) => setForm({ ...form, break_duration_minutes: Number(e.target.value) || 0 })}
              />
            </div>
            <label className="form-checkbox">
              <input
                type="checkbox"
                checked={form.is_night_shift}
                onChange={(e) => setForm({ ...form, is_night_shift: e.target.checked })}
              />
              <span>{t('shifts.nightShift')}</span>
            </label>
            <label className="form-checkbox">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              />
              <span>{t('shifts.active')}</span>
            </label>
            <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end', marginTop: '1rem' }}>
              <button type="button" className="btn btn-outline-secondary" onClick={() => setModalOpen(false)}>
                {t('shifts.cancel')}
              </button>
              <button type="button" className="btn btn-primary" disabled={saving} onClick={() => void save()}>
                {saving ? t('shifts.saving') : t('shifts.save')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ShiftsPage;

import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { attendanceApi, employeesApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { DataTable } from '../../components/ui';
import { BsCheck2All, BsCheck, BsPencil, BsPlus } from 'react-icons/bs';

interface AttendanceRow {
  id: number;
  date: string;
  user_id?: number;
  clock_in?: string | null;
  clock_out?: string | null;
  total_hours?: number | string | null;
  status: string;
  is_approved: boolean;
  notes?: string | null;
  user?: { id: number; name: string };
}

interface DailySummary {
  date: string;
  total_employees: number;
  present: number;
  absent: number;
  late: number;
  on_leave: number;
}

interface EmployeeOption {
  id: number;
  user_id?: number | null;
  first_name?: string;
  last_name?: string;
  user?: { id: number; name: string };
}

interface FormState {
  user_id: string;
  date: string;
  clock_in: string;
  clock_out: string;
  status: string;
  notes: string;
  reason: string;
}

function emptyForm(date: string): FormState {
  return {
    user_id: '',
    date,
    clock_in: '',
    clock_out: '',
    status: 'present',
    notes: '',
    reason: '',
  };
}

function formatTime(value?: string | null): string {
  if (!value) return '—';
  if (value.length >= 5) return value.slice(0, 5);
  return value;
}

function nowDate(): string {
  const d = new Date();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${m}-${day}`;
}

function parseSummary(data: unknown): DailySummary | null {
  if (!data || typeof data !== 'object') return null;
  if (!('date' in data) || typeof data.date !== 'string') return null;
  const total = 'total_employees' in data ? Number(data.total_employees) : 0;
  const present = 'present' in data ? Number(data.present) : 0;
  const absent = 'absent' in data ? Number(data.absent) : 0;
  const late = 'late' in data ? Number(data.late) : 0;
  const onLeave = 'on_leave' in data ? Number(data.on_leave) : 0;
  return {
    date: data.date,
    total_employees: Number.isFinite(total) ? total : 0,
    present: Number.isFinite(present) ? present : 0,
    absent: Number.isFinite(absent) ? absent : 0,
    late: Number.isFinite(late) ? late : 0,
    on_leave: Number.isFinite(onLeave) ? onLeave : 0,
  };
}

const AttendancePage: React.FC = () => {
  const { t } = useTranslation('common');
  const [date, setDate] = useState(nowDate());
  const [rows, setRows] = useState<AttendanceRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [selected, setSelected] = useState<number[]>([]);
  const [summary, setSummary] = useState<DailySummary | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [employees, setEmployees] = useState<EmployeeOption[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<AttendanceRow | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm(nowDate()));
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const [listRes, summaryRes] = await Promise.all([
        attendanceApi.list({ date, page, per_page: 50 }),
        attendanceApi.dailySummary(date),
      ]);
      const data = listRes.data.data;
      const list: AttendanceRow[] = Array.isArray(data) ? data : data?.data || [];
      setRows(list);
      setTotalPages(listRes.data.meta?.last_page || 1);
      setSummary(parseSummary(summaryRes.data.data));
      setSelected([]);
    } catch {
      toast.error(t('attendance.loadFailed'));
    } finally {
      setLoading(false);
    }
  }, [date, page, t]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void employeesApi
      .getAll({ per_page: 200, status: 'active' })
      .then((res) => {
        const data = res.data.data;
        setEmployees(Array.isArray(data) ? data : data?.data || []);
      })
      .catch(() => undefined);
  }, []);

  const employeeOptions = useMemo(() => {
    return employees
      .map((e) => {
        const uid = e.user_id ?? e.user?.id;
        if (!uid) return null;
        const label =
          e.user?.name ||
          [e.first_name, e.last_name].filter(Boolean).join(' ') ||
          `#${uid}`;
        return { userId: uid, label };
      })
      .filter((x): x is { userId: number; label: string } => x !== null);
  }, [employees]);

  const toggle = (id: number) => {
    setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  };

  const approveOne = async (id: number) => {
    try {
      setActionLoading(true);
      await attendanceApi.approve(id);
      toast.success(t('attendance.approveSuccess'));
      await load();
    } catch {
      toast.error(t('attendance.actionFailed'));
    } finally {
      setActionLoading(false);
    }
  };

  const bulkApprove = async () => {
    if (selected.length === 0) return;
    try {
      setActionLoading(true);
      const res = await attendanceApi.bulkApprove(selected);
      const payload = res.data.data;
      const count =
        payload && typeof payload === 'object' && 'approved_count' in payload
          ? Number(payload.approved_count)
          : selected.length;
      toast.success(t('attendance.bulkApproveSuccess', { count }));
      await load();
    } catch {
      toast.error(t('attendance.actionFailed'));
    } finally {
      setActionLoading(false);
    }
  };

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm(date));
    setModalOpen(true);
  };

  const openEdit = (row: AttendanceRow) => {
    setEditing(row);
    setForm({
      user_id: String(row.user_id ?? row.user?.id ?? ''),
      date: row.date,
      clock_in: formatTime(row.clock_in) === '—' ? '' : formatTime(row.clock_in),
      clock_out: formatTime(row.clock_out) === '—' ? '' : formatTime(row.clock_out),
      status: row.status,
      notes: row.notes || '',
      reason: '',
    });
    setModalOpen(true);
  };

  const save = async () => {
    if (!form.reason.trim() || form.reason.trim().length < 3) {
      toast.error(t('attendance.reasonRequired'));
      return;
    }
    try {
      setSaving(true);
      if (editing) {
        await attendanceApi.update(editing.id, {
          clock_in: form.clock_in || null,
          clock_out: form.clock_out || null,
          status: form.status,
          notes: form.notes || null,
          reason: form.reason.trim(),
        });
        toast.success(t('attendance.updateSuccess'));
      } else {
        if (!form.user_id) {
          toast.error(t('attendance.employeeRequired'));
          return;
        }
        await attendanceApi.create({
          user_id: Number(form.user_id),
          date: form.date,
          clock_in: form.clock_in || null,
          clock_out: form.clock_out || null,
          status: form.status,
          notes: form.notes || null,
          reason: form.reason.trim(),
        });
        toast.success(t('attendance.createSuccess'));
      }
      setModalOpen(false);
      await load();
    } catch {
      toast.error(t('attendance.saveFailed'));
    } finally {
      setSaving(false);
    }
  };

  const columns = [
    {
      key: 'select',
      title: '',
      render: (row: AttendanceRow) =>
        !row.is_approved ? (
          <input
            type="checkbox"
            checked={selected.includes(row.id)}
            onChange={() => toggle(row.id)}
          />
        ) : null,
    },
    {
      key: 'user',
      title: t('attendance.employee'),
      render: (row: AttendanceRow) => row.user?.name || '—',
    },
    {
      key: 'date',
      title: t('attendance.date'),
      render: (row: AttendanceRow) => row.date,
    },
    {
      key: 'clock_in',
      title: t('attendance.clockIn'),
      render: (row: AttendanceRow) => formatTime(row.clock_in),
    },
    {
      key: 'clock_out',
      title: t('attendance.clockOut'),
      render: (row: AttendanceRow) => formatTime(row.clock_out),
    },
    {
      key: 'hours',
      title: t('attendance.hours'),
      render: (row: AttendanceRow) =>
        row.total_hours != null ? Number(row.total_hours).toFixed(2) : '—',
    },
    {
      key: 'status',
      title: t('attendance.status'),
      render: (row: AttendanceRow) => row.status,
    },
    {
      key: 'approved',
      title: t('attendance.approved'),
      render: (row: AttendanceRow) => (row.is_approved ? t('attendance.yes') : t('attendance.no')),
    },
    {
      key: 'actions',
      title: '',
      render: (row: AttendanceRow) => (
        <div style={{ display: 'flex', gap: '0.35rem' }}>
          <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => openEdit(row)}>
            <BsPencil />
          </button>
          {!row.is_approved ? (
            <button
              type="button"
              className="btn btn-sm btn-success"
              disabled={actionLoading}
              onClick={() => void approveOne(row.id)}
            >
              <BsCheck /> {t('attendance.approve')}
            </button>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div className="page-container">
      <div
        className="page-header"
        style={{
          marginBottom: '1rem',
          display: 'flex',
          justifyContent: 'space-between',
          gap: '1rem',
          flexWrap: 'wrap',
        }}
      >
        <h1 style={{ margin: 0 }}>{t('attendance.title')}</h1>
        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <input
            type="date"
            className="form-control"
            value={date}
            onChange={(e) => {
              setDate(e.target.value);
              setPage(1);
            }}
          />
          <button type="button" className="btn btn-outline-primary" onClick={openCreate}>
            <BsPlus /> {t('attendance.create')}
          </button>
          <button
            type="button"
            className="btn btn-primary"
            disabled={actionLoading || selected.length === 0}
            onClick={() => void bulkApprove()}
          >
            <BsCheck2All /> {t('attendance.bulkApprove')}
          </button>
        </div>
      </div>

      {summary && (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
            gap: '0.75rem',
            marginBottom: '1rem',
          }}
        >
          <SummaryCard label={t('attendance.summaryPresent')} value={summary.present} />
          <SummaryCard label={t('attendance.summaryAbsent')} value={summary.absent} />
          <SummaryCard label={t('attendance.summaryLate')} value={summary.late} />
          <SummaryCard label={t('attendance.summaryLeave')} value={summary.on_leave} />
        </div>
      )}

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        emptyMessage={t('attendance.empty')}
        currentPage={page}
        totalPages={totalPages}
        onPageChange={setPage}
      />

      {modalOpen && (
        <div className="modal-overlay" onClick={() => setModalOpen(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 480 }}>
            <h2 style={{ marginTop: 0 }}>
              {editing ? t('attendance.edit') : t('attendance.create')}
            </h2>
            {!editing && (
              <div className="form-group">
                <label className="form-label">{t('attendance.employee')}</label>
                <select
                  className="form-control"
                  value={form.user_id}
                  onChange={(e) => setForm({ ...form, user_id: e.target.value })}
                >
                  <option value="">{t('attendance.selectEmployee')}</option>
                  {employeeOptions.map((o) => (
                    <option key={o.userId} value={o.userId}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </div>
            )}
            {!editing && (
              <div className="form-group">
                <label className="form-label">{t('attendance.date')}</label>
                <input
                  type="date"
                  className="form-control"
                  value={form.date}
                  onChange={(e) => setForm({ ...form, date: e.target.value })}
                />
              </div>
            )}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
              <div className="form-group">
                <label className="form-label">{t('attendance.clockIn')}</label>
                <input
                  type="time"
                  className="form-control"
                  value={form.clock_in}
                  onChange={(e) => setForm({ ...form, clock_in: e.target.value })}
                />
              </div>
              <div className="form-group">
                <label className="form-label">{t('attendance.clockOut')}</label>
                <input
                  type="time"
                  className="form-control"
                  value={form.clock_out}
                  onChange={(e) => setForm({ ...form, clock_out: e.target.value })}
                />
              </div>
            </div>
            <div className="form-group">
              <label className="form-label">{t('attendance.status')}</label>
              <select
                className="form-control"
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
              >
                <option value="present">present</option>
                <option value="absent">absent</option>
                <option value="late">late</option>
                <option value="early_leave">early_leave</option>
                <option value="leave">leave</option>
                <option value="holiday">holiday</option>
              </select>
            </div>
            <div className="form-group">
              <label className="form-label">{t('attendance.notes')}</label>
              <input
                className="form-control"
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label className="form-label">{t('attendance.reason')}</label>
              <textarea
                className="form-control"
                rows={3}
                value={form.reason}
                onChange={(e) => setForm({ ...form, reason: e.target.value })}
              />
            </div>
            <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end' }}>
              <button type="button" className="btn btn-outline-secondary" onClick={() => setModalOpen(false)}>
                {t('attendance.cancel')}
              </button>
              <button type="button" className="btn btn-primary" disabled={saving} onClick={() => void save()}>
                {saving ? t('attendance.saving') : t('attendance.save')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

function SummaryCard({ label, value }: { label: string; value: number }) {
  return (
    <div
      style={{
        padding: '0.75rem 1rem',
        background: 'var(--bg-secondary)',
        borderRadius: 'var(--radius-md)',
        border: '1px solid var(--border-color)',
      }}
    >
      <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>{label}</div>
      <div style={{ fontSize: '1.25rem', fontWeight: 600 }}>{value}</div>
    </div>
  );
}

export default AttendancePage;

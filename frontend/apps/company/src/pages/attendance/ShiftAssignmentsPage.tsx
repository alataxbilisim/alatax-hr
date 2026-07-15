import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { employeesApi, shiftsApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { DataTable } from '../../components/ui';
import { BsTrash } from 'react-icons/bs';

interface ShiftOption {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
}

interface EmployeeOption {
  id: number;
  user_id?: number | null;
  first_name?: string;
  last_name?: string;
  user?: { id: number; name: string };
}

interface AssignmentRow {
  id: number;
  date: string;
  user_id: number;
  shift_id: number;
  user?: { id: number; name: string };
  shift?: { id: number; name: string; start_time: string; end_time: string };
}

function mondayOf(d: Date): string {
  const x = new Date(d);
  const day = x.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  x.setDate(x.getDate() + diff);
  return x.toISOString().slice(0, 10);
}

function addDays(dateStr: string, days: number): string {
  const x = new Date(dateStr + 'T12:00:00');
  x.setDate(x.getDate() + days);
  return x.toISOString().slice(0, 10);
}

function formatTime(value: string): string {
  return value.length >= 5 ? value.slice(0, 5) : value;
}

function getErrorStatus(err: unknown): number | undefined {
  if (!err || typeof err !== 'object' || !('response' in err)) return undefined;
  const response = err.response;
  if (!response || typeof response !== 'object' || !('status' in response)) return undefined;
  const status = response.status;
  return typeof status === 'number' ? status : undefined;
}

const ShiftAssignmentsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [weekStart, setWeekStart] = useState(mondayOf(new Date()));
  const [rows, setRows] = useState<AssignmentRow[]>([]);
  const [shifts, setShifts] = useState<ShiftOption[]>([]);
  const [employees, setEmployees] = useState<EmployeeOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [userId, setUserId] = useState('');
  const [shiftId, setShiftId] = useState('');
  const [startDate, setStartDate] = useState(mondayOf(new Date()));
  const [endDate, setEndDate] = useState(addDays(mondayOf(new Date()), 6));

  const weekEnd = useMemo(() => addDays(weekStart, 6), [weekStart]);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const [assignRes, shiftRes, empRes] = await Promise.all([
        shiftsApi.assignments.list({ start_date: weekStart, end_date: weekEnd, per_page: 200 }),
        shiftsApi.list({ active_only: true, per_page: 100 }),
        employeesApi.getAll({ per_page: 200, status: 'active' }),
      ]);
      const aData = assignRes.data.data;
      setRows(Array.isArray(aData) ? aData : aData?.data || []);
      const sData = shiftRes.data.data;
      setShifts(Array.isArray(sData) ? sData : sData?.data || []);
      const eData = empRes.data.data;
      setEmployees(Array.isArray(eData) ? eData : eData?.data || []);
    } catch {
      toast.error(t('shiftAssign.loadFailed'));
    } finally {
      setLoading(false);
    }
  }, [t, weekStart, weekEnd]);

  useEffect(() => {
    void load();
  }, [load]);

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

  const assign = async () => {
    if (!userId || !shiftId || !startDate || !endDate) {
      toast.error(t('shiftAssign.formRequired'));
      return;
    }
    try {
      setSaving(true);
      if (startDate === endDate) {
        await shiftsApi.assignments.assign({
          user_id: Number(userId),
          shift_id: Number(shiftId),
          date: startDate,
        });
      } else {
        await shiftsApi.assignments.bulkAssign({
          user_ids: [Number(userId)],
          shift_id: Number(shiftId),
          start_date: startDate,
          end_date: endDate,
        });
      }
      toast.success(t('shiftAssign.assignSuccess'));
      await load();
    } catch (err: unknown) {
      toast.error(
        getErrorStatus(err) === 403 ? t('shiftAssign.forbidden') : t('shiftAssign.assignFailed')
      );
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id: number) => {
    try {
      await shiftsApi.assignments.remove(id);
      toast.success(t('shiftAssign.removeSuccess'));
      await load();
    } catch {
      toast.error(t('shiftAssign.removeFailed'));
    }
  };

  const columns = [
    {
      key: 'user',
      title: t('shiftAssign.employee'),
      render: (row: AssignmentRow) => row.user?.name || `#${row.user_id}`,
    },
    { key: 'date', title: t('shiftAssign.date'), render: (row: AssignmentRow) => row.date },
    {
      key: 'shift',
      title: t('shiftAssign.shift'),
      render: (row: AssignmentRow) => row.shift?.name || `#${row.shift_id}`,
    },
    {
      key: 'hours',
      title: t('shiftAssign.hours'),
      render: (row: AssignmentRow) =>
        row.shift
          ? `${formatTime(row.shift.start_time)} – ${formatTime(row.shift.end_time)}`
          : '—',
    },
    {
      key: 'actions',
      title: '',
      render: (row: AssignmentRow) => (
        <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => void remove(row.id)}>
          <BsTrash />
        </button>
      ),
    },
  ];

  return (
    <div className="page-container">
      <div className="page-header" style={{ marginBottom: '1rem' }}>
        <h1 style={{ margin: 0 }}>{t('shiftAssign.title')}</h1>
      </div>

      <div
        className="card"
        style={{ marginBottom: '1rem', padding: '1rem', display: 'grid', gap: '0.75rem' }}
      >
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '0.75rem' }}>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('shiftAssign.employee')}</label>
            <select className="form-control" value={userId} onChange={(e) => setUserId(e.target.value)}>
              <option value="">{t('shiftAssign.selectEmployee')}</option>
              {employeeOptions.map((o) => (
                <option key={o.userId} value={o.userId}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('shiftAssign.shift')}</label>
            <select className="form-control" value={shiftId} onChange={(e) => setShiftId(e.target.value)}>
              <option value="">{t('shiftAssign.selectShift')}</option>
              {shifts.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({formatTime(s.start_time)}–{formatTime(s.end_time)})
                </option>
              ))}
            </select>
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('shiftAssign.startDate')}</label>
            <input type="date" className="form-control" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('shiftAssign.endDate')}</label>
            <input type="date" className="form-control" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
          </div>
        </div>
        <div>
          <button type="button" className="btn btn-primary" disabled={saving} onClick={() => void assign()}>
            {saving ? t('shiftAssign.saving') : t('shiftAssign.assign')}
          </button>
        </div>
      </div>

      <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginBottom: '0.75rem' }}>
        <label className="form-label" style={{ margin: 0 }}>
          {t('shiftAssign.week')}
        </label>
        <input
          type="date"
          className="form-control"
          style={{ maxWidth: 180 }}
          value={weekStart}
          onChange={(e) => setWeekStart(mondayOf(new Date(e.target.value + 'T12:00:00')))}
        />
        <span className="text-muted">
          {weekStart} → {weekEnd}
        </span>
      </div>

      <DataTable columns={columns} data={rows} loading={loading} />
    </div>
  );
};

export default ShiftAssignmentsPage;

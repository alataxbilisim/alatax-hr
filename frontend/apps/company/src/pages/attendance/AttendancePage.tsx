import React, { useCallback, useEffect, useState } from 'react';
import { attendanceApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { DataTable } from '../../components/ui';
import { BsCheck2All, BsCheck } from 'react-icons/bs';

interface AttendanceRow {
  id: number;
  date: string;
  clock_in?: string | null;
  clock_out?: string | null;
  total_hours?: number | string | null;
  status: string;
  is_approved: boolean;
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

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const [listRes, summaryRes] = await Promise.all([
        attendanceApi.list({ date, page, per_page: 50 }),
        attendanceApi.dailySummary(date),
      ]);
      const data = listRes.data.data;
      const list = Array.isArray(data) ? data : data?.data || [];
      setRows(list);
      setTotalPages(listRes.data.meta?.last_page || 1);
      setSummary(summaryRes.data.data as DailySummary);
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
      const count = (res.data.data as { approved_count?: number })?.approved_count ?? selected.length;
      toast.success(t('attendance.bulkApproveSuccess', { count }));
      await load();
    } catch {
      toast.error(t('attendance.actionFailed'));
    } finally {
      setActionLoading(false);
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
      render: (row: AttendanceRow) =>
        !row.is_approved ? (
          <button
            type="button"
            className="btn btn-sm btn-success"
            disabled={actionLoading}
            onClick={() => void approveOne(row.id)}
          >
            <BsCheck /> {t('attendance.approve')}
          </button>
        ) : null,
    },
  ];

  return (
    <div className="page-container">
      <div className="page-header" style={{ marginBottom: '1rem', display: 'flex', justifyContent: 'space-between', gap: '1rem', flexWrap: 'wrap' }}>
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

function nowDate(): string {
  const d = new Date();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${m}-${day}`;
}

function formatTime(value?: string | null): string {
  if (!value) return '—';
  if (value.length >= 5) return value.slice(0, 5);
  return value;
}

export default AttendancePage;

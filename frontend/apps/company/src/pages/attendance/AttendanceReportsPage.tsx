import React, { useCallback, useEffect, useState } from 'react';
import { attendanceApi, branchesApi, departmentsApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { DataTable } from '../../components/ui';
import { BsDownload } from 'react-icons/bs';

interface ReportRow {
  id: number;
  user_name: string | null;
  date: string;
  clock_in: string | null;
  clock_out: string | null;
  status: string;
  late_minutes: number;
  early_leave_minutes: number;
  missing_minutes: number;
  overtime_hours: number;
  total_hours: number | null;
}

interface Totals {
  late_minutes: number;
  early_leave_minutes: number;
  overtime_hours: number;
  missing_minutes: number;
  absent_days: number;
  late_days: number;
  early_leave_days: number;
  record_count: number;
}

interface Option {
  id: number;
  name: string;
}

function monthStart(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
}

function today(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function readNumber(obj: object, key: string): number {
  if (!(key in obj)) return 0;
  const value = Object.getOwnPropertyDescriptor(obj, key)?.value;
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function parseTotals(data: unknown): Totals | null {
  if (!data || typeof data !== 'object' || !('totals' in data)) return null;
  const totals = data.totals;
  if (!totals || typeof totals !== 'object') return null;
  return {
    late_minutes: readNumber(totals, 'late_minutes'),
    early_leave_minutes: readNumber(totals, 'early_leave_minutes'),
    overtime_hours: readNumber(totals, 'overtime_hours'),
    missing_minutes: readNumber(totals, 'missing_minutes'),
    absent_days: readNumber(totals, 'absent_days'),
    late_days: readNumber(totals, 'late_days'),
    early_leave_days: readNumber(totals, 'early_leave_days'),
    record_count: readNumber(totals, 'record_count'),
  };
}

function parseRows(data: unknown): ReportRow[] {
  if (!data || typeof data !== 'object' || !('rows' in data)) return [];
  const rows = data.rows;
  return Array.isArray(rows) ? rows : [];
}

const AttendanceReportsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [startDate, setStartDate] = useState(monthStart());
  const [endDate, setEndDate] = useState(today());
  const [departmentId, setDepartmentId] = useState('');
  const [branchId, setBranchId] = useState('');
  const [departments, setDepartments] = useState<Option[]>([]);
  const [branches, setBranches] = useState<Option[]>([]);
  const [rows, setRows] = useState<ReportRow[]>([]);
  const [totals, setTotals] = useState<Totals | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadedOnce, setLoadedOnce] = useState(false);

  useEffect(() => {
    void Promise.all([
      departmentsApi.getAll({ per_page: 100 }),
      branchesApi.list({ per_page: 100 }),
    ])
      .then(([deptRes, branchRes]) => {
        const d = deptRes.data.data;
        setDepartments(Array.isArray(d) ? d : d?.data || []);
        const b = branchRes.data.data;
        setBranches(Array.isArray(b) ? b : b?.data || []);
      })
      .catch(() => undefined);
  }, []);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await attendanceApi.report({
        start_date: startDate,
        end_date: endDate,
        department_id: departmentId || undefined,
        branch_id: branchId || undefined,
      });
      const payload = res.data.data;
      setRows(parseRows(payload));
      setTotals(parseTotals(payload));
      setLoadedOnce(true);
    } catch {
      toast.error(t('attendanceReport.loadFailed'));
    } finally {
      setLoading(false);
    }
  }, [startDate, endDate, departmentId, branchId, t]);

  const exportExcel = async () => {
    try {
      const res = await attendanceApi.reportExport({
        start_date: startDate,
        end_date: endDate,
        department_id: departmentId || undefined,
        branch_id: branchId || undefined,
      });
      const blob = new Blob([res.data], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `puantaj_${startDate}_${endDate}.xlsx`;
      a.click();
      URL.revokeObjectURL(url);
      toast.success(t('attendanceReport.exportSuccess'));
    } catch {
      toast.error(t('attendanceReport.exportFailed'));
    }
  };

  const columns = [
    { key: 'user', title: t('attendanceReport.employee'), render: (r: ReportRow) => r.user_name || '—' },
    { key: 'date', title: t('attendanceReport.date'), render: (r: ReportRow) => r.date },
    { key: 'status', title: t('attendanceReport.status'), render: (r: ReportRow) => r.status },
    { key: 'late', title: t('attendanceReport.late'), render: (r: ReportRow) => String(r.late_minutes) },
    { key: 'early', title: t('attendanceReport.early'), render: (r: ReportRow) => String(r.early_leave_minutes) },
    { key: 'missing', title: t('attendanceReport.missing'), render: (r: ReportRow) => String(r.missing_minutes) },
    { key: 'ot', title: t('attendanceReport.overtime'), render: (r: ReportRow) => String(r.overtime_hours) },
  ];

  return (
    <div className="page-container">
      <div className="page-header" style={{ marginBottom: '1rem' }}>
        <h1 style={{ margin: 0 }}>{t('attendanceReport.title')}</h1>
      </div>

      <div
        className="card"
        style={{ padding: '1rem', marginBottom: '1rem', display: 'grid', gap: '0.75rem' }}
      >
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: '0.75rem' }}>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('attendanceReport.start')}</label>
            <input type="date" className="form-control" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('attendanceReport.end')}</label>
            <input type="date" className="form-control" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('attendanceReport.department')}</label>
            <select className="form-control" value={departmentId} onChange={(e) => setDepartmentId(e.target.value)}>
              <option value="">{t('attendanceReport.all')}</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name}
                </option>
              ))}
            </select>
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <label className="form-label">{t('attendanceReport.branch')}</label>
            <select className="form-control" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
              <option value="">{t('attendanceReport.all')}</option>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </select>
          </div>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button type="button" className="btn btn-primary" disabled={loading} onClick={() => void load()}>
            {loading ? t('attendanceReport.loading') : t('attendanceReport.run')}
          </button>
          <button
            type="button"
            className="btn btn-outline-primary"
            disabled={!loadedOnce}
            onClick={() => void exportExcel()}
          >
            <BsDownload /> {t('attendanceReport.export')}
          </button>
        </div>
      </div>

      {totals && (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
            gap: '0.75rem',
            marginBottom: '1rem',
          }}
        >
          <Stat label={t('attendanceReport.lateTotal')} value={totals.late_minutes} />
          <Stat label={t('attendanceReport.earlyTotal')} value={totals.early_leave_minutes} />
          <Stat label={t('attendanceReport.otTotal')} value={totals.overtime_hours} />
          <Stat label={t('attendanceReport.missingTotal')} value={totals.missing_minutes} />
          <Stat label={t('attendanceReport.absentDays')} value={totals.absent_days} />
        </div>
      )}

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        emptyMessage={t('attendanceReport.empty')}
      />
    </div>
  );
};

function Stat({ label, value }: { label: string; value: number }) {
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

export default AttendanceReportsPage;

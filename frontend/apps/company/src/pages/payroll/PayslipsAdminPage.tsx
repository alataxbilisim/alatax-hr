import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { payslipsApi, employeesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, Modal, type Column } from '../../components/ui';
import { BsPlus, BsFileEarmarkPdf, BsTrash, BsBroadcast } from 'react-icons/bs';

interface PayslipRow {
  id: number;
  employee_id: number;
  employee_name?: string;
  employee_code?: string;
  period: string;
  period_label?: string;
  is_published: boolean;
  has_file: boolean;
  net_salary?: string | number;
}

const PayslipsAdminPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [rows, setRows] = useState<PayslipRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<PayslipRow | null>(null);
  const [employees, setEmployees] = useState<Array<{ id: number; name?: string; employee_code?: string }>>([]);
  const [employeeId, setEmployeeId] = useState('');
  const [period, setPeriod] = useState('');
  const [netSalary, setNetSalary] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [publishNow, setPublishNow] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await payslipsApi.list({ page, per_page: 20 });
      const data = res.data.data;
      setRows(Array.isArray(data) ? data : []);
      setTotalPages(res.data.meta?.last_page ?? 1);
    } catch {
      toast.error(t('payslipsAdmin.loadError'));
    } finally {
      setLoading(false);
    }
  }, [page, t]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void (async () => {
      try {
        const res = await employeesApi.getAll({ per_page: 200 });
        const data = res.data.data;
        const list: Array<{
          id: number;
          employee_code?: string;
          user?: { name?: string };
          name?: string;
        }> = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
        setEmployees(
          list.map((e) => ({
            id: e.id,
            employee_code: e.employee_code,
            name: e.user?.name ?? e.name,
          }))
        );
      } catch {
        /* opsiyonel */
      }
    })();
  }, []);

  const handleUpload = async () => {
    if (!employeeId || !period) {
      toast.error(t('payslipsAdmin.validation'));
      return;
    }
    setSubmitting(true);
    try {
      const fd = new FormData();
      fd.append('employee_id', employeeId);
      fd.append('period', period);
      if (netSalary) fd.append('net_salary', netSalary);
      fd.append('publish', publishNow ? '1' : '0');
      if (file) fd.append('file', file);
      await payslipsApi.create(fd);
      toast.success(t('payslipsAdmin.saveSuccess'));
      setShowModal(false);
      setEmployeeId('');
      setPeriod('');
      setNetSalary('');
      setFile(null);
      await load();
    } catch {
      toast.error(t('payslipsAdmin.saveError'));
    } finally {
      setSubmitting(false);
    }
  };

  const columns: Column<PayslipRow>[] = [
    {
      key: 'employee',
      title: t('payslipsAdmin.colEmployee'),
      render: (r) => `${r.employee_name ?? '—'} (${r.employee_code ?? r.employee_id})`,
    },
    { key: 'period', title: t('payslipsAdmin.colPeriod'), render: (r) => r.period_label ?? r.period },
    {
      key: 'status',
      title: t('payslipsAdmin.colStatus'),
      render: (r) => (r.is_published ? t('payslipsAdmin.published') : t('payslipsAdmin.draft')),
    },
    {
      key: 'actions',
      title: t('payslipsAdmin.colActions'),
      render: (r) => (
        <div style={{ display: 'flex', gap: 'var(--space-2)' }}>
          {!r.is_published ? (
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={() => {
                void (async () => {
                  try {
                    await payslipsApi.publish(r.id);
                    toast.success(t('payslipsAdmin.publishSuccess'));
                    await load();
                  } catch {
                    toast.error(t('payslipsAdmin.publishError'));
                  }
                })();
              }}
            >
              <BsBroadcast /> {t('payslipsAdmin.publish')}
            </button>
          ) : null}
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => setDeleteTarget(r)}>
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)', display: 'flex', justifyContent: 'space-between' }}>
        <div>
          <h1 className="page-title">{t('payslipsAdmin.title')}</h1>
          <p className="page-subtitle">{t('payslipsAdmin.subtitle')}</p>
        </div>
        <button type="button" className="btn btn-primary" onClick={() => setShowModal(true)}>
          <BsPlus /> {t('payslipsAdmin.upload')}
        </button>
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        emptyIcon={<BsFileEarmarkPdf />}
        emptyMessage={t('payslipsAdmin.empty')}
        currentPage={page}
        totalPages={totalPages}
        onPageChange={setPage}
      />

      <Modal isOpen={showModal} onClose={() => setShowModal(false)} title={t('payslipsAdmin.upload')}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
          <label className="form-label">{t('payslipsAdmin.colEmployee')}</label>
          <select className="form-control" value={employeeId} onChange={(e) => setEmployeeId(e.target.value)}>
            <option value="">{t('payslipsAdmin.selectEmployee')}</option>
            {employees.map((e) => (
              <option key={e.id} value={e.id}>
                {e.name ?? e.id} ({e.employee_code ?? e.id})
              </option>
            ))}
          </select>
          <label className="form-label">{t('payslipsAdmin.colPeriod')}</label>
          <input
            className="form-control"
            type="month"
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
          />
          <label className="form-label">{t('payslipsAdmin.netSalary')}</label>
          <input
            className="form-control"
            type="number"
            value={netSalary}
            onChange={(e) => setNetSalary(e.target.value)}
          />
          <label className="form-label">{t('payslipsAdmin.file')}</label>
          <input
            className="form-control"
            type="file"
            accept="application/pdf"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
          <label style={{ display: 'flex', gap: 'var(--space-2)', alignItems: 'center' }}>
            <input type="checkbox" checked={publishNow} onChange={(e) => setPublishNow(e.target.checked)} />
            {t('payslipsAdmin.publishNow')}
          </label>
          <button type="button" className="btn btn-primary" disabled={submitting} onClick={() => void handleUpload()}>
            {t('save')}
          </button>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => {
          void (async () => {
            if (!deleteTarget) return;
            try {
              await payslipsApi.delete(deleteTarget.id);
              toast.success(t('payslipsAdmin.deleteSuccess'));
              setDeleteTarget(null);
              await load();
            } catch {
              toast.error(t('payslipsAdmin.deleteError'));
            }
          })();
        }}
        title={t('payslipsAdmin.deleteTitle')}
        message={t('payslipsAdmin.deleteConfirm')}
      />
    </div>
  );
};

export default PayslipsAdminPage;

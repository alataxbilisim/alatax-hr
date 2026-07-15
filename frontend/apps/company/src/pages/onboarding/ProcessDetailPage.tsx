import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { onboardingApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import TaskList from '../../components/onboarding/TaskList';
import {
  BsArrowLeft,
  BsPersonCheck,
  BsCalendar,
  BsClock,
  BsCheckCircle,
  BsDownload,
} from 'react-icons/bs';

interface Task {
  id: number;
  title: string;
  description?: string;
  type: string;
  status: string;
  is_required: boolean;
  due_date?: string;
  completed_at?: string;
  completed_by?: { id: number; name: string };
  data?: {
    action_key?: string;
    open_assignments?: Array<{ asset_name?: string; asset_code?: string }>;
    open_count?: number;
  };
}

interface Process {
  id: number;
  title: string;
  process_type?: 'onboarding' | 'offboarding';
  user: { id: number; name: string; email: string };
  template?: { id: number; name: string };
  assigned_to?: { id: number; name: string };
  status: string;
  progress: number;
  start_date: string;
  target_end_date?: string;
  actual_end_date?: string;
  notes?: string;
  remaining_leave_days?: number | string | null;
  termination_reason_code?: string | null;
  termination_date?: string | null;
  tasks: Task[];
  created_at: string;
}

const statusColors: Record<string, string> = {
  pending: 'var(--warning)',
  in_progress: 'var(--info)',
  completed: 'var(--success)',
  cancelled: 'var(--danger)',
};

const ProcessDetailPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [process, setProcess] = useState<Process | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [processStatusOptions, setProcessStatusOptions] = useState<LookupItem[]>([]);

  const processStatusLabel = (value: string) =>
    processStatusOptions.find((o) => o.value === value)?.label || value;

  const loadProcess = useCallback(async () => {
    try {
      setLoading(true);
      const response = await onboardingApi.processes.get(Number(id));
      setProcess(response.data.data);
    } catch {
      toast.error('Süreç yüklenemedi');
      navigate('/onboarding');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  useEffect(() => {
    void lookupsApi.forType('onboarding_process_status')
      .then((res) => setProcessStatusOptions(res.data.data ?? []))
      .catch(() => console.error('Onboarding process status lookup yüklenemedi'));
  }, []);

  useEffect(() => {
    if (id) {
      void loadProcess();
    }
  }, [id, loadProcess]);

  const handleCompleteTask = async (taskId: number) => {
    try {
      await onboardingApi.processes.completeTask(Number(id), taskId, {});
      toast.success('Görev tamamlandı');
      void loadProcess();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Görev tamamlanamadı'));
    }
  };

  const handleSkipTask = async (taskId: number) => {
    try {
      await onboardingApi.processes.skipTask(Number(id), taskId);
      toast.success('Görev atlandı');
      void loadProcess();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleFinalize = async () => {
    try {
      setActionLoading(true);
      await onboardingApi.processes.finalizeOffboarding(Number(id));
      toast.success(t('offboarding.finalizeSuccess'));
      void loadProcess();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('offboarding.finalizeError')));
    } finally {
      setActionLoading(false);
    }
  };

  const handleCancel = async () => {
    try {
      setActionLoading(true);
      await onboardingApi.processes.cancelOffboarding(Number(id));
      toast.success(t('offboarding.cancelSuccess'));
      void loadProcess();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('offboarding.cancelError')));
    } finally {
      setActionLoading(false);
    }
  };

  const handleDownloadClearance = async () => {
    try {
      const res = await onboardingApi.processes.clearanceForm(Number(id));
      const blob = new Blob([res.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `ibraname-${id}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
      toast.success(t('offboarding.clearanceSuccess'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('offboarding.clearanceError')));
    }
  };

  const formatDate = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  if (loading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner" />
      </div>
    );
  }

  if (!process) {
    return null;
  }

  const completedTasks = process.tasks?.filter((task) => task.status === 'completed').length || 0;
  const totalTasks = process.tasks?.length || 0;
  const isOffboarding = process.process_type === 'offboarding';
  const isActiveProcess = process.status === 'pending' || process.status === 'in_progress';
  const openRequired = process.tasks?.filter(
    (task) => task.is_required && task.status !== 'completed' && task.status !== 'skipped'
  ).length ?? 0;

  return (
    <div className="animate-fade-in">
      <div style={{ marginBottom: '1.5rem' }}>
        <button
          className="btn btn-ghost btn-sm"
          onClick={() => navigate('/onboarding')}
          style={{ marginBottom: '1rem' }}
        >
          <BsArrowLeft size={16} />
          Geri
        </button>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem' }}>
          <div>
            <h1 className="page-title">{process.title}</h1>
            <p className="page-subtitle">
              {process.user?.name} • {process.template?.name || 'Özel Süreç'}
            </p>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '0.5rem' }}>
            <div
              style={{
                padding: '0.5rem 1rem',
                borderRadius: 'var(--radius-md)',
                background: statusColors[process.status] || 'var(--text-tertiary)',
                color: 'white',
                fontWeight: 500,
              }}
            >
              {processStatusLabel(process.status)}
            </div>
            {isOffboarding && (
              <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => void handleDownloadClearance()}>
                  <BsDownload /> {t('offboarding.downloadClearance')}
                </button>
                {isActiveProcess && (
                  <>
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      disabled={actionLoading}
                      onClick={() => void handleCancel()}
                    >
                      {t('offboarding.cancelProcess')}
                    </button>
                    <button
                      type="button"
                      className="btn btn-primary btn-sm"
                      disabled={actionLoading || openRequired > 0}
                      onClick={() => void handleFinalize()}
                    >
                      {t('offboarding.finalize')}
                    </button>
                  </>
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {isOffboarding && (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-body" style={{ padding: '1rem', display: 'flex', flexWrap: 'wrap', gap: '1.5rem' }}>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{t('offboarding.reasonCode')}</div>
              <div style={{ fontWeight: 500 }}>{process.termination_reason_code || '-'}</div>
            </div>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{t('offboarding.terminationDate')}</div>
              <div style={{ fontWeight: 500 }}>{formatDate(process.termination_date)}</div>
            </div>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{t('offboarding.remainingLeave', { days: process.remaining_leave_days ?? 0 })}</div>
            </div>
          </div>
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        <div className="card">
          <div className="card-body" style={{ padding: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{ padding: '0.5rem', background: 'var(--accent-bg)', borderRadius: 'var(--radius-md)' }}>
                <BsPersonCheck size={20} style={{ color: 'var(--accent)' }} />
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Çalışan</div>
                <div style={{ fontWeight: 500 }}>{process.user?.name}</div>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body" style={{ padding: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{ padding: '0.5rem', background: 'var(--info-bg)', borderRadius: 'var(--radius-md)' }}>
                <BsCalendar size={20} style={{ color: 'var(--info)' }} />
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Başlangıç</div>
                <div style={{ fontWeight: 500 }}>{formatDate(process.start_date)}</div>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body" style={{ padding: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{ padding: '0.5rem', background: 'var(--warning-bg)', borderRadius: 'var(--radius-md)' }}>
                <BsClock size={20} style={{ color: 'var(--warning)' }} />
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Hedef Bitiş</div>
                <div style={{ fontWeight: 500 }}>{formatDate(process.target_end_date)}</div>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body" style={{ padding: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{ padding: '0.5rem', background: 'var(--success-bg)', borderRadius: 'var(--radius-md)' }}>
                <BsCheckCircle size={20} style={{ color: 'var(--success)' }} />
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>İlerleme</div>
                <div style={{ fontWeight: 500 }}>{completedTasks}/{totalTasks} görev ({process.progress || 0}%)</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-body" style={{ padding: '1rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <span style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>İlerleme</span>
            <div style={{
              flex: 1,
              height: '8px',
              background: 'var(--bg-tertiary)',
              borderRadius: '4px',
              overflow: 'hidden',
            }}>
              <div style={{
                width: `${process.progress || 0}%`,
                height: '100%',
                background: 'var(--accent)',
                transition: 'width 0.3s',
              }} />
            </div>
            <span style={{ fontSize: '0.875rem', fontWeight: 500 }}>{process.progress || 0}%</span>
          </div>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">Görevler</h3>
        </div>
        <div className="card-body">
          <TaskList
            tasks={process.tasks || []}
            onComplete={handleCompleteTask}
            onSkip={handleSkipTask}
            readonly={process.status === 'completed' || process.status === 'cancelled'}
          />
        </div>
      </div>

      {process.notes && (
        <div className="card" style={{ marginTop: '1.5rem' }}>
          <div className="card-header">
            <h3 className="card-title">Notlar</h3>
          </div>
          <div className="card-body">
            <p style={{ color: 'var(--text-secondary)', whiteSpace: 'pre-wrap' }}>{process.notes}</p>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProcessDetailPage;

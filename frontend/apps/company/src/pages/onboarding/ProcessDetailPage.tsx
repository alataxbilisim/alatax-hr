import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { onboardingApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import TaskList from '../../components/onboarding/TaskList';
import {
  BsArrowLeft,
  BsPersonCheck,
  BsCalendar,
  BsClock,
  BsCheckCircle,
} from 'react-icons/bs';

interface Task {
  id: number;
  title: string;
  description?: string;
  type: string;
  status: 'pending' | 'in_progress' | 'completed' | 'skipped';
  is_required: boolean;
  due_date?: string;
  completed_at?: string;
  completed_by?: { id: number; name: string };
}

interface Process {
  id: number;
  title: string;
  user: { id: number; name: string; email: string };
  template?: { id: number; name: string };
  assigned_to?: { id: number; name: string };
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  progress: number;
  start_date: string;
  target_end_date?: string;
  actual_end_date?: string;
  notes?: string;
  tasks: Task[];
  created_at: string;
}

const statusLabels: Record<string, string> = {
  pending: 'Bekliyor',
  in_progress: 'Devam Ediyor',
  completed: 'Tamamlandı',
  cancelled: 'İptal Edildi',
};

const statusColors: Record<string, string> = {
  pending: 'var(--warning)',
  in_progress: 'var(--info)',
  completed: 'var(--success)',
  cancelled: 'var(--danger)',
};

const ProcessDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [process, setProcess] = useState<Process | null>(null);
  const [loading, setLoading] = useState(true);

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
    if (id) {
      loadProcess();
    }
  }, [id, loadProcess]);

  

  const handleCompleteTask = async (taskId: number) => {
    try {
      await onboardingApi.processes.completeTask(Number(id), taskId, {});
      toast.success('Görev tamamlandı');
      loadProcess();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleSkipTask = async (taskId: number) => {
    try {
      await onboardingApi.processes.skipTask(Number(id), taskId);
      toast.success('Görev atlandı');
      loadProcess();
    } catch {
      // Error handled by interceptor
    }
  };

  const formatDate = (date?: string) => {
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

  const completedTasks = process.tasks?.filter(t => t.status === 'completed').length || 0;
  const totalTasks = process.tasks?.length || 0;

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div style={{ marginBottom: '1.5rem' }}>
        <button
          className="btn btn-ghost btn-sm"
          onClick={() => navigate('/onboarding')}
          style={{ marginBottom: '1rem' }}
        >
          <BsArrowLeft size={16} />
          Geri
        </button>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <h1 className="page-title">{process.title}</h1>
            <p className="page-subtitle">
              {process.user?.name} • {process.template?.name || 'Özel Süreç'}
            </p>
          </div>
          <div
            style={{
              padding: '0.5rem 1rem',
              borderRadius: 'var(--radius-md)',
              background: statusColors[process.status],
              color: 'white',
              fontWeight: 500,
            }}
          >
            {statusLabels[process.status]}
          </div>
        </div>
      </div>

      {/* Stats */}
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

      {/* Progress Bar */}
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

      {/* Tasks */}
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

      {/* Notes */}
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


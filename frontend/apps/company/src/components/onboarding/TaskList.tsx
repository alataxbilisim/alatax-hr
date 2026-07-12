import React from 'react';
import {
  BsCheck2Circle,
  BsCircle,
  BsSkipForward,
  BsFileEarmark,
  BsBook,
  BsPeople,
  BsGear,
  BsQuestionCircle,
  BsListTask,
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
}

interface TaskListProps {
  tasks: Task[];
  onComplete: (taskId: number) => void;
  onSkip: (taskId: number) => void;
  readonly?: boolean;
}

const taskTypeIcons: Record<string, React.ReactNode> = {
  document_upload: <BsFileEarmark size={16} />,
  document_fill: <BsFileEarmark size={16} />,
  training: <BsBook size={16} />,
  meeting: <BsPeople size={16} />,
  system_setup: <BsGear size={16} />,
  quiz: <BsQuestionCircle size={16} />,
  custom: <BsListTask size={16} />,
};

const taskTypeLabels: Record<string, string> = {
  document_upload: 'Evrak Yükleme',
  document_fill: 'Form Doldurma',
  training: 'Eğitim',
  meeting: 'Toplantı',
  system_setup: 'Sistem Kurulumu',
  quiz: 'Quiz/Sınav',
  custom: 'Özel Görev',
};

const statusColors: Record<string, string> = {
  pending: 'var(--text-tertiary)',
  in_progress: 'var(--warning)',
  completed: 'var(--success)',
  skipped: 'var(--text-tertiary)',
};

const TaskList: React.FC<TaskListProps> = ({
  tasks,
  onComplete,
  onSkip,
  readonly = false,
}) => {
  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
      {tasks.map((task, index) => (
        <div
          key={task.id}
          style={{
            display: 'flex',
            alignItems: 'flex-start',
            gap: '0.75rem',
            padding: '1rem',
            background: task.status === 'completed' ? 'var(--bg-tertiary)' : 'var(--bg-secondary)',
            borderRadius: 'var(--radius-md)',
            border: '1px solid var(--border-color)',
            opacity: task.status === 'skipped' ? 0.6 : 1,
          }}
        >
          {/* Status Icon */}
          <div style={{ color: statusColors[task.status], marginTop: '2px' }}>
            {task.status === 'completed' ? (
              <BsCheck2Circle size={20} />
            ) : task.status === 'skipped' ? (
              <BsSkipForward size={20} />
            ) : (
              <BsCircle size={20} />
            )}
          </div>

          {/* Task Info */}
          <div style={{ flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
              <span style={{ fontWeight: 500, color: 'var(--text-primary)' }}>
                {index + 1}. {task.title}
              </span>
              {task.is_required && (
                <span style={{
                  fontSize: '0.625rem',
                  padding: '0.125rem 0.375rem',
                  background: 'var(--danger-bg)',
                  color: 'var(--danger)',
                  borderRadius: 'var(--radius-sm)',
                }}>
                  Zorunlu
                </span>
              )}
            </div>
            
            {task.description && (
              <p style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', margin: '0.25rem 0' }}>
                {task.description}
              </p>
            )}

            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', marginTop: '0.5rem', fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                {taskTypeIcons[task.type] || taskTypeIcons.custom}
                {taskTypeLabels[task.type] || task.type}
              </span>
              {task.due_date && (
                <span>Son Tarih: {formatDate(task.due_date)}</span>
              )}
              {task.completed_at && (
                <span style={{ color: 'var(--success)' }}>
                  Tamamlandı: {formatDate(task.completed_at)}
                  {task.completed_by && ` - ${task.completed_by.name}`}
                </span>
              )}
            </div>
          </div>

          {/* Actions */}
          {!readonly && task.status === 'pending' && (
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <button
                className="btn btn-success btn-sm"
                onClick={() => onComplete(task.id)}
              >
                <BsCheck2Circle size={14} />
                Tamamla
              </button>
              {!task.is_required && (
                <button
                  className="btn btn-ghost btn-sm"
                  onClick={() => onSkip(task.id)}
                >
                  <BsSkipForward size={14} />
                  Atla
                </button>
              )}
            </div>
          )}
        </div>
      ))}

      {tasks.length === 0 && (
        <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
          Henüz görev eklenmemiş
        </div>
      )}
    </div>
  );
};

export default TaskList;


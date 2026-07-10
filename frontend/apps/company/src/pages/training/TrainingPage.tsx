import React, { useEffect, useState, useCallback } from 'react';
import { trainingApi, usersApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, EmptyState, Modal } from '../../components/ui';
import TrainingForm from '../../components/training/TrainingForm';
import SessionForm from '../../components/training/SessionForm';
import {
  BsPlus,
  BsMortarboard,
  BsCalendarEvent,
  BsPencil,
  BsTrash,
  BsPeople,
  BsPersonPlus,
} from 'react-icons/bs';

interface Training {
  id: number;
  title: string;
  description?: string;
  category?: string;
  type: 'online' | 'classroom' | 'hybrid';
  duration_hours?: number;
  is_mandatory: boolean;
  is_active: boolean;
  sessions_count?: number;
}

interface Session {
  id: number;
  training_id: number;
  training?: { id: number; title: string };
  title: string;
  description?: string;
  instructor_name?: string;
  location?: string;
  start_date: string;
  end_date?: string;
  max_participants?: number;
  participants_count?: number;
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
}

interface User {
  id: number;
  name: string;
  email: string;
}

type TabType = 'trainings' | 'sessions';

const typeLabels: Record<string, string> = {
  online: 'Online',
  classroom: 'Sınıf İçi',
  hybrid: 'Hibrit',
};

const statusBadgeClass: Record<string, string> = {
  scheduled: 'badge-info',
  in_progress: 'badge-warning',
  completed: 'badge-success',
  cancelled: 'badge-danger',
};

const statusLabels: Record<string, string> = {
  scheduled: 'Planlandı',
  in_progress: 'Devam Ediyor',
  completed: 'Tamamlandı',
  cancelled: 'İptal Edildi',
};

const TrainingPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<TabType>('trainings');

  // Trainings state
  const [trainings, setTrainings] = useState<Training[]>([]);
  const [trainingsLoading, setTrainingsLoading] = useState(true);
  const [trainingPage, setTrainingPage] = useState(1);
  const [trainingTotalPages, setTrainingTotalPages] = useState(1);
  const [trainingFormOpen, setTrainingFormOpen] = useState(false);
  const [selectedTraining, setSelectedTraining] = useState<Training | null>(null);
  const [categories, setCategories] = useState<string[]>([]);

  // Sessions state
  const [sessions, setSessions] = useState<Session[]>([]);
  const [sessionsLoading, setSessionsLoading] = useState(true);
  const [sessionPage, setSessionPage] = useState(1);
  const [sessionTotalPages, setSessionTotalPages] = useState(1);
  const [sessionFormOpen, setSessionFormOpen] = useState(false);
  const [selectedSession, setSelectedSession] = useState<Session | null>(null);

  // Participants modal
  const [participantsModalOpen, setParticipantsModalOpen] = useState(false);
  const [currentSession, setCurrentSession] = useState<Session | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [addParticipantOpen, setAddParticipantOpen] = useState(false);
  const [selectedUserId, setSelectedUserId] = useState('');

  // Delete state
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [itemToDelete, setItemToDelete] = useState<{ type: 'training' | 'session'; item: Training | Session } | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const loadCategories = useCallback(async () => {
    try {
      const response = await trainingApi.categories();
      setCategories(response.data.data || []);
    } catch {
      // Silent fail
    }
  }, []);

  const loadTrainings = useCallback(async () => {
    try {
      setTrainingsLoading(true);
      const response = await trainingApi.trainings.list({ page: trainingPage });
      const data = response.data.data;
      setTrainings(data.data || []);
      setTrainingTotalPages(data.last_page || 1);
    } catch {
      toast.error('Eğitimler yüklenemedi');
    } finally {
      setTrainingsLoading(false);
    }
  }, [trainingPage]);

  const loadSessions = useCallback(async () => {
    try {
      setSessionsLoading(true);
      const response = await trainingApi.sessions.list({ page: sessionPage });
      const data = response.data.data;
      setSessions(data.data || []);
      setSessionTotalPages(data.last_page || 1);
    } catch {
      toast.error('Oturumlar yüklenemedi');
    } finally {
      setSessionsLoading(false);
    }
  }, [sessionPage]);

  const loadUsers = useCallback(async () => {
    try {
      const response = await usersApi.list({ per_page: 100 });
      setUsers(response.data.data?.data || response.data.data || []);
    } catch {
      toast.error('Kullanıcılar yüklenemedi');
    }
  }, []);

  useEffect(() => {
    if (activeTab === 'trainings') {
      loadTrainings();
      loadCategories();
    } else {
      loadSessions();
    }
  }, [activeTab, loadCategories, loadTrainings, loadSessions]);

  

  

  

  

  const handleTrainingSubmit = async (data: Omit<Training, 'id'>) => {
    if (selectedTraining) {
      await trainingApi.trainings.update(selectedTraining.id, data);
      toast.success('Eğitim güncellendi');
    } else {
      await trainingApi.trainings.create(data);
      toast.success('Eğitim oluşturuldu');
    }
    loadTrainings();
  };

  const handleSessionSubmit = async (data: Omit<Session, 'id'>) => {
    if (selectedSession) {
      await trainingApi.sessions.update(selectedSession.id, data);
      toast.success('Oturum güncellendi');
    } else {
      await trainingApi.sessions.create(data);
      toast.success('Oturum oluşturuldu');
    }
    loadSessions();
  };

  const handleDelete = async () => {
    if (!itemToDelete) return;
    setDeleteLoading(true);
    try {
      if (itemToDelete.type === 'training') {
        await trainingApi.trainings.delete((itemToDelete.item as Training).id);
        toast.success('Eğitim silindi');
        loadTrainings();
      } else {
        await trainingApi.sessions.delete((itemToDelete.item as Session).id);
        toast.success('Oturum silindi');
        loadSessions();
      }
      setDeleteDialogOpen(false);
      setItemToDelete(null);
    } catch {
      // Error handled by interceptor
    } finally {
      setDeleteLoading(false);
    }
  };

  const handleAddParticipant = async () => {
    if (!currentSession || !selectedUserId) return;
    try {
      await trainingApi.sessions.addParticipant(currentSession.id, Number(selectedUserId));
      toast.success('Katılımcı eklendi');
      setAddParticipantOpen(false);
      setSelectedUserId('');
      loadSessions();
    } catch {
      // Error handled by interceptor
    }
  };

  const formatDate = (date: string) => new Date(date).toLocaleString('tr-TR');

  const trainingColumns = [
    {
      key: 'title',
      title: 'Eğitim',
      render: (t: Training) => (
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <span style={{ fontWeight: 500 }}>{t.title}</span>
            {t.is_mandatory && (
              <span className="badge badge-danger" style={{ fontSize: '0.625rem' }}>Zorunlu</span>
            )}
          </div>
          {t.description && (
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{t.description}</div>
          )}
        </div>
      ),
    },
    {
      key: 'category',
      title: 'Kategori',
      render: (t: Training) => t.category || '-',
    },
    {
      key: 'type',
      title: 'Tür',
      render: (t: Training) => typeLabels[t.type],
    },
    {
      key: 'duration',
      title: 'Süre',
      render: (t: Training) => t.duration_hours ? `${t.duration_hours} saat` : '-',
    },
    {
      key: 'sessions',
      title: 'Oturumlar',
      render: (t: Training) => `${t.sessions_count || 0} oturum`,
    },
    {
      key: 'status',
      title: 'Durum',
      render: (t: Training) => (
        <span className={`badge ${t.is_active ? 'badge-success' : 'badge-warning'}`}>
          {t.is_active ? 'Aktif' : 'Pasif'}
        </span>
      ),
    },
    {
      key: 'actions',
      title: '',
      render: (t: Training) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => { setSelectedTraining(t); setTrainingFormOpen(true); }}
            title="Düzenle"
          >
            <BsPencil size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => { setItemToDelete({ type: 'training', item: t }); setDeleteDialogOpen(true); }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  const sessionColumns = [
    {
      key: 'title',
      title: 'Oturum',
      render: (s: Session) => (
        <div>
          <div style={{ fontWeight: 500 }}>{s.title}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {s.training?.title}
          </div>
        </div>
      ),
    },
    {
      key: 'instructor',
      title: 'Eğitmen',
      render: (s: Session) => s.instructor_name || '-',
    },
    {
      key: 'date',
      title: 'Tarih',
      render: (s: Session) => formatDate(s.start_date),
    },
    {
      key: 'participants',
      title: 'Katılımcı',
      render: (s: Session) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <span>{s.participants_count || 0}</span>
          {s.max_participants && <span style={{ color: 'var(--text-tertiary)' }}>/ {s.max_participants}</span>}
        </div>
      ),
    },
    {
      key: 'status',
      title: 'Durum',
      render: (s: Session) => (
        <span className={`badge ${statusBadgeClass[s.status]}`}>
          {statusLabels[s.status]}
        </span>
      ),
    },
    {
      key: 'actions',
      title: '',
      render: (s: Session) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => { setCurrentSession(s); loadUsers(); setParticipantsModalOpen(true); }}
            title="Katılımcılar"
          >
            <BsPeople size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => { setSelectedSession(s); setSessionFormOpen(true); }}
            title="Düzenle"
          >
            <BsPencil size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => { setItemToDelete({ type: 'session', item: s }); setDeleteDialogOpen(true); }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div>
          <h1 className="page-title">Eğitim Yönetimi</h1>
          <p className="page-subtitle">Eğitim katalog ve oturum yönetimi</p>
        </div>
        <button
          className="btn btn-primary"
          onClick={() => {
            if (activeTab === 'trainings') {
              setSelectedTraining(null);
              setTrainingFormOpen(true);
            } else {
              setSelectedSession(null);
              setSessionFormOpen(true);
            }
          }}
        >
          <BsPlus size={18} />
          {activeTab === 'trainings' ? 'Yeni Eğitim' : 'Yeni Oturum'}
        </button>
      </div>

      {/* Tabs */}
      <div className="tabs" style={{ marginBottom: '1.5rem' }}>
        <button
          className={`tab ${activeTab === 'trainings' ? 'active' : ''}`}
          onClick={() => setActiveTab('trainings')}
        >
          <BsMortarboard size={16} />
          Eğitimler
        </button>
        <button
          className={`tab ${activeTab === 'sessions' ? 'active' : ''}`}
          onClick={() => setActiveTab('sessions')}
        >
          <BsCalendarEvent size={16} />
          Oturumlar
        </button>
      </div>

      {/* Content */}
      {activeTab === 'trainings' ? (
        trainingsLoading ? (
          <div className="loading-container"><div className="loading-spinner" /></div>
        ) : trainings.length === 0 ? (
          <EmptyState
            icon={<BsMortarboard size={48} />}
            title="Henüz eğitim yok"
            description="Yeni bir eğitim ekleyerek başlayın."
            action={
              <button className="btn btn-primary" onClick={() => { setSelectedTraining(null); setTrainingFormOpen(true); }}>
                <BsPlus size={18} />
                İlk Eğitimi Ekle
              </button>
            }
          />
        ) : (
          <DataTable
            columns={trainingColumns}
            data={trainings}
            currentPage={trainingPage}
            totalPages={trainingTotalPages}
            onPageChange={setTrainingPage}
          />
        )
      ) : sessionsLoading ? (
        <div className="loading-container"><div className="loading-spinner" /></div>
      ) : sessions.length === 0 ? (
        <EmptyState
          icon={<BsCalendarEvent size={48} />}
          title="Henüz oturum yok"
          description="Eğitim oturumu planlayarak başlayın."
          action={
            <button className="btn btn-primary" onClick={() => { setSelectedSession(null); setSessionFormOpen(true); }}>
              <BsPlus size={18} />
              İlk Oturumu Planla
            </button>
          }
        />
      ) : (
        <DataTable
          columns={sessionColumns}
          data={sessions}
          currentPage={sessionPage}
          totalPages={sessionTotalPages}
          onPageChange={setSessionPage}
        />
      )}

      {/* Training Form */}
      <TrainingForm
        isOpen={trainingFormOpen}
        onClose={() => { setTrainingFormOpen(false); setSelectedTraining(null); }}
        onSubmit={handleTrainingSubmit}
        training={selectedTraining}
        categories={categories}
      />

      {/* Session Form */}
      <SessionForm
        isOpen={sessionFormOpen}
        onClose={() => { setSessionFormOpen(false); setSelectedSession(null); }}
        onSubmit={handleSessionSubmit}
        session={selectedSession}
        trainings={trainings}
      />

      {/* Participants Modal */}
      <Modal
        isOpen={participantsModalOpen}
        onClose={() => setParticipantsModalOpen(false)}
        title={`Katılımcılar - ${currentSession?.title}`}
        size="md"
      >
        <div style={{ marginBottom: '1rem' }}>
          <button
            className="btn btn-primary btn-sm"
            onClick={() => setAddParticipantOpen(true)}
          >
            <BsPersonPlus size={16} />
            Katılımcı Ekle
          </button>
        </div>
        <p style={{ color: 'var(--text-secondary)', textAlign: 'center', padding: '2rem' }}>
          Katılımcı listesi API'den yüklenecek.
        </p>
      </Modal>

      {/* Add Participant Modal */}
      <Modal
        isOpen={addParticipantOpen}
        onClose={() => { setAddParticipantOpen(false); setSelectedUserId(''); }}
        title="Katılımcı Ekle"
        size="sm"
      >
        <div className="form-group">
          <label className="form-label">Kullanıcı</label>
          <select
            value={selectedUserId}
            onChange={(e) => setSelectedUserId(e.target.value)}
            className="form-input"
          >
            <option value="">Seçin...</option>
            {users.map(u => (
              <option key={u.id} value={u.id}>{u.name} ({u.email})</option>
            ))}
          </select>
        </div>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
          <button className="btn btn-ghost" onClick={() => { setAddParticipantOpen(false); setSelectedUserId(''); }}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleAddParticipant} disabled={!selectedUserId}>
            Ekle
          </button>
        </div>
      </Modal>

      {/* Delete Confirm */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => { setDeleteDialogOpen(false); setItemToDelete(null); }}
        onConfirm={handleDelete}
        title={itemToDelete?.type === 'training' ? 'Eğitimi Sil' : 'Oturumu Sil'}
        message={`"${(itemToDelete?.item as Training | Session)?.title}" silinecek. Emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default TrainingPage;


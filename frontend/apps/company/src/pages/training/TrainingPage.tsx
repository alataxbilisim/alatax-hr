import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { trainingApi, usersApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, EmptyState, Modal } from '../../components/ui';
import TrainingForm from '../../components/training/TrainingForm';
import SessionForm from '../../components/training/SessionForm';
import { useLocation, useNavigate } from 'react-router-dom';
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
  type: string;
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
  status: string;
}

interface ParticipantRow {
  id: number;
  user_id: number;
  status: string;
  score?: number | null;
  passed?: boolean | null;
  user?: { id: number; name: string; email?: string };
}

interface User {
  id: number;
  name: string;
  email: string;
}

type TabType = 'trainings' | 'sessions';

const statusBadgeClass: Record<string, string> = {
  scheduled: 'badge-info',
  in_progress: 'badge-warning',
  completed: 'badge-success',
  cancelled: 'badge-danger',
};

const TrainingPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const location = useLocation();

  const activeTab: TabType = useMemo(() => {
    if (location.pathname.includes('/training/sessions')) return 'sessions';
    return 'trainings';
  }, [location.pathname]);

  const handleTabChange = (tab: TabType) => {
    navigate(tab === 'sessions' ? '/training/sessions' : '/training');
  };

  const [trainings, setTrainings] = useState<Training[]>([]);
  const [trainingsLoading, setTrainingsLoading] = useState(true);
  const [trainingPage, setTrainingPage] = useState(1);
  const [trainingTotalPages, setTrainingTotalPages] = useState(1);
  const [trainingFormOpen, setTrainingFormOpen] = useState(false);
  const [selectedTraining, setSelectedTraining] = useState<Training | null>(null);

  const [sessions, setSessions] = useState<Session[]>([]);
  const [sessionsLoading, setSessionsLoading] = useState(true);
  const [sessionPage, setSessionPage] = useState(1);
  const [sessionTotalPages, setSessionTotalPages] = useState(1);
  const [sessionFormOpen, setSessionFormOpen] = useState(false);
  const [selectedSession, setSelectedSession] = useState<Session | null>(null);

  const [participantsModalOpen, setParticipantsModalOpen] = useState(false);
  const [currentSession, setCurrentSession] = useState<Session | null>(null);
  const [participants, setParticipants] = useState<ParticipantRow[]>([]);
  const [participantsLoading, setParticipantsLoading] = useState(false);
  const [attendanceSaving, setAttendanceSaving] = useState(false);
  const [addParticipantOpen, setAddParticipantOpen] = useState(false);
  const [users, setUsers] = useState<User[]>([]);
  const [selectedUserId, setSelectedUserId] = useState('');

  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [itemToDelete, setItemToDelete] = useState<{ type: 'training' | 'session'; item: Training | Session } | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const [typeOptions, setTypeOptions] = useState<LookupItem[]>([]);
  const [categoryOptions, setCategoryOptions] = useState<LookupItem[]>([]);
  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [typeFilter, setTypeFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const loadLookups = useCallback(async () => {
    try {
      const [typeRes, categoryRes, statusRes] = await Promise.all([
        lookupsApi.forType('training_type'),
        lookupsApi.forType('training_category'),
        lookupsApi.forType('training_session_status'),
      ]);
      setTypeOptions(typeRes.data.data ?? []);
      setCategoryOptions(categoryRes.data.data ?? []);
      setStatusOptions(statusRes.data.data ?? []);
    } catch {
      console.error('Eğitim lookup listeleri yüklenemedi');
    }
  }, []);

  const typeLabel = (value: string) =>
    typeOptions.find((o) => o.value === value)?.label || value;

  const categoryLabel = (value?: string) =>
    value ? (categoryOptions.find((o) => o.value === value)?.label || value) : '-';

  const statusLabel = (value: string) =>
    statusOptions.find((o) => o.value === value)?.label || value;

  const loadTrainings = useCallback(async () => {
    try {
      setTrainingsLoading(true);
      const params: Record<string, string | number> = { page: trainingPage };
      if (typeFilter) params.type = typeFilter;
      if (categoryFilter) params.category = categoryFilter;
      const response = await trainingApi.trainings.list(params);
      const data = response.data.data;
      setTrainings(data.data || []);
      setTrainingTotalPages(data.last_page || 1);
    } catch {
      toast.error('Eğitimler yüklenemedi');
    } finally {
      setTrainingsLoading(false);
    }
  }, [trainingPage, typeFilter, categoryFilter]);

  const loadSessions = useCallback(async () => {
    try {
      setSessionsLoading(true);
      const params: Record<string, string | number> = { page: sessionPage };
      if (statusFilter) params.status = statusFilter;
      const response = await trainingApi.sessions.list(params);
      const data = response.data.data;
      setSessions(data.data || []);
      setSessionTotalPages(data.last_page || 1);
    } catch {
      toast.error('Oturumlar yüklenemedi');
    } finally {
      setSessionsLoading(false);
    }
  }, [sessionPage, statusFilter]);

  const loadUsers = useCallback(async () => {
    try {
      const response = await usersApi.list({ per_page: 100 });
      setUsers(response.data.data?.data || response.data.data || []);
    } catch {
      toast.error('Kullanıcılar yüklenemedi');
    }
  }, []);

  useEffect(() => {
    void loadLookups();
  }, [loadLookups]);

  useEffect(() => {
    if (activeTab === 'trainings') {
      loadTrainings();
    } else {
      loadSessions();
      // Oturum formu için eğitim listesi gerekli
      if (trainings.length === 0) {
        void trainingApi.trainings.list({ per_page: 100 }).then((response) => {
          const data = response.data.data;
          setTrainings(data.data || data || []);
        }).catch(() => undefined);
      }
    }
  }, [activeTab, loadTrainings, loadSessions, trainings.length]);

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
        await trainingApi.trainings.delete(itemToDelete.item.id);
        toast.success('Eğitim silindi');
        loadTrainings();
      } else {
        await trainingApi.sessions.delete(itemToDelete.item.id);
        toast.success('Oturum silindi');
        loadSessions();
      }
      setDeleteDialogOpen(false);
      setItemToDelete(null);
    } catch {
      toast.error('Silme başarısız');
    } finally {
      setDeleteLoading(false);
    }
  };

  const openParticipants = async (session: Session) => {
    setCurrentSession(session);
    setParticipantsModalOpen(true);
    setParticipantsLoading(true);
    setParticipants([]);
    try {
      const res = await trainingApi.sessions.get(session.id);
      const payload = res.data.data;
      const list: ParticipantRow[] = Array.isArray(payload?.participants)
        ? payload.participants
        : [];
      setParticipants(
        list.map((row) => ({
          id: row.id,
          user_id: row.user_id,
          status: row.status || 'registered',
          score: row.score ?? null,
          passed: row.passed ?? null,
          user: row.user,
        })),
      );
    } catch {
      toast.error(t('trainingAttendance.loadError'));
    } finally {
      setParticipantsLoading(false);
    }
  };

  const setParticipantStatus = (participantId: number, status: string) => {
    setParticipants((prev) =>
      prev.map((p) => (p.id === participantId ? { ...p, status } : p)),
    );
  };

  const handleSaveAttendance = async () => {
    if (!currentSession) return;
    setAttendanceSaving(true);
    try {
      await trainingApi.sessions.updateAttendance(currentSession.id, {
        attendances: participants.map((p) => ({
          user_id: p.user_id,
          status: p.status,
          score: p.score ?? null,
          passed: p.passed ?? null,
        })),
      });
      toast.success(t('trainingAttendance.saveSuccess'));
      void loadSessions();
    } catch {
      toast.error(t('trainingAttendance.saveError'));
    } finally {
      setAttendanceSaving(false);
    }
  };

  const handleAddParticipant = async () => {
    if (!currentSession || !selectedUserId) return;
    try {
      await trainingApi.sessions.addParticipant(currentSession.id, Number(selectedUserId));
      toast.success(t('trainingAttendance.addSuccess'));
      setAddParticipantOpen(false);
      setSelectedUserId('');
      await openParticipants(currentSession);
      void loadSessions();
    } catch {
      toast.error(t('trainingAttendance.addError'));
    }
  };

  const trainingColumns = [
    {
      key: 'title',
      title: 'Eğitim',
      render: (t: Training) => (
        <div>
          <div style={{ fontWeight: 500 }}>{t.title}</div>
          {t.is_mandatory && <span className="badge badge-warning" style={{ fontSize: '0.65rem' }}>Zorunlu</span>}
        </div>
      ),
    },
    {
      key: 'category',
      title: 'Kategori',
      render: (t: Training) => categoryLabel(t.category),
    },
    {
      key: 'type',
      title: 'Tür',
      render: (t: Training) => typeLabel(t.type),
    },
    {
      key: 'duration_hours',
      title: 'Süre',
      render: (t: Training) => (t.duration_hours ? `${t.duration_hours} sa` : '-'),
    },
    {
      key: 'sessions_count',
      title: 'Oturum',
      render: (t: Training) => t.sessions_count ?? 0,
    },
    {
      key: 'status',
      title: 'Durum',
      render: (t: Training) => (
        <span className={`badge ${t.is_active ? 'badge-success' : 'badge-secondary'}`}>
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
            {s.training?.title || '-'}
          </div>
        </div>
      ),
    },
    {
      key: 'start_date',
      title: 'Başlangıç',
      render: (s: Session) => new Date(s.start_date).toLocaleString('tr-TR'),
    },
    {
      key: 'location',
      title: 'Konum',
      render: (s: Session) => s.location || '-',
    },
    {
      key: 'participants_count',
      title: 'Katılımcı',
      render: (s: Session) => s.participants_count ?? 0,
    },
    {
      key: 'status',
      title: 'Durum',
      render: (s: Session) => (
        <span className={`badge ${statusBadgeClass[s.status] || 'badge-secondary'}`}>
          {statusLabel(s.status)}
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
            onClick={() => {
              void openParticipants(s);
              void loadUsers();
            }}
            title={t('trainingAttendance.title')}
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
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Eğitimler</h1>
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

      <div className="tabs" style={{ marginBottom: '1.5rem' }}>
        <button
          className={`tab ${activeTab === 'trainings' ? 'active' : ''}`}
          onClick={() => handleTabChange('trainings')}
        >
          <BsMortarboard size={16} />
          Eğitimler
        </button>
        <button
          className={`tab ${activeTab === 'sessions' ? 'active' : ''}`}
          onClick={() => handleTabChange('sessions')}
        >
          <BsCalendarEvent size={16} />
          Oturumlar
        </button>
      </div>

      {activeTab === 'trainings' ? (
        <>
          <div className="list-filter-bar" style={{ marginBottom: '1rem', display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
            <div style={{ minWidth: 160 }}>
              <Select
                value={typeFilter}
                onChange={(v) => { setTypeFilter(v); setTrainingPage(1); }}
                options={typeOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                placeholder="Tür"
                allowEmpty
                emptyLabel="Tüm türler"
                clearable
                aria-label="Tür filtresi"
              />
            </div>
            <div style={{ minWidth: 160 }}>
              <Select
                value={categoryFilter}
                onChange={(v) => { setCategoryFilter(v); setTrainingPage(1); }}
                options={categoryOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                placeholder="Kategori"
                allowEmpty
                emptyLabel="Tüm kategoriler"
                clearable
                aria-label="Kategori filtresi"
              />
            </div>
          </div>
          {trainingsLoading ? (
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
          )}
        </>
      ) : (
        <>
          <div className="list-filter-bar" style={{ marginBottom: '1rem', display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
            <div style={{ minWidth: 180 }}>
              <Select
                value={statusFilter}
                onChange={(v) => { setStatusFilter(v); setSessionPage(1); }}
                options={statusOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                placeholder="Durum"
                allowEmpty
                emptyLabel="Tüm durumlar"
                clearable
                aria-label="Durum filtresi"
              />
            </div>
          </div>
          {sessionsLoading ? (
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
        </>
      )}

      <TrainingForm
        isOpen={trainingFormOpen}
        onClose={() => { setTrainingFormOpen(false); setSelectedTraining(null); }}
        onSubmit={handleTrainingSubmit}
        training={selectedTraining}
      />

      <SessionForm
        isOpen={sessionFormOpen}
        onClose={() => { setSessionFormOpen(false); setSelectedSession(null); }}
        onSubmit={handleSessionSubmit}
        session={selectedSession}
        trainings={trainings}
      />

      <Modal
        isOpen={participantsModalOpen}
        onClose={() => setParticipantsModalOpen(false)}
        title={`${t('trainingAttendance.title')}: ${currentSession?.training?.title || currentSession?.title || ''}`}
        size="lg"
      >
        <div className="space-y-4">
          <div className="flex justify-between items-center gap-2" style={{ marginBottom: '1rem' }}>
            <p className="text-sm text-secondary">
              {participantsLoading
                ? t('trainingAttendance.loading')
                : t('trainingAttendance.count', { count: participants.length })}
            </p>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <button type="button" className="btn btn-secondary btn-sm" onClick={() => setAddParticipantOpen(true)}>
                <BsPersonPlus size={16} />
                {t('trainingAttendance.add')}
              </button>
              <button
                type="button"
                className="btn btn-primary btn-sm"
                onClick={() => void handleSaveAttendance()}
                disabled={attendanceSaving || participantsLoading || participants.length === 0}
              >
                {attendanceSaving ? t('trainingAttendance.saving') : t('trainingAttendance.save')}
              </button>
            </div>
          </div>
          {participantsLoading ? (
            <p style={{ color: 'var(--text-secondary)', textAlign: 'center', padding: '2rem' }}>
              {t('trainingAttendance.loading')}
            </p>
          ) : participants.length === 0 ? (
            <EmptyState
              icon={<BsPeople className="w-12 h-12" />}
              title={t('trainingAttendance.empty')}
              description={t('trainingAttendance.emptyHint')}
            />
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table className="w-full text-sm" style={{ width: '100%' }}>
                <thead>
                  <tr style={{ borderBottom: '1px solid var(--border-default)', textAlign: 'left' }}>
                    <th style={{ padding: '0.5rem 0.75rem' }}>{t('trainingAttendance.colName')}</th>
                    <th style={{ padding: '0.5rem 0.75rem' }}>{t('trainingAttendance.colStatus')}</th>
                  </tr>
                </thead>
                <tbody>
                  {participants.map((p) => (
                    <tr key={p.id} style={{ borderBottom: '1px solid var(--border-default)' }}>
                      <td style={{ padding: '0.5rem 0.75rem' }}>
                        <div style={{ fontWeight: 500 }}>{p.user?.name ?? `#${p.user_id}`}</div>
                        {p.user?.email ? (
                          <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>{p.user.email}</div>
                        ) : null}
                      </td>
                      <td style={{ padding: '0.5rem 0.75rem', minWidth: '10rem' }}>
                        <Select
                          value={p.status}
                          onChange={(v) => setParticipantStatus(p.id, v || 'registered')}
                          options={[
                            { value: 'registered', label: t('trainingAttendance.status.registered') },
                            { value: 'attended', label: t('trainingAttendance.status.attended') },
                            { value: 'absent', label: t('trainingAttendance.status.absent') },
                            { value: 'excused', label: t('trainingAttendance.status.excused') },
                          ]}
                          aria-label={t('trainingAttendance.colStatus')}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </Modal>

      <Modal
        isOpen={addParticipantOpen}
        onClose={() => setAddParticipantOpen(false)}
        title="Katılımcı Ekle"
        size="sm"
      >
        <div className="form-group">
          <label className="form-label">Kullanıcı</label>
          <Select
            value={selectedUserId}
            onChange={setSelectedUserId}
            options={users.map((u) => ({
              value: String(u.id),
              label: `${u.name} (${u.email})`,
            }))}
            placeholder="Seçin..."
            aria-label="Kullanıcı"
          />
        </div>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
          <button className="btn btn-ghost" onClick={() => setAddParticipantOpen(false)}>İptal</button>
          <button className="btn btn-primary" onClick={handleAddParticipant} disabled={!selectedUserId}>
            Ekle
          </button>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={handleDelete}
        title={itemToDelete?.type === 'training' ? 'Eğitimi Sil' : 'Oturumu Sil'}
        message="Bu kaydı silmek istediğinize emin misiniz?"
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default TrainingPage;

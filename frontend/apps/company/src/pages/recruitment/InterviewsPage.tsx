import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { recruitmentApi, usersApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, Modal, ConfirmDialog } from '../../components/ui';
import {
  BsArrowLeft,
  BsCalendar,
  BsPlus,
  BsPencil,
  BsTrash,
  BsCheckCircle,
  BsXCircle,
  BsClock,
  BsPersonVideo,
  BsTelephone,
  BsGeoAlt,
  BsLink,
  BsStarFill,
  BsStar,
} from 'react-icons/bs';

interface Interview {
  id: number;
  title: string;
  type: string;
  type_label: string;
  scheduled_at: string;
  scheduled_at_formatted: string;
  duration_minutes: number;
  location: string | null;
  meeting_link: string | null;
  status: string;
  overall_rating: number | null;
  recommendation: string | null;
  recommendation_label: string | null;
  notes: string | null;
  feedback: string | null;
  application: {
    id: number;
    applicant_name: string;
    email: string;
    phone: string | null;
    position: { id: number; title: string } | null;
  } | null;
  interviewer: { id: number; name: string } | null;
  created_at: string;
}

interface User {
  id: number;
  name: string;
  email: string;
}

interface Application {
  id: number;
  applicant_name: string;
  email: string;
  job_position: { id: number; title: string };
}

const statusConfig: Record<string, { label: string; class: string; icon: React.ReactNode }> = {
  scheduled: { label: 'Planlandı', class: 'badge-info', icon: <BsClock /> },
  completed: { label: 'Tamamlandı', class: 'badge-success', icon: <BsCheckCircle /> },
  cancelled: { label: 'İptal', class: 'badge-danger', icon: <BsXCircle /> },
  no_show: { label: 'Gelmedi', class: 'badge-warning', icon: <BsXCircle /> },
  rescheduled: { label: 'Ertelendi', class: 'badge-secondary', icon: <BsClock /> },
};

const typeIcons: Record<string, React.ReactNode> = {
  phone: <BsTelephone />,
  video: <BsPersonVideo />,
  onsite: <BsGeoAlt />,
  technical: <BsPersonVideo />,
  hr: <BsPersonVideo />,
  panel: <BsPersonVideo />,
};

const InterviewsPage: React.FC = () => {
  const navigate = useNavigate();
  const [interviews, setInterviews] = useState<Interview[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Form modal
  const [formOpen, setFormOpen] = useState(false);
  const [selectedInterview, setSelectedInterview] = useState<Interview | null>(null);
  const [saving, setSaving] = useState(false);

  // Complete modal
  const [completeOpen, setCompleteOpen] = useState(false);
  const [interviewToComplete, setInterviewToComplete] = useState<Interview | null>(null);

  // Delete dialog
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [interviewToDelete, setInterviewToDelete] = useState<Interview | null>(null);
  const [deleting, setDeleting] = useState(false);

  // Form data
  const [applications, setApplications] = useState<Application[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [interviewTypes, setInterviewTypes] = useState<Record<string, string>>({});
  const [formData, setFormData] = useState({
    job_application_id: '',
    title: '',
    type: 'onsite',
    scheduled_at: '',
    duration_minutes: 60,
    location: '',
    meeting_link: '',
    notes: '',
    interviewer_id: '',
  });

  // Complete form data
  const [completeData, setCompleteData] = useState({
    overall_rating: 3,
    recommendation: 'no_decision',
    feedback: '',
  });

  const loadInterviews = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { page, per_page: 15 };
      if (search) params.search = search;
      if (statusFilter) params.status = statusFilter;

      const response = await recruitmentApi.interviews.list(params);
      const data = response.data.data;

      if (Array.isArray(data)) {
        setInterviews(data);
        setTotalPages(1);
        setTotal(data.length);
      } else if (data?.data) {
        setInterviews(data.data);
        setTotalPages(data.meta?.last_page || data.last_page || 1);
        setTotal(data.meta?.total || data.total || 0);
      }
    } catch {
      toast.error('Mülakatlar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page, search, statusFilter]);

  const loadFormData = useCallback(async () => {
    try {
      const [appsRes, usersRes, typesRes] = await Promise.all([
        recruitmentApi.applications.list({ per_page: 100, status: 'reviewing' }),
        usersApi.list({ per_page: 100 }),
        recruitmentApi.interviews.getTypes(),
      ]);

      setApplications(appsRes.data.data?.data || appsRes.data.data || []);
      setUsers(usersRes.data.data?.data || usersRes.data.data || []);
      setInterviewTypes(typesRes.data.data || {});
    } catch {
      console.error('Form verileri yüklenemedi');
    }
  }, []);

  useEffect(() => {
    loadInterviews();
  }, [loadInterviews]);

  useEffect(() => {
    if (formOpen) {
      loadFormData();
    }
  }, [formOpen, loadFormData]);

  

  

  const handleOpenForm = (interview?: Interview) => {
    if (interview) {
      setSelectedInterview(interview);
      setFormData({
        job_application_id: String(interview.application?.id || ''),
        title: interview.title,
        type: interview.type,
        scheduled_at: interview.scheduled_at?.slice(0, 16) || '',
        duration_minutes: interview.duration_minutes,
        location: interview.location || '',
        meeting_link: interview.meeting_link || '',
        notes: interview.notes || '',
        interviewer_id: String(interview.interviewer?.id || ''),
      });
    } else {
      setSelectedInterview(null);
      setFormData({
        job_application_id: '',
        title: '',
        type: 'onsite',
        scheduled_at: '',
        duration_minutes: 60,
        location: '',
        meeting_link: '',
        notes: '',
        interviewer_id: '',
      });
    }
    setFormOpen(true);
  };

  const handleSave = async () => {
    if (!formData.job_application_id || !formData.title || !formData.scheduled_at || !formData.interviewer_id) {
      toast.error('Lütfen zorunlu alanları doldurun');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        ...formData,
        job_application_id: Number(formData.job_application_id),
        interviewer_id: Number(formData.interviewer_id),
      };

      if (selectedInterview) {
        await recruitmentApi.interviews.update(selectedInterview.id, payload);
        toast.success('Mülakat güncellendi');
      } else {
        await recruitmentApi.interviews.create(payload);
        toast.success('Mülakat oluşturuldu');
      }
      setFormOpen(false);
      loadInterviews();
    } catch {
      toast.error('İşlem başarısız');
    } finally {
      setSaving(false);
    }
  };

  const handleComplete = async () => {
    if (!interviewToComplete) return;

    setSaving(true);
    try {
      await recruitmentApi.interviews.complete(interviewToComplete.id, completeData);
      toast.success('Mülakat tamamlandı');
      setCompleteOpen(false);
      setInterviewToComplete(null);
      loadInterviews();
    } catch {
      toast.error('İşlem başarısız');
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = async (interview: Interview) => {
    try {
      await recruitmentApi.interviews.cancel(interview.id, {});
      toast.success('Mülakat iptal edildi');
      loadInterviews();
    } catch {
      toast.error('İptal işlemi başarısız');
    }
  };

  const handleDelete = async () => {
    if (!interviewToDelete) return;

    setDeleting(true);
    try {
      await recruitmentApi.interviews.delete(interviewToDelete.id);
      toast.success('Mülakat silindi');
      setDeleteOpen(false);
      setInterviewToDelete(null);
      loadInterviews();
    } catch {
      toast.error('Silme işlemi başarısız');
    } finally {
      setDeleting(false);
    }
  };

  const openCompleteModal = (interview: Interview) => {
    setInterviewToComplete(interview);
    setCompleteData({
      overall_rating: 3,
      recommendation: 'no_decision',
      feedback: '',
    });
    setCompleteOpen(true);
  };

  const columns = [
    {
      key: 'info',
      title: 'Mülakat',
      render: (i: Interview) => (
        <div>
          <div style={{ fontWeight: 500, color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            {typeIcons[i.type]} {i.title}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {i.type_label}
          </div>
        </div>
      ),
    },
    {
      key: 'applicant',
      title: 'Aday',
      render: (i: Interview) => i.application ? (
        <div>
          <div style={{ fontWeight: 500 }}>{i.application.applicant_name}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {i.application.position?.title || '-'}
          </div>
        </div>
      ) : '-',
    },
    {
      key: 'scheduled',
      title: 'Tarih/Saat',
      width: '150px',
      render: (i: Interview) => (
        <div>
          <div style={{ fontWeight: 500 }}>{i.scheduled_at_formatted}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {i.duration_minutes} dk
          </div>
        </div>
      ),
    },
    {
      key: 'interviewer',
      title: 'Görüşmeci',
      width: '150px',
      render: (i: Interview) => i.interviewer?.name || '-',
    },
    {
      key: 'location',
      title: 'Konum',
      width: '150px',
      render: (i: Interview) => (
        <div>
          {i.location && <div style={{ fontSize: '0.875rem' }}><BsGeoAlt size={10} /> {i.location}</div>}
          {i.meeting_link && (
            <a href={i.meeting_link} target="_blank" rel="noopener noreferrer" style={{ fontSize: '0.75rem', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
              <BsLink size={10} /> Link
            </a>
          )}
          {!i.location && !i.meeting_link && '-'}
        </div>
      ),
    },
    {
      key: 'status',
      title: 'Durum',
      width: '120px',
      render: (i: Interview) => {
        const s = statusConfig[i.status] || { label: i.status, class: 'badge-secondary', icon: null };
        return (
          <span className={`badge ${s.class}`} style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
            {s.icon} {s.label}
          </span>
        );
      },
    },
    {
      key: 'rating',
      title: 'Değerlendirme',
      width: '120px',
      render: (i: Interview) => i.overall_rating ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.125rem' }}>
          {[1, 2, 3, 4, 5].map((star) => (
            <span key={star} style={{ color: star <= i.overall_rating! ? '#f59e0b' : 'var(--text-muted)' }}>
              {star <= i.overall_rating! ? <BsStarFill size={12} /> : <BsStar size={12} />}
            </span>
          ))}
        </div>
      ) : '-',
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '150px',
      align: 'right' as const,
      render: (i: Interview) => (
        <div style={{ display: 'flex', gap: '0.25rem', justifyContent: 'flex-end' }}>
          {i.status === 'scheduled' && (
            <>
              <button
                className="btn btn-success btn-icon btn-sm"
                onClick={() => openCompleteModal(i)}
                title="Tamamla"
              >
                <BsCheckCircle />
              </button>
              <button
                className="btn btn-warning btn-icon btn-sm"
                onClick={() => handleCancel(i)}
                title="İptal Et"
              >
                <BsXCircle />
              </button>
            </>
          )}
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleOpenForm(i)}
            title="Düzenle"
          >
            <BsPencil />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setInterviewToDelete(i);
              setDeleteOpen(true);
            }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  const recommendationOptions = [
    { value: 'strong_hire', label: 'Kesinlikle İşe Alınmalı' },
    { value: 'hire', label: 'İşe Alınmalı' },
    { value: 'no_decision', label: 'Kararsız' },
    { value: 'no_hire', label: 'İşe Alınmamalı' },
    { value: 'strong_no_hire', label: 'Kesinlikle İşe Alınmamalı' },
  ];

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/recruitment/positions')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Geri
          </button>
          <h1>Mülakatlar</h1>
          <p>Mülakat planlaması ve değerlendirmeleri</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => handleOpenForm()}>
            <BsPlus size={18} /> Yeni Mülakat
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="card" style={{ marginBottom: '1rem' }}>
        <div className="card-body filter-bar">
          <div className="form-group" style={{ marginBottom: 0, minWidth: '150px', flex: '0 1 auto' }}>
            <select
              className="form-control"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">Tüm Durumlar</option>
              {Object.entries(statusConfig).map(([key, { label }]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={interviews}
        loading={loading}
        emptyMessage="Mülakat bulunamadı"
        emptyIcon={<BsCalendar size={32} />}
        currentPage={page}
        totalPages={totalPages}
        total={total}
        onPageChange={setPage}
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        searchPlaceholder="Mülakat ara..."
      />

      {/* Form Modal */}
      <Modal
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        title={selectedInterview ? 'Mülakat Düzenle' : 'Yeni Mülakat'}
        size="lg"
        footer={
          <>
            <button className="btn btn-secondary" onClick={() => setFormOpen(false)}>İptal</button>
            <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
              {saving ? 'Kaydediliyor...' : selectedInterview ? 'Güncelle' : 'Oluştur'}
            </button>
          </>
        }
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {!selectedInterview && (
            <div className="form-group">
              <label className="form-label">Aday *</label>
              <select
                className="form-control"
                value={formData.job_application_id}
                onChange={(e) => setFormData({ ...formData, job_application_id: e.target.value })}
              >
                <option value="">Seçin...</option>
                {applications.map((app) => (
                  <option key={app.id} value={app.id}>
                    {app.applicant_name} - {app.job_position?.title}
                  </option>
                ))}
              </select>
            </div>
          )}

          <div className="form-grid form-grid-2">
            <div className="form-group">
              <label className="form-label">Mülakat Başlığı *</label>
              <input
                type="text"
                className="form-control"
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                placeholder="Örn: Teknik Mülakat"
              />
            </div>
            <div className="form-group">
              <label className="form-label">Mülakat Tipi *</label>
              <select
                className="form-control"
                value={formData.type}
                onChange={(e) => setFormData({ ...formData, type: e.target.value })}
              >
                {Object.entries(interviewTypes).map(([key, label]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="form-grid form-grid-2">
            <div className="form-group">
              <label className="form-label">Tarih ve Saat *</label>
              <input
                type="datetime-local"
                className="form-control"
                value={formData.scheduled_at}
                onChange={(e) => setFormData({ ...formData, scheduled_at: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label className="form-label">Süre (dk)</label>
              <input
                type="number"
                className="form-control"
                value={formData.duration_minutes}
                onChange={(e) => setFormData({ ...formData, duration_minutes: Number(e.target.value) })}
                min={15}
                max={480}
              />
            </div>
          </div>

          <div className="form-group">
            <label className="form-label">Görüşmeci *</label>
            <select
              className="form-control"
              value={formData.interviewer_id}
              onChange={(e) => setFormData({ ...formData, interviewer_id: e.target.value })}
            >
              <option value="">Seçin...</option>
              {users.map((user) => (
                <option key={user.id} value={user.id}>{user.name}</option>
              ))}
            </select>
          </div>

          <div className="form-grid form-grid-2">
            <div className="form-group">
              <label className="form-label">Konum</label>
              <input
                type="text"
                className="form-control"
                value={formData.location}
                onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                placeholder="Örn: Toplantı Odası 1"
              />
            </div>
            <div className="form-group">
              <label className="form-label">Toplantı Linki</label>
              <input
                type="url"
                className="form-control"
                value={formData.meeting_link}
                onChange={(e) => setFormData({ ...formData, meeting_link: e.target.value })}
                placeholder="https://..."
              />
            </div>
          </div>

          <div className="form-group">
            <label className="form-label">Notlar</label>
            <textarea
              className="form-control"
              value={formData.notes}
              onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
              rows={3}
              placeholder="Mülakat için notlar..."
            />
          </div>
        </div>
      </Modal>

      {/* Complete Modal */}
      <Modal
        isOpen={completeOpen}
        onClose={() => setCompleteOpen(false)}
        title="Mülakatı Tamamla"
        size="md"
        footer={
          <>
            <button className="btn btn-secondary" onClick={() => setCompleteOpen(false)}>İptal</button>
            <button className="btn btn-success" onClick={handleComplete} disabled={saving}>
              {saving ? 'Kaydediliyor...' : 'Tamamla'}
            </button>
          </>
        }
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Genel Değerlendirme *</label>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  type="button"
                  onClick={() => setCompleteData({ ...completeData, overall_rating: star })}
                  style={{
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    padding: '0.25rem',
                    color: star <= completeData.overall_rating ? '#f59e0b' : 'var(--text-muted)',
                  }}
                >
                  {star <= completeData.overall_rating ? <BsStarFill size={24} /> : <BsStar size={24} />}
                </button>
              ))}
            </div>
          </div>

          <div className="form-group">
            <label className="form-label">Öneri *</label>
            <select
              className="form-control"
              value={completeData.recommendation}
              onChange={(e) => setCompleteData({ ...completeData, recommendation: e.target.value })}
            >
              {recommendationOptions.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
          </div>

          <div className="form-group">
            <label className="form-label">Geri Bildirim</label>
            <textarea
              className="form-control"
              value={completeData.feedback}
              onChange={(e) => setCompleteData({ ...completeData, feedback: e.target.value })}
              rows={4}
              placeholder="Adayla ilgili geri bildiriminizi yazın..."
            />
          </div>
        </div>
      </Modal>

      {/* Delete Dialog */}
      <ConfirmDialog
        isOpen={deleteOpen}
        onClose={() => setDeleteOpen(false)}
        onConfirm={handleDelete}
        title="Mülakatı Sil"
        message={`"${interviewToDelete?.title}" mülakatını silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleting}
      />
    </div>
  );
};

export default InterviewsPage;


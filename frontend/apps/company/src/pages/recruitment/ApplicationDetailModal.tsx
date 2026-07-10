import React, { useEffect, useState, useCallback, useRef } from 'react';
import { recruitmentApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { Modal } from '../../components/ui';
import {
  BsEnvelope,
  BsTelephone,
  BsDownload,
  BsStarFill,
  BsStar,
  BsClockHistory,
  BsPerson,
  BsCalendar,
  BsChatDots,
} from 'react-icons/bs';

interface ApplicationDetail {
  id: number;
  applicant_name: string;
  applicant_email: string;
  applicant_phone: string | null;
  position: { id: number; title: string } | null;
  status: string;
  form_data: Record<string, unknown>;
  cv_path: string | null;
  notes: string | null;
  rating: number | null;
  status_logs: Array<{
    id: number;
    status: string;
    notes: string | null;
    user: string | null;
    created_at: string;
  }>;
  created_at: string;
}

interface ApplicationDetailModalProps {
  isOpen: boolean;
  onClose: () => void;
  applicationId: number | null;
  onUpdate: () => void;
}

const statusLabels: Record<string, { label: string; color: string }> = {
  new: { label: 'Yeni', color: '#94a3b8' },
  reviewing: { label: 'İnceleniyor', color: '#f59e0b' },
  shortlisted: { label: 'Ön Seçim', color: '#8b5cf6' },
  interview_scheduled: { label: 'Mülakat Planlandı', color: '#3b82f6' },
  interviewed: { label: 'Mülakat Yapıldı', color: '#0ea5e9' },
  offer_sent: { label: 'Teklif Gönderildi', color: '#6366f1' },
  hired: { label: 'İşe Alındı', color: '#10b981' },
  rejected: { label: 'Reddedildi', color: '#ef4444' },
  withdrawn: { label: 'Çekildi', color: '#6b7280' },
};

const statusOptions = Object.entries(statusLabels).map(([key, { label }]) => ({
  value: key,
  label,
}));

const ApplicationDetailModal: React.FC<ApplicationDetailModalProps> = ({
  isOpen,
  onClose,
  applicationId,
  onUpdate,
}) => {
  const [loading, setLoading] = useState(true);
  const [application, setApplication] = useState<ApplicationDetail | null>(null);
  const [activeTab, setActiveTab] = useState<'info' | 'notes' | 'history'>('info');
  const [notes, setNotes] = useState('');
  const [savingNotes, setSavingNotes] = useState(false);
  const [newStatus, setNewStatus] = useState('');
  const [statusNote, setStatusNote] = useState('');
  const [changingStatus, setChangingStatus] = useState(false);
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  const loadApplication = useCallback(async () => {
    if (!applicationId) return;

    setLoading(true);
    try {
      const response = await recruitmentApi.applications.get(applicationId);
      const data = response.data.data;
      setApplication(data);
      setNotes(data.notes || '');
      setNewStatus(data.status);
    } catch {
      toast.error('Başvuru detayları yüklenemedi');
      onCloseRef.current();
    } finally {
      setLoading(false);
    }
  }, [applicationId]);

  useEffect(() => {
    if (isOpen && applicationId) {
      loadApplication();
    }
  }, [isOpen, applicationId, loadApplication]);

  

  const handleSaveNotes = async () => {
    if (!applicationId) return;

    setSavingNotes(true);
    try {
      await recruitmentApi.applications.updateNotes(applicationId, notes);
      toast.success('Notlar kaydedildi');
      onUpdate();
    } catch {
      toast.error('Notlar kaydedilemedi');
    } finally {
      setSavingNotes(false);
    }
  };

  const handleStatusChange = async () => {
    if (!applicationId || !newStatus) return;

    setChangingStatus(true);
    try {
      await recruitmentApi.applications.updateStatus(applicationId, {
        status: newStatus,
        note: statusNote,
      });
      toast.success('Durum güncellendi');
      setStatusNote('');
      loadApplication();
      onUpdate();
    } catch {
      toast.error('Durum güncellenemedi');
    } finally {
      setChangingStatus(false);
    }
  };

  const handleRate = async (rating: number) => {
    if (!applicationId) return;

    try {
      await recruitmentApi.applications.rate(applicationId, rating);
      toast.success('Puan kaydedildi');
      loadApplication();
      onUpdate();
    } catch {
      toast.error('Puanlama başarısız');
    }
  };

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Başvuru Detayları"
      size="lg"
    >
      {loading ? (
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <div className="loading-spinner" />
        </div>
      ) : application ? (
        <div>
          {/* Header */}
          <div style={{ 
            display: 'flex', 
            flexDirection: 'column',
            gap: '1rem', 
            marginBottom: '1.5rem',
            padding: '1rem',
            background: 'var(--surface-secondary)',
            borderRadius: 'var(--radius-lg)',
          }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '1rem', flexWrap: 'wrap' }}>
              <div style={{
                width: 56,
                height: 56,
                borderRadius: 'var(--radius-lg)',
                background: 'var(--gradient-primary)',
                color: 'white',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '1.25rem',
                fontWeight: 600,
                flexShrink: 0,
              }}>
                {application.applicant_name?.charAt(0) || 'A'}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <h3 style={{ margin: 0, color: 'var(--text-primary)', fontSize: '1.125rem' }}>{application.applicant_name}</h3>
                <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginTop: '0.25rem' }}>
                  {application.position?.title || 'Pozisyon belirtilmemiş'}
                </div>
              </div>
              <div 
                className="badge" 
                style={{ 
                  background: statusLabels[application.status]?.color || '#94a3b8',
                  color: 'white',
                  flexShrink: 0,
                }}
              >
                {statusLabels[application.status]?.label || application.status}
              </div>
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem', fontSize: '0.8125rem', color: 'var(--text-tertiary)' }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                <BsEnvelope size={12} /> {application.applicant_email}
              </span>
              {application.applicant_phone && (
                <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                  <BsTelephone size={12} /> {application.applicant_phone}
                </span>
              )}
              {application.cv_path && (
                <a
                  href={application.cv_path}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn btn-secondary btn-sm"
                  style={{ marginLeft: 'auto' }}
                >
                  <BsDownload size={14} /> CV İndir
                </a>
              )}
            </div>
          </div>

          {/* Rating */}
          <div style={{ marginBottom: '1.5rem' }}>
            <label style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginBottom: '0.5rem', display: 'block' }}>
              Değerlendirme
            </label>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  onClick={() => handleRate(star)}
                  style={{
                    background: 'none',
                    border: 'none',
                    padding: '0.25rem',
                    cursor: 'pointer',
                    color: star <= (application.rating || 0) ? '#f59e0b' : 'var(--text-muted)',
                  }}
                >
                  {star <= (application.rating || 0) ? <BsStarFill size={20} /> : <BsStar size={20} />}
                </button>
              ))}
              <span style={{ marginLeft: '0.5rem', fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                {application.rating ? `${application.rating}/5` : 'Puanlanmamış'}
              </span>
            </div>
          </div>

          {/* Tabs */}
          <div className="tabs" style={{ marginBottom: '1rem' }}>
            <button
              className={`tab ${activeTab === 'info' ? 'active' : ''}`}
              onClick={() => setActiveTab('info')}
            >
              <BsPerson /> Bilgiler
            </button>
            <button
              className={`tab ${activeTab === 'notes' ? 'active' : ''}`}
              onClick={() => setActiveTab('notes')}
            >
              <BsChatDots /> Notlar
            </button>
            <button
              className={`tab ${activeTab === 'history' ? 'active' : ''}`}
              onClick={() => setActiveTab('history')}
            >
              <BsClockHistory /> Geçmiş
            </button>
          </div>

          {/* Tab Content */}
          <div style={{ minHeight: '200px' }}>
            {activeTab === 'info' && (
              <div>
                {/* Status Change */}
                <div style={{ marginBottom: '1.5rem', padding: '1rem', background: 'var(--surface-secondary)', borderRadius: 'var(--radius-md)' }}>
                  <label style={{ fontSize: '0.875rem', fontWeight: 500, marginBottom: '0.5rem', display: 'block' }}>
                    Durum Değiştir
                  </label>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '0.5rem' }}>
                    <select
                      className="form-control"
                      value={newStatus}
                      onChange={(e) => setNewStatus(e.target.value)}
                      style={{ flex: '1 1 200px', minWidth: 0 }}
                    >
                      {statusOptions.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                      ))}
                    </select>
                    <button
                      className="btn btn-primary"
                      onClick={handleStatusChange}
                      disabled={changingStatus || newStatus === application.status}
                      style={{ flexShrink: 0 }}
                    >
                      {changingStatus ? '...' : 'Uygula'}
                    </button>
                  </div>
                  <input
                    type="text"
                    className="form-control"
                    value={statusNote}
                    onChange={(e) => setStatusNote(e.target.value)}
                    placeholder="Durum değişikliği notu (opsiyonel)"
                  />
                </div>

                {/* Form Data */}
                {Object.keys(application.form_data || {}).length > 0 && (
                  <div>
                    <h4 style={{ fontSize: '0.9375rem', marginBottom: '0.75rem', color: 'var(--text-primary)' }}>
                      Form Bilgileri
                    </h4>
                    <div style={{ display: 'grid', gap: '0.5rem' }}>
                      {Object.entries(application.form_data).map(([key, value]) => (
                        <div key={key} style={{ 
                          display: 'flex', 
                          padding: '0.5rem 0',
                          borderBottom: '1px solid var(--border-primary)',
                        }}>
                          <span style={{ width: '40%', fontWeight: 500, color: 'var(--text-secondary)' }}>
                            {key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                          </span>
                          <span style={{ color: 'var(--text-primary)' }}>
                            {typeof value === 'object' ? JSON.stringify(value) : String(value)}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Meta Info */}
                <div style={{ marginTop: '1.5rem', display: 'flex', gap: '1rem', fontSize: '0.8125rem', color: 'var(--text-tertiary)' }}>
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                    <BsCalendar size={12} /> Başvuru: {new Date(application.created_at).toLocaleDateString('tr-TR')}
                  </span>
                </div>
              </div>
            )}

            {activeTab === 'notes' && (
              <div>
                <div className="form-group">
                  <label className="form-label">Notlar</label>
                  <textarea
                    className="form-control"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={6}
                    placeholder="Bu aday hakkında notlarınızı buraya yazabilirsiniz..."
                  />
                </div>
                <button
                  className="btn btn-primary"
                  onClick={handleSaveNotes}
                  disabled={savingNotes}
                >
                  {savingNotes ? 'Kaydediliyor...' : 'Notları Kaydet'}
                </button>
              </div>
            )}

            {activeTab === 'history' && (
              <div>
                {application.status_logs && application.status_logs.length > 0 ? (
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                    {application.status_logs.map((log) => (
                      <div 
                        key={log.id} 
                        style={{ 
                          padding: '0.75rem 1rem',
                          background: 'var(--surface-secondary)',
                          borderRadius: 'var(--radius-md)',
                          borderLeft: `3px solid ${statusLabels[log.status]?.color || '#94a3b8'}`,
                        }}
                      >
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.25rem' }}>
                          <span 
                            className="badge" 
                            style={{ 
                              background: statusLabels[log.status]?.color || '#94a3b8',
                              color: 'white',
                              fontSize: '0.6875rem',
                            }}
                          >
                            {statusLabels[log.status]?.label || log.status}
                          </span>
                          <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                            {new Date(log.created_at).toLocaleString('tr-TR')}
                          </span>
                        </div>
                        {log.notes && (
                          <p style={{ margin: '0.5rem 0 0', fontSize: '0.8125rem', color: 'var(--text-secondary)' }}>
                            {log.notes}
                          </p>
                        )}
                        {log.user && (
                          <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
                            {log.user}
                          </span>
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                    Henüz durum geçmişi yok
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      ) : (
        <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
          Başvuru bulunamadı
        </div>
      )}
    </Modal>
  );
};

export default ApplicationDetailModal;


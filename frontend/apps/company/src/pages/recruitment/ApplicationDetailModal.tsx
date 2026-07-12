import React, { useEffect, useState, useCallback, useRef } from 'react';
import { recruitmentApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
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

function resolveStageMeta(
  stages: LookupItem[],
  status: string
): { label: string; color: string } {
  const found = stages.find((s) => s.value === status);
  return {
    label: found?.label || status,
    color: found?.color || '#94a3b8',
  };
}

const ApplicationDetailModal: React.FC<ApplicationDetailModalProps> = ({
  isOpen,
  onClose,
  applicationId,
  onUpdate,
}) => {
  const [loading, setLoading] = useState(true);
  const [application, setApplication] = useState<ApplicationDetail | null>(null);
  const [stageOptions, setStageOptions] = useState<LookupItem[]>([]);
  const [activeTab, setActiveTab] = useState<'info' | 'notes' | 'history'>('info');
  const [notes, setNotes] = useState('');
  const [savingNotes, setSavingNotes] = useState(false);
  const [newStatus, setNewStatus] = useState('');
  const [statusNote, setStatusNote] = useState('');
  const [changingStatus, setChangingStatus] = useState(false);
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  const loadStageLookups = useCallback(async () => {
    try {
      const response = await lookupsApi.forType('application_stage');
      setStageOptions(response.data.data ?? []);
    } catch {
      toast.error('Başvuru aşamaları yüklenemedi');
    }
  }, []);

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
      void loadStageLookups();
      void loadApplication();
    }
  }, [isOpen, applicationId, loadApplication, loadStageLookups]);

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
      void loadApplication();
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
      void loadApplication();
      onUpdate();
    } catch {
      toast.error('Puanlama başarısız');
    }
  };

  if (!isOpen) return null;

  const currentMeta = application
    ? resolveStageMeta(stageOptions, application.status)
    : { label: '', color: '#94a3b8' };

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
                  background: currentMeta.color,
                  color: 'white',
                  flexShrink: 0,
                }}
              >
                {currentMeta.label}
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
                  type="button"
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
              type="button"
              className={`tab ${activeTab === 'info' ? 'active' : ''}`}
              onClick={() => setActiveTab('info')}
            >
              <BsPerson /> Bilgiler
            </button>
            <button
              type="button"
              className={`tab ${activeTab === 'notes' ? 'active' : ''}`}
              onClick={() => setActiveTab('notes')}
            >
              <BsChatDots /> Notlar
            </button>
            <button
              type="button"
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
                    <div style={{ flex: '1 1 200px', minWidth: 0 }}>
                      <Select
                        value={newStatus}
                        onChange={setNewStatus}
                        options={stageOptions.map((opt) => ({
                          value: opt.value,
                          label: opt.label,
                          color: opt.color,
                        }))}
                        placeholder="Durum seçin..."
                        aria-label="Başvuru durumu"
                      />
                    </div>
                    <button
                      type="button"
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
                            {key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
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
                  type="button"
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
                    {application.status_logs.map((log) => {
                      const logMeta = resolveStageMeta(stageOptions, log.status);
                      return (
                        <div
                          key={log.id}
                          style={{
                            padding: '0.75rem 1rem',
                            background: 'var(--surface-secondary)',
                            borderRadius: 'var(--radius-md)',
                            borderLeft: `3px solid ${logMeta.color}`,
                          }}
                        >
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.25rem' }}>
                            <span
                              className="badge"
                              style={{
                                background: logMeta.color,
                                color: 'white',
                                fontSize: '0.6875rem',
                              }}
                            >
                              {logMeta.label}
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
                      );
                    })}
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

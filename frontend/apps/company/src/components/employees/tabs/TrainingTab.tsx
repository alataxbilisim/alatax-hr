import React from 'react';
import { BsBook, BsAward } from 'react-icons/bs';

export interface TrainingParticipation {
  id: number;
  status: string;
  session: {
    id: number;
    training_id: number;
    start_date: string;
    end_date?: string;
    location?: string;
    status: string;
    training: {
      id: number;
      title: string;
      category?: string;
      description?: string;
    };
  };
}

export interface TrainingCertificate {
  id: number;
  training: {
    id: number;
    title: string;
  };
  certificate_number?: string;
  issued_date: string;
  expiry_date?: string;
}

export interface EmployeeTrainingData {
  participations: TrainingParticipation[];
  certificates: TrainingCertificate[];
}

interface TrainingTabProps {
  participations: TrainingParticipation[];
  certificates: TrainingCertificate[];
}

const TrainingTab: React.FC<TrainingTabProps> = ({ participations, certificates }) => {
  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  const getSessionStatusBadge = (status: string) => {
    const map: Record<string, { label: string; className: string }> = {
      scheduled: { label: 'Planlandı', className: 'badge-info' },
      in_progress: { label: 'Devam Ediyor', className: 'badge-primary' },
      completed: { label: 'Tamamlandı', className: 'badge-success' },
      cancelled: { label: 'İptal', className: 'badge-danger' },
    };
    const info = map[status] || { label: status, className: 'badge-secondary' };
    return <span className={`badge ${info.className}`}>{info.label}</span>;
  };

  const getParticipantStatusBadge = (status: string) => {
    const map: Record<string, { label: string; className: string }> = {
      registered: { label: 'Kayıtlı', className: 'badge-info' },
      attended: { label: 'Katıldı', className: 'badge-success' },
      absent: { label: 'Katılmadı', className: 'badge-danger' },
      completed: { label: 'Tamamladı', className: 'badge-success' },
    };
    const info = map[status] || { label: status, className: 'badge-secondary' };
    return <span className={`badge ${info.className}`}>{info.label}</span>;
  };

  return (
    <div>
      {/* Eğitimler */}
      <h4 style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <BsBook style={{ color: 'var(--primary)' }} />
        Katılınan Eğitimler
      </h4>

      {participations.length === 0 ? (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-body" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            Henüz eğitim katılımı bulunmuyor
          </div>
        </div>
      ) : (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Eğitim</th>
                  <th>Kategori</th>
                  <th>Tarih</th>
                  <th>Konum</th>
                  <th>Oturum Durumu</th>
                  <th>Katılım</th>
                </tr>
              </thead>
              <tbody>
                {participations.map((p) => (
                  <tr key={p.id}>
                    <td>
                      <div style={{ fontWeight: 500 }}>{p.session.training.title}</div>
                      {p.session.training.description && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          {p.session.training.description.substring(0, 60)}...
                        </div>
                      )}
                    </td>
                    <td>
                      {p.session.training.category && (
                        <span className="badge badge-secondary">{p.session.training.category}</span>
                      )}
                    </td>
                    <td>
                      <div style={{ fontSize: '0.875rem' }}>{formatDate(p.session.start_date)}</div>
                      {p.session.end_date && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          {formatDate(p.session.end_date)}
                        </div>
                      )}
                    </td>
                    <td>{p.session.location || '-'}</td>
                    <td>{getSessionStatusBadge(p.session.status)}</td>
                    <td>{getParticipantStatusBadge(p.status)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Sertifikalar */}
      <h4 style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <BsAward style={{ color: 'var(--warning)' }} />
        Sertifikalar
      </h4>

      {certificates.length === 0 ? (
        <div className="card">
          <div className="card-body" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            Henüz sertifika bulunmuyor
          </div>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '1rem' }}>
          {certificates.map((cert) => (
            <div key={cert.id} className="card">
              <div className="card-body">
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: '0.75rem' }}>
                  <div
                    style={{
                      width: 48,
                      height: 48,
                      borderRadius: 'var(--radius-md)',
                      background: 'var(--warning-soft, rgba(251, 191, 36, 0.1))',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0,
                    }}
                  >
                    <BsAward size={24} style={{ color: 'var(--warning)' }} />
                  </div>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 500, marginBottom: '0.25rem' }}>{cert.training.title}</div>
                    {cert.certificate_number && (
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.5rem' }}>
                        #{cert.certificate_number}
                      </div>
                    )}
                    <div style={{ display: 'flex', gap: '1rem', fontSize: '0.75rem' }}>
                      <div>
                        <span style={{ color: 'var(--text-tertiary)' }}>Verilme: </span>
                        {formatDate(cert.issued_date)}
                      </div>
                      {cert.expiry_date && (
                        <div>
                          <span style={{ color: 'var(--text-tertiary)' }}>Geçerlilik: </span>
                          {formatDate(cert.expiry_date)}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default TrainingTab;


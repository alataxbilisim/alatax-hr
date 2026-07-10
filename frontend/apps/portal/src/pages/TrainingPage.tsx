import React, { useCallback, useEffect, useState } from 'react';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsMortarboard, BsAward } from 'react-icons/bs';

interface Training {
  id: number;
  training: {
    id: number;
    title: string;
    description: string;
    category: string;
    type: string;
    duration_hours: number;
    is_mandatory: boolean;
  };
  session: {
    id: number;
    start_date: string;
    end_date: string;
    location: string;
    status: string;
  };
  status: string;
  score: number | null;
  passed: boolean | null;
  registered_at: string | null;
  completed_at: string | null;
  has_certificate: boolean;
  certificate_id: number | null;
}

const TrainingPage: React.FC = () => {
  const [trainings, setTrainings] = useState<Training[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'upcoming' | 'completed'>('all');

  const loadTrainings = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (filter === 'upcoming') {
        params.upcoming_only = true;
      } else if (filter === 'completed') {
        params.completed_only = true;
      }
      const response = await portalApi.training.list(params);
      setTrainings(response.data.data.data || []);
    } catch {
      toast.error('Eğitimler yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    loadTrainings();
  }, [loadTrainings]);

  const getStatusBadge = (training: Training) => {
    if (training.completed_at) {
      return <span className="request-status approved">Tamamlandı</span>;
    }
    if (training.status === 'attended') {
      return <span className="request-status pending">Katıldı</span>;
    }
    if (training.status === 'registered') {
      return <span className="request-status pending">Kayıtlı</span>;
    }
    return <span className="request-status cancelled">Bilinmiyor</span>;
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Eğitimlerim</h1>
          <p className="page-subtitle">Eğitimlerinizi görüntüleyin ve sertifikalarınızı takip edin</p>
        </div>
      </div>

      {/* Filters - Mobile Tabs */}
      <div className="nav-tabs-mobile mb-4">
        <button
          className={`nav-tab-mobile ${filter === 'all' ? 'active' : ''}`}
          onClick={() => setFilter('all')}
        >
          Tümü
        </button>
        <button
          className={`nav-tab-mobile ${filter === 'upcoming' ? 'active' : ''}`}
          onClick={() => setFilter('upcoming')}
        >
          Yaklaşan
        </button>
        <button
          className={`nav-tab-mobile ${filter === 'completed' ? 'active' : ''}`}
          onClick={() => setFilter('completed')}
        >
          Tamamlanan
        </button>
      </div>

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : trainings.length > 0 ? (
            <>
              {/* Desktop Table */}
              <div className="table-responsive desktop-only">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Eğitim Adı</th>
                      <th>Tür</th>
                      <th>Başlangıç Tarihi</th>
                      <th>Süre</th>
                      <th>Durum</th>
                      <th>Puan</th>
                      <th>Sertifika</th>
                    </tr>
                  </thead>
                  <tbody>
                    {trainings.map((training) => (
                      <tr key={training.id}>
                        <td>
                          <strong>{training.training.title}</strong>
                          {training.training.is_mandatory && (
                            <span className="badge bg-warning text-dark ms-2">Zorunlu</span>
                          )}
                        </td>
                        <td>
                          <span className="badge bg-info">{training.training.type}</span>
                          {training.training.category && (
                            <span className="text-muted ms-2">{training.training.category}</span>
                          )}
                        </td>
                        <td>
                          {training.session.start_date
                            ? new Date(training.session.start_date).toLocaleDateString('tr-TR')
                            : '-'}
                        </td>
                        <td>{training.training.duration_hours || '-'} saat</td>
                        <td>{getStatusBadge(training)}</td>
                        <td>
                          {training.score !== null ? (
                            <span className={training.passed ? 'text-success' : 'text-danger'}>
                              {training.score}
                            </span>
                          ) : (
                            '-'
                          )}
                        </td>
                        <td>
                          {training.has_certificate ? (
                            <BsAward className="text-success" size={20} title="Sertifika mevcut" />
                          ) : (
                            '-'
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Mobile Cards */}
              <div className="mobile-card-list has-data">
                {trainings.map((training) => (
                  <div key={training.id} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">
                          {training.training.title}
                          {training.training.is_mandatory && (
                            <span className="badge bg-warning text-dark ms-2" style={{ fontSize: '0.65rem' }}>Zorunlu</span>
                          )}
                        </div>
                        <div className="mobile-card-subtitle">
                          {training.training.type} • {training.training.duration_hours} saat
                        </div>
                      </div>
                      {getStatusBadge(training)}
                    </div>
                    <div className="mobile-card-body">
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Tarih</span>
                        <span className="mobile-card-value">
                          {training.session.start_date
                            ? new Date(training.session.start_date).toLocaleDateString('tr-TR')
                            : '-'}
                        </span>
                      </div>
                      {training.session.location && (
                        <div className="mobile-card-row">
                          <span className="mobile-card-label">Konum</span>
                          <span className="mobile-card-value">{training.session.location}</span>
                        </div>
                      )}
                      {training.score !== null && (
                        <div className="mobile-card-row">
                          <span className="mobile-card-label">Puan</span>
                          <span className={`mobile-card-value ${training.passed ? 'text-success' : 'text-danger'}`}>
                            {training.score}
                          </span>
                        </div>
                      )}
                      {training.has_certificate && (
                        <div className="mobile-card-row">
                          <span className="mobile-card-label">Sertifika</span>
                          <span className="mobile-card-value text-success">
                            <BsAward size={16} className="me-1" /> Mevcut
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </>
          ) : (
            <div className="empty-state">
              <BsMortarboard size={64} className="text-muted mb-3" />
              <h3>Henüz eğitiminiz yok</h3>
              <p>Size atanan eğitimler burada görünecektir</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default TrainingPage;

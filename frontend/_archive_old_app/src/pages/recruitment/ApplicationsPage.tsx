import React, { useState, useEffect, useCallback } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';

interface Application {
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
  created_at: string;
}

const statusOptions = [
  { value: 'new', label: 'Yeni Başvuru', color: 'badge-info' },
  { value: 'reviewing', label: 'İnceleniyor', color: 'badge-warning' },
  { value: 'interview', label: 'Mülakat', color: 'badge-primary' },
  { value: 'testing', label: 'Test Aşaması', color: 'badge-secondary' },
  { value: 'offer', label: 'Teklif Gönderildi', color: 'badge-accent' },
  { value: 'accepted', label: 'Kabul Edildi', color: 'badge-success' },
  { value: 'rejected', label: 'Reddedildi', color: 'badge-danger' },
  { value: 'pool', label: 'Havuzda', color: 'badge-secondary' },
];

const ApplicationsPage: React.FC = () => {
  const [applications, setApplications] = useState<Application[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedApplication, setSelectedApplication] = useState<Application | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [showDetailModal, setShowDetailModal] = useState(false);

  const loadApplications = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, string> = {};
      if (statusFilter) params.status = statusFilter;
      if (searchQuery) params.search = searchQuery;
      
      const response = await api.get('/api/v1/recruitment/applications', { params });
      setApplications(response.data.data || []);
    } catch (error) {
      console.error('Başvurular yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  }, [statusFilter, searchQuery]);

  useEffect(() => {
    loadApplications();
  }, [loadApplications]);

  const handleStatusChange = async (application: Application, newStatus: string) => {
    try {
      await api.put(`/api/v1/recruitment/applications/${application.id}/status`, {
        status: newStatus,
      });
      toast.success('Durum güncellendi');
      loadApplications();
    } catch (error) {
      toast.error('Durum güncellenemedi');
    }
  };

  const handleViewDetail = (application: Application) => {
    setSelectedApplication(application);
    setShowDetailModal(true);
  };

  const getStatusBadge = (status: string) => {
    const option = statusOptions.find(s => s.value === status);
    return option || { value: status, label: status, color: 'badge-secondary' };
  };

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('tr-TR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Başvurular</h1>
          <p className="page-subtitle">{applications.length} başvuru</p>
        </div>
      </div>

      {/* Filters */}
      <div className="card mb-4">
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-6">
              <div className="input-icon">
                <i className="bi bi-search"></i>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Ad, e-posta veya telefon ile ara..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-4">
              <select
                className="form-select"
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
              >
                <option value="">Tüm Durumlar</option>
                {statusOptions.map((status) => (
                  <option key={status.value} value={status.value}>
                    {status.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <button className="btn btn-outline-secondary w-100" onClick={loadApplications}>
                <i className="bi bi-arrow-clockwise me-2"></i>
                Yenile
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Applications Table */}
      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="text-center py-8">
              <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Yükleniyor...</span>
              </div>
            </div>
          ) : applications.length === 0 ? (
            <div className="text-center py-12">
              <div className="text-6xl mb-4">📋</div>
              <h3 className="text-xl font-semibold mb-2">Henüz başvuru yok</h3>
              <p className="text-[var(--text-secondary)]">
                Başvurular geldiğinde burada listelenecek
              </p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Aday</th>
                    <th>Pozisyon</th>
                    <th>Durum</th>
                    <th>Puan</th>
                    <th>Başvuru Tarihi</th>
                    <th className="text-end">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {applications.map((app) => {
                    const status = getStatusBadge(app.status);
                    return (
                      <tr key={app.id}>
                        <td>
                          <div className="d-flex align-items-center gap-3">
                            <div
                              className="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                              style={{ background: 'var(--primary)' }}
                            >
                              {app.applicant_name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                              <div className="font-semibold">{app.applicant_name}</div>
                              <small className="text-[var(--text-secondary)]">
                                {app.applicant_email}
                              </small>
                            </div>
                          </div>
                        </td>
                        <td>
                          {app.position?.title || (
                            <span className="text-[var(--text-muted)]">Genel Başvuru</span>
                          )}
                        </td>
                        <td>
                          <div className="dropdown">
                            <button
                              className={`badge ${status.color} dropdown-toggle`}
                              data-bs-toggle="dropdown"
                              style={{ cursor: 'pointer' }}
                            >
                              {status.label}
                            </button>
                            <ul className="dropdown-menu">
                              {statusOptions.map((s) => (
                                <li key={s.value}>
                                  <button
                                    className="dropdown-item"
                                    onClick={() => handleStatusChange(app, s.value)}
                                  >
                                    <span className={`badge ${s.color} me-2`}></span>
                                    {s.label}
                                  </button>
                                </li>
                              ))}
                            </ul>
                          </div>
                        </td>
                        <td>
                          {app.rating ? (
                            <div className="flex items-center gap-1">
                              {[1, 2, 3, 4, 5].map((star) => (
                                <i
                                  key={star}
                                  className={`bi bi-star${star <= app.rating! ? '-fill' : ''} text-yellow-500`}
                                ></i>
                              ))}
                            </div>
                          ) : (
                            <span className="text-[var(--text-muted)]">-</span>
                          )}
                        </td>
                        <td>{formatDate(app.created_at)}</td>
                        <td className="text-end">
                          <button
                            className="btn btn-sm btn-outline-primary"
                            onClick={() => handleViewDetail(app)}
                          >
                            <i className="bi bi-eye me-1"></i>
                            Detay
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Detail Modal */}
      {showDetailModal && selectedApplication && (
        <div className="modal-backdrop show" onClick={() => setShowDetailModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">Başvuru Detayı</h5>
                  <button type="button" className="btn-close" onClick={() => setShowDetailModal(false)}></button>
                </div>
                <div className="modal-body">
                  <div className="row g-4">
                    <div className="col-md-6">
                      <h6 className="text-[var(--text-secondary)] mb-1">Ad Soyad</h6>
                      <p className="font-semibold">{selectedApplication.applicant_name}</p>
                    </div>
                    <div className="col-md-6">
                      <h6 className="text-[var(--text-secondary)] mb-1">E-posta</h6>
                      <p className="font-semibold">{selectedApplication.applicant_email}</p>
                    </div>
                    <div className="col-md-6">
                      <h6 className="text-[var(--text-secondary)] mb-1">Telefon</h6>
                      <p className="font-semibold">{selectedApplication.applicant_phone || '-'}</p>
                    </div>
                    <div className="col-md-6">
                      <h6 className="text-[var(--text-secondary)] mb-1">Pozisyon</h6>
                      <p className="font-semibold">{selectedApplication.position?.title || 'Genel Başvuru'}</p>
                    </div>
                    <div className="col-md-6">
                      <h6 className="text-[var(--text-secondary)] mb-1">Durum</h6>
                      <span className={`badge ${getStatusBadge(selectedApplication.status).color}`}>
                        {getStatusBadge(selectedApplication.status).label}
                      </span>
                    </div>
                    <div className="col-md-6">
                      <h6 className="text-[var(--text-secondary)] mb-1">Başvuru Tarihi</h6>
                      <p className="font-semibold">{formatDate(selectedApplication.created_at)}</p>
                    </div>
                    {selectedApplication.cv_path && (
                      <div className="col-12">
                        <h6 className="text-[var(--text-secondary)] mb-1">CV</h6>
                        <a
                          href={selectedApplication.cv_path}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="btn btn-outline-primary btn-sm"
                        >
                          <i className="bi bi-download me-1"></i>
                          CV İndir
                        </a>
                      </div>
                    )}
                    {selectedApplication.notes && (
                      <div className="col-12">
                        <h6 className="text-[var(--text-secondary)] mb-1">Notlar</h6>
                        <p>{selectedApplication.notes}</p>
                      </div>
                    )}
                    {selectedApplication.form_data && Object.keys(selectedApplication.form_data).length > 0 && (
                      <div className="col-12">
                        <h6 className="text-[var(--text-secondary)] mb-2">Form Cevapları</h6>
                        <div className="space-y-2">
                          {Object.entries(selectedApplication.form_data).map(([key, value]) => (
                            <div key={key} className="p-2 bg-[var(--surface-secondary)] rounded">
                              <span className="text-sm text-[var(--text-secondary)]">{key}:</span>
                              <span className="ms-2">{String(value)}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowDetailModal(false)}>
                    Kapat
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .modal-backdrop.show {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          z-index: 1050;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .modal.show { position: relative; z-index: 1051; }
        .modal-dialog { margin: 0; }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
        }
        .modal-header { border-bottom: 1px solid var(--border-primary); }
        .modal-footer { border-top: 1px solid var(--border-primary); }
        .dropdown-menu {
          background: var(--surface-primary);
          border-color: var(--border-primary);
        }
        .dropdown-item { color: var(--text-primary); }
        .dropdown-item:hover { background: var(--surface-secondary); }
      `}</style>
    </div>
  );
};

export default ApplicationsPage;


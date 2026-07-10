import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';
import toast from 'react-hot-toast';

interface JobPosition {
  id: number;
  title: string;
  slug: string;
  department: string | null;
  location: string | null;
  employment_type: string;
  experience_level: string;
  status: 'draft' | 'active' | 'paused' | 'closed';
  applications_count: number;
  new_applications_count?: number;
  created_at: string;
  published_at: string | null;
}

interface JobFormData {
  title: string;
  description: string;
  requirements: string;
  responsibilities: string;
  department: string;
  location: string;
  employment_type: string;
  experience_level: string;
  salary_min: string;
  salary_max: string;
  salary_visible: boolean;
  positions_count: number;
  application_deadline: string;
}

const initialFormData: JobFormData = {
  title: '',
  description: '',
  requirements: '',
  responsibilities: '',
  department: '',
  location: '',
  employment_type: 'full_time',
  experience_level: 'mid',
  salary_min: '',
  salary_max: '',
  salary_visible: false,
  positions_count: 1,
  application_deadline: '',
};

const employmentTypes: Record<string, string> = {
  full_time: 'Tam Zamanlı',
  part_time: 'Yarı Zamanlı',
  contract: 'Sözleşmeli',
  internship: 'Staj',
  remote: 'Uzaktan',
};

const experienceLevels: Record<string, string> = {
  entry: 'Yeni Başlayan',
  mid: 'Orta Seviye',
  senior: 'Kıdemli',
  lead: 'Takım Lideri',
  manager: 'Yönetici',
};

const statusLabels: Record<string, { text: string; class: string }> = {
  draft: { text: 'Taslak', class: 'badge-secondary' },
  active: { text: 'Aktif', class: 'badge-success' },
  paused: { text: 'Duraklatıldı', class: 'badge-warning' },
  closed: { text: 'Kapatıldı', class: 'badge-danger' },
};

const JobPositionsPage: React.FC = () => {
  const [positions, setPositions] = useState<JobPosition[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingPosition, setEditingPosition] = useState<JobPosition | null>(null);
  const [formData, setFormData] = useState<JobFormData>(initialFormData);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

  const loadPositions = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (searchQuery) params.search = searchQuery;
      if (statusFilter) params.status = statusFilter;
      
      const response = await api.get('/recruitment/positions', { params });
      setPositions(response.data.data || []);
      if (response.data.meta) {
        setMeta(response.data.meta);
      }
    } catch (error) {
      console.error('Pozisyonlar yüklenemedi:', error);
      toast.error('Pozisyonlar yüklenirken bir hata oluştu');
    } finally {
      setLoading(false);
    }
  }, [searchQuery, statusFilter]);

  useEffect(() => {
    loadPositions();
  }, [loadPositions]);

  const handleAddClick = () => {
    setEditingPosition(null);
    setFormData(initialFormData);
    setShowModal(true);
  };

  const handleEditClick = async (position: JobPosition) => {
    try {
      const response = await api.get(`/recruitment/positions/${position.id}`);
      const data = response.data.data;
      setEditingPosition(position);
      setFormData({
        title: data.title || '',
        description: data.description || '',
        requirements: data.requirements || '',
        responsibilities: data.responsibilities || '',
        department: data.department || '',
        location: data.location || '',
        employment_type: data.employment_type || 'full_time',
        experience_level: data.experience_level || 'mid',
        salary_min: data.salary_min || '',
        salary_max: data.salary_max || '',
        salary_visible: data.salary_visible || false,
        positions_count: data.positions_count || 1,
        application_deadline: data.application_deadline?.split('T')[0] || '',
      });
      setShowModal(true);
    } catch (error) {
      toast.error('Pozisyon bilgileri yüklenemedi');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    try {
      const data = {
        ...formData,
        salary_min: formData.salary_min ? parseFloat(formData.salary_min) : null,
        salary_max: formData.salary_max ? parseFloat(formData.salary_max) : null,
      };

      if (editingPosition) {
        await api.put(`/recruitment/positions/${editingPosition.id}`, data);
        toast.success('Pozisyon güncellendi');
      } else {
        await api.post('/recruitment/positions', data);
        toast.success('Pozisyon oluşturuldu');
      }
      setShowModal(false);
      loadPositions();
    } catch (error) {
      console.error('Pozisyon kaydedilemedi:', error);
    } finally {
      setSubmitting(false);
    }
  };

  const handleStatusChange = async (position: JobPosition, newStatus: string) => {
    try {
      await api.put(`/recruitment/positions/${position.id}`, { status: newStatus });
      toast.success('Pozisyon durumu güncellendi');
      loadPositions();
    } catch (error) {
      toast.error('Durum güncellenemedi');
    }
  };

  const handleDelete = async (position: JobPosition) => {
    if (!confirm(`"${position.title}" pozisyonunu silmek istediğinize emin misiniz?`)) return;
    
    try {
      await api.delete(`/recruitment/positions/${position.id}`);
      toast.success('Pozisyon silindi');
      loadPositions();
    } catch (error) {
      console.error('Pozisyon silinemedi:', error);
    }
  };

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">İş Pozisyonları</h1>
          <p className="page-subtitle">
            {meta.total} pozisyon kayıtlı
          </p>
        </div>
        <button className="btn btn-primary" onClick={handleAddClick}>
          <i className="bi bi-plus-lg me-2"></i>
          Yeni Pozisyon
        </button>
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
                  placeholder="Pozisyon adı, departman veya lokasyon ile ara..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-3">
              <select
                className="form-select"
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
              >
                <option value="">Tüm Durumlar</option>
                <option value="draft">Taslak</option>
                <option value="active">Aktif</option>
                <option value="paused">Duraklatıldı</option>
                <option value="closed">Kapatıldı</option>
              </select>
            </div>
            <div className="col-md-3">
              <button 
                className="btn btn-outline-secondary w-100"
                onClick={loadPositions}
              >
                <i className="bi bi-arrow-clockwise me-2"></i>
                Yenile
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="text-center py-5">
              <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Yükleniyor...</span>
              </div>
            </div>
          ) : positions.length === 0 ? (
            <div className="empty-state py-5">
              <div className="empty-state-icon">📋</div>
              <h3 className="empty-state-title">Henüz pozisyon yok</h3>
              <p className="empty-state-text">İlk iş ilanınızı oluşturun</p>
              <button className="btn btn-primary" onClick={handleAddClick}>
                <i className="bi bi-plus-lg me-2"></i>
                Yeni Pozisyon
              </button>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Pozisyon</th>
                    <th>Departman</th>
                    <th>Tip</th>
                    <th>Başvuru</th>
                    <th>Durum</th>
                    <th>Tarih</th>
                    <th className="text-end">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {positions.map((position) => {
                    const status = statusLabels[position.status];
                    
                    return (
                      <tr key={position.id}>
                        <td>
                          <div className="fw-semibold">{position.title}</div>
                          {position.location && (
                            <small className="text-muted">
                              <i className="bi bi-geo-alt me-1"></i>
                              {position.location}
                            </small>
                          )}
                        </td>
                        <td>{position.department || '-'}</td>
                        <td>
                          <span className="small">
                            {employmentTypes[position.employment_type]}
                          </span>
                        </td>
                        <td>
                          <span className="fw-semibold">{position.applications_count}</span>
                          {position.new_applications_count !== undefined && position.new_applications_count > 0 && (
                            <span className="badge badge-primary ms-2">
                              {position.new_applications_count} yeni
                            </span>
                          )}
                        </td>
                        <td>
                          <span className={`badge ${status.class}`}>
                            {status.text}
                          </span>
                        </td>
                        <td className="text-muted small">
                          {new Date(position.created_at).toLocaleDateString('tr-TR')}
                        </td>
                        <td>
                          <div className="d-flex gap-1 justify-content-end">
                            <button
                              className="btn btn-sm btn-outline-secondary"
                              title="Düzenle"
                              onClick={() => handleEditClick(position)}
                            >
                              <i className="bi bi-pencil"></i>
                            </button>
                            {position.status === 'draft' && (
                              <button
                                className="btn btn-sm btn-outline-success"
                                title="Yayınla"
                                onClick={() => handleStatusChange(position, 'active')}
                              >
                                <i className="bi bi-play-circle"></i>
                              </button>
                            )}
                            {position.status === 'active' && (
                              <button
                                className="btn btn-sm btn-outline-warning"
                                title="Duraklat"
                                onClick={() => handleStatusChange(position, 'paused')}
                              >
                                <i className="bi bi-pause-circle"></i>
                              </button>
                            )}
                            <button
                              className="btn btn-sm btn-outline-danger"
                              title="Sil"
                              onClick={() => handleDelete(position)}
                            >
                              <i className="bi bi-trash"></i>
                            </button>
                          </div>
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

      {/* Modal */}
      {showModal && (
        <div className="modal-backdrop show" onClick={() => setShowModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-xl" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">
                    {editingPosition ? 'Pozisyon Düzenle' : 'Yeni Pozisyon'}
                  </h5>
                  <button
                    type="button"
                    className="btn-close"
                    onClick={() => setShowModal(false)}
                  ></button>
                </div>
                <form onSubmit={handleSubmit}>
                  <div className="modal-body" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
                    <div className="row g-3">
                      <div className="col-md-8">
                        <label className="form-label">Pozisyon Adı *</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.title}
                          onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                          required
                          placeholder="ör: Kıdemli Yazılım Geliştirici"
                        />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Açık Pozisyon Sayısı</label>
                        <input
                          type="number"
                          className="form-control"
                          min="1"
                          value={formData.positions_count}
                          onChange={(e) => setFormData({ ...formData, positions_count: parseInt(e.target.value) })}
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Departman</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.department}
                          onChange={(e) => setFormData({ ...formData, department: e.target.value })}
                          placeholder="ör: Yazılım Geliştirme"
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Lokasyon</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.location}
                          onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                          placeholder="ör: İstanbul, Türkiye"
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Çalışma Tipi</label>
                        <select
                          className="form-select"
                          value={formData.employment_type}
                          onChange={(e) => setFormData({ ...formData, employment_type: e.target.value })}
                        >
                          {Object.entries(employmentTypes).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                          ))}
                        </select>
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Deneyim Seviyesi</label>
                        <select
                          className="form-select"
                          value={formData.experience_level}
                          onChange={(e) => setFormData({ ...formData, experience_level: e.target.value })}
                        >
                          {Object.entries(experienceLevels).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                          ))}
                        </select>
                      </div>
                      <div className="col-12">
                        <label className="form-label">İş Tanımı</label>
                        <textarea
                          className="form-control"
                          rows={4}
                          value={formData.description}
                          onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                          placeholder="Pozisyon hakkında genel bilgi..."
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Gereksinimler</label>
                        <textarea
                          className="form-control"
                          rows={4}
                          value={formData.requirements}
                          onChange={(e) => setFormData({ ...formData, requirements: e.target.value })}
                          placeholder="Aranan nitelikler..."
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Sorumluluklar</label>
                        <textarea
                          className="form-control"
                          rows={4}
                          value={formData.responsibilities}
                          onChange={(e) => setFormData({ ...formData, responsibilities: e.target.value })}
                          placeholder="Pozisyonun sorumlulukları..."
                        />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Minimum Maaş (TL)</label>
                        <input
                          type="number"
                          className="form-control"
                          min="0"
                          value={formData.salary_min}
                          onChange={(e) => setFormData({ ...formData, salary_min: e.target.value })}
                        />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Maximum Maaş (TL)</label>
                        <input
                          type="number"
                          className="form-control"
                          min="0"
                          value={formData.salary_max}
                          onChange={(e) => setFormData({ ...formData, salary_max: e.target.value })}
                        />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Son Başvuru Tarihi</label>
                        <input
                          type="date"
                          className="form-control"
                          value={formData.application_deadline}
                          onChange={(e) => setFormData({ ...formData, application_deadline: e.target.value })}
                        />
                      </div>
                      <div className="col-12">
                        <div className="form-check">
                          <input
                            className="form-check-input"
                            type="checkbox"
                            id="salaryVisible"
                            checked={formData.salary_visible}
                            onChange={(e) => setFormData({ ...formData, salary_visible: e.target.checked })}
                          />
                          <label className="form-check-label" htmlFor="salaryVisible">
                            Maaş bilgisini ilanlarda göster
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="modal-footer">
                    <button
                      type="button"
                      className="btn btn-secondary"
                      onClick={() => setShowModal(false)}
                    >
                      İptal
                    </button>
                    <button
                      type="submit"
                      className="btn btn-primary"
                      disabled={submitting}
                    >
                      {submitting ? (
                        <>
                          <span className="spinner-border spinner-border-sm me-2"></span>
                          Kaydediliyor...
                        </>
                      ) : (
                        <>
                          <i className="bi bi-check-lg me-2"></i>
                          Kaydet
                        </>
                      )}
                    </button>
                  </div>
                </form>
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
        .modal.show {
          position: relative;
          z-index: 1051;
        }
        .modal-dialog {
          margin: 0;
          max-height: 90vh;
        }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
          max-height: 90vh;
          overflow: hidden;
        }
        .modal-header {
          border-bottom: 1px solid var(--border-primary);
          padding: 1rem 1.5rem;
        }
        .modal-footer {
          border-top: 1px solid var(--border-primary);
          padding: 1rem 1.5rem;
        }
      `}</style>
    </div>
  );
};

export default JobPositionsPage;


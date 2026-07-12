import React, { useEffect, useState } from 'react';
import { portalApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';
import { BsPlus, BsInboxes, BsX } from 'react-icons/bs';

interface RequestType {
  id: number;
  name: string;
  icon: string | null;
  color: string | null;
  description: string | null;
}

interface EmployeeRequest {
  id: number;
  title: string;
  description: string | null;
  request_type: RequestType;
  status: string;
  priority: string;
  created_at: string;
}

const statusClassMap: Record<string, string> = {
  pending: 'pending',
  in_review: 'pending',
  approved: 'approved',
  rejected: 'rejected',
  cancelled: 'cancelled',
};

const RequestsPage: React.FC = () => {
  const [requests, setRequests] = useState<EmployeeRequest[]>([]);
  const [requestTypes, setRequestTypes] = useState<RequestType[]>([]);
  const [statusLookups, setStatusLookups] = useState<LookupItem[]>([]);
  const [priorityLookups, setPriorityLookups] = useState<LookupItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [formData, setFormData] = useState({
    request_type_id: '',
    title: '',
    description: '',
    priority: 'normal',
  });

  useEffect(() => {
    void loadData();
  }, []);

  const loadData = async () => {
    try {
      const [requestsRes, typesRes, statusRes, priorityRes] = await Promise.all([
        portalApi.requests.list(),
        portalApi.requests.types(),
        lookupsApi.forType('employee_request_status'),
        lookupsApi.forType('employee_request_priority'),
      ]);
      setRequests(requestsRes.data.data.data || []);
      setRequestTypes(typesRes.data.data || []);
      setStatusLookups(statusRes.data.data ?? []);
      setPriorityLookups(priorityRes.data.data ?? []);
    } catch {
      toast.error('Veriler yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const label = statusLookups.find((o) => o.value === status)?.label || status;
    const className = statusClassMap[status] || '';
    return <span className={`request-status ${className}`}>{label}</span>;
  };

  const getPriorityBadge = (priority: string) => {
    const item = priorityLookups.find((o) => o.value === priority);
    const label = item?.label || priority;
    const color = item?.color;
    if (color) {
      return (
        <span className="badge" style={{ backgroundColor: color, color: '#fff' }}>
          {label}
        </span>
      );
    }
    return <span className="badge bg-secondary">{label}</span>;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.request_type_id || !formData.title) {
      toast.error('Lütfen zorunlu alanları doldurun');
      return;
    }

    setSubmitting(true);
    try {
      const data = new FormData();
      data.append('request_type_id', formData.request_type_id);
      data.append('title', formData.title);
      data.append('priority', formData.priority);
      if (formData.description) data.append('description', formData.description);

      await portalApi.requests.create(data);
      toast.success('Talep oluşturuldu');
      setShowModal(false);
      setFormData({ request_type_id: '', title: '', description: '', priority: 'normal' });
      void loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Talep oluşturulamadı');
    } finally {
      setSubmitting(false);
    }
  };

  const handleCancel = async (id: number) => {
    if (!confirm('Bu talebi iptal etmek istediğinize emin misiniz?')) return;
    try {
      await portalApi.requests.cancel(id);
      toast.success('Talep iptal edildi');
      void loadData();
    } catch {
      toast.error('İptal işlemi başarısız');
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Taleplerim</h1>
          <p className="page-subtitle">Tüm taleplerinizi görüntüleyin ve yönetin</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => setShowModal(true)}>
            <BsPlus size={20} /> Yeni Talep
          </button>
        </div>
      </div>

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : requests.length > 0 ? (
            <>
              <div className="table-responsive desktop-only">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Talep</th>
                      <th>Tür</th>
                      <th>Durum</th>
                      <th>Öncelik</th>
                      <th>Tarih</th>
                      <th>İşlem</th>
                    </tr>
                  </thead>
                  <tbody>
                    {requests.map((request) => (
                      <tr key={request.id}>
                        <td>
                          <div className="fw-semibold">{request.title}</div>
                          {request.description && (
                            <div className="text-muted small">{request.description.substring(0, 50)}...</div>
                          )}
                        </td>
                        <td>{request.request_type?.name}</td>
                        <td>{getStatusBadge(request.status)}</td>
                        <td>{getPriorityBadge(request.priority)}</td>
                        <td>{new Date(request.created_at).toLocaleDateString('tr-TR')}</td>
                        <td>
                          {request.status === 'pending' && (
                            <button
                              className="btn btn-sm btn-ghost text-danger"
                              onClick={() => void handleCancel(request.id)}
                            >
                              İptal
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="mobile-card-list has-data">
                {requests.map((request) => (
                  <div key={request.id} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">{request.title}</div>
                        <div className="mobile-card-subtitle">{request.request_type?.name}</div>
                      </div>
                      {getStatusBadge(request.status)}
                    </div>
                    <div className="mobile-card-body">
                      {request.description && (
                        <div className="mobile-card-row">
                          <span className="mobile-card-value" style={{ fontSize: '0.85rem', color: 'var(--portal-text-muted)' }}>
                            {request.description}
                          </span>
                        </div>
                      )}
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Öncelik</span>
                        {getPriorityBadge(request.priority)}
                      </div>
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Tarih</span>
                        <span className="mobile-card-value">
                          {new Date(request.created_at).toLocaleDateString('tr-TR')}
                        </span>
                      </div>
                    </div>
                    {request.status === 'pending' && (
                      <div className="mobile-card-footer">
                        <button
                          className="btn btn-outline-primary btn-sm"
                          onClick={() => void handleCancel(request.id)}
                        >
                          İptal Et
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </>
          ) : (
            <div className="empty-state">
              <BsInboxes size={64} className="text-muted mb-3" />
              <h3>Henüz talebiniz yok</h3>
              <p>Yeni bir talep oluşturarak başlayın</p>
              <button className="btn btn-primary" onClick={() => setShowModal(true)}>
                <BsPlus /> İlk Talebinizi Oluşturun
              </button>
            </div>
          )}
        </div>
      </div>

      <button className="fab" onClick={() => setShowModal(true)} aria-label="Yeni Talep">
        <BsPlus size={24} />
      </button>

      {showModal && (
        <div className="modal-mobile open">
          <div className="modal-mobile-header">
            <h3 className="modal-mobile-title">Yeni Talep</h3>
            <button className="modal-mobile-close" onClick={() => setShowModal(false)}>
              <BsX size={24} />
            </button>
          </div>
          <form onSubmit={(e) => void handleSubmit(e)}>
            <div className="modal-mobile-body">
              <div className="mb-3">
                <label className="form-label">Talep Türü *</label>
                <Select
                  value={formData.request_type_id}
                  onChange={(v) => setFormData({ ...formData, request_type_id: v })}
                  options={requestTypes.map((type) => ({
                    value: String(type.id),
                    label: type.name,
                  }))}
                  allowEmpty
                  placeholder="Seçiniz"
                  aria-label="Talep türü"
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Talep Başlığı *</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  placeholder="Talep başlığını yazın"
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Öncelik</label>
                <Select
                  value={formData.priority}
                  onChange={(v) => setFormData({ ...formData, priority: v || 'normal' })}
                  options={priorityLookups.map((opt) => ({
                    value: opt.value,
                    label: opt.label,
                    color: opt.color,
                  }))}
                  placeholder="Öncelik seçin"
                  aria-label="Öncelik"
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Açıklama</label>
                <textarea
                  className="form-control"
                  rows={4}
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Talebinizi detaylı açıklayın"
                />
              </div>
            </div>
            <div className="modal-mobile-footer">
              <button
                type="button"
                className="btn btn-outline-primary"
                onClick={() => setShowModal(false)}
              >
                İptal
              </button>
              <button type="submit" className="btn btn-primary" disabled={submitting}>
                {submitting ? 'Gönderiliyor...' : 'Talep Oluştur'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
};

export default RequestsPage;

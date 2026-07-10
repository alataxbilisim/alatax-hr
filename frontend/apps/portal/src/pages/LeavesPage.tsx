import React, { useEffect, useState } from 'react';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsPlus, BsCalendarCheck, BsX } from 'react-icons/bs';

interface LeaveRequest {
  id: number;
  leave_type: { id: number; name: string };
  start_date: string;
  end_date: string;
  total_days: number;
  status: string;
  reason: string | null;
  created_at: string;
}

interface LeaveType {
  id: number;
  name: string;
  max_days: number;
}

interface LeaveBalance {
  type: string;
  type_id: number;
  remaining: number;
  used: number;
  total: number;
}

const LeavesPage: React.FC = () => {
  const [leaves, setLeaves] = useState<LeaveRequest[]>([]);
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([]);
  const [balances, setBalances] = useState<LeaveBalance[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  
  // Form state
  const [formData, setFormData] = useState({
    leave_type_id: '',
    start_date: '',
    end_date: '',
    reason: '',
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [leavesRes, typesRes, balancesRes] = await Promise.all([
        portalApi.leaves.list(),
        portalApi.leaves.types(),
        portalApi.leaves.balances(),
      ]);
      setLeaves(leavesRes.data.data.data || []);
      setLeaveTypes(typesRes.data.data || []);
      setBalances(balancesRes.data.data || []);
    } catch {
      toast.error('Veriler yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; class: string }> = {
      pending: { label: 'Beklemede', class: 'pending' },
      approved: { label: 'Onaylandı', class: 'approved' },
      rejected: { label: 'Reddedildi', class: 'rejected' },
      cancelled: { label: 'İptal', class: 'cancelled' },
    };
    const s = statusMap[status] || { label: status, class: '' };
    return <span className={`request-status ${s.class}`}>{s.label}</span>;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.leave_type_id || !formData.start_date || !formData.end_date) {
      toast.error('Lütfen zorunlu alanları doldurun');
      return;
    }

    setSubmitting(true);
    try {
      const data = new FormData();
      data.append('leave_type_id', formData.leave_type_id);
      data.append('start_date', formData.start_date);
      data.append('end_date', formData.end_date);
      if (formData.reason) data.append('reason', formData.reason);

      await portalApi.leaves.create(data);
      toast.success('İzin talebi oluşturuldu');
      setShowModal(false);
      setFormData({ leave_type_id: '', start_date: '', end_date: '', reason: '' });
      loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'İzin talebi oluşturulamadı');
    } finally {
      setSubmitting(false);
    }
  };

  const handleCancel = async (id: number) => {
    if (!confirm('Bu izin talebini iptal etmek istediğinize emin misiniz?')) return;
    try {
      await portalApi.leaves.cancel(id);
      toast.success('İzin talebi iptal edildi');
      loadData();
    } catch {
      toast.error('İptal işlemi başarısız');
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">İzinlerim</h1>
          <p className="page-subtitle">İzin taleplerinizi görüntüleyin ve yönetin</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => setShowModal(true)}>
            <BsPlus size={20} /> Yeni İzin Talebi
          </button>
        </div>
      </div>

      {/* Bakiye Kartları */}
      {balances.length > 0 && (
        <div className="row mb-4">
          {balances.map((balance, index) => (
            <div key={index} className="col-6 col-lg-3 mb-3">
              <div className="balance-card">
                <div className="balance-info">
                  <div className="balance-type">{balance.type}</div>
                  <div className="balance-value">{balance.remaining}</div>
                  <div className="balance-label">gün kaldı</div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : leaves.length > 0 ? (
            <>
              {/* Desktop Table */}
              <div className="table-responsive desktop-only">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>İzin Türü</th>
                      <th>Başlangıç</th>
                      <th>Bitiş</th>
                      <th>Süre</th>
                      <th>Durum</th>
                      <th>Tarih</th>
                      <th>İşlem</th>
                    </tr>
                  </thead>
                  <tbody>
                    {leaves.map((leave) => (
                      <tr key={leave.id}>
                        <td>{leave.leave_type?.name}</td>
                        <td>{new Date(leave.start_date).toLocaleDateString('tr-TR')}</td>
                        <td>{new Date(leave.end_date).toLocaleDateString('tr-TR')}</td>
                        <td>{leave.total_days} gün</td>
                        <td>{getStatusBadge(leave.status)}</td>
                        <td>{new Date(leave.created_at).toLocaleDateString('tr-TR')}</td>
                        <td>
                          {leave.status === 'pending' && (
                            <button
                              className="btn btn-sm btn-ghost text-danger"
                              onClick={() => handleCancel(leave.id)}
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

              {/* Mobile Cards */}
              <div className="mobile-card-list has-data">
                {leaves.map((leave) => (
                  <div key={leave.id} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">{leave.leave_type?.name}</div>
                        <div className="mobile-card-subtitle">
                          {leave.total_days} gün
                        </div>
                      </div>
                      {getStatusBadge(leave.status)}
                    </div>
                    <div className="mobile-card-body">
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Tarih</span>
                        <span className="mobile-card-value">
                          {new Date(leave.start_date).toLocaleDateString('tr-TR')} - {new Date(leave.end_date).toLocaleDateString('tr-TR')}
                        </span>
                      </div>
                      {leave.reason && (
                        <div className="mobile-card-row">
                          <span className="mobile-card-label">Sebep</span>
                          <span className="mobile-card-value">{leave.reason}</span>
                        </div>
                      )}
                    </div>
                    {leave.status === 'pending' && (
                      <div className="mobile-card-footer">
                        <button
                          className="btn btn-outline-primary btn-sm"
                          onClick={() => handleCancel(leave.id)}
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
              <BsCalendarCheck size={64} className="text-muted mb-3" />
              <h3>Henüz izin talebiniz yok</h3>
              <p>Yeni bir izin talebi oluşturarak başlayın</p>
              <button className="btn btn-primary" onClick={() => setShowModal(true)}>
                <BsPlus /> İlk İzin Talebini Oluştur
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Mobile FAB */}
      <button className="fab" onClick={() => setShowModal(true)} aria-label="Yeni İzin Talebi">
        <BsPlus size={24} />
      </button>

      {/* Modal */}
      {showModal && (
        <div className={`modal-mobile open`}>
          <div className="modal-mobile-header">
            <h3 className="modal-mobile-title">Yeni İzin Talebi</h3>
            <button className="modal-mobile-close" onClick={() => setShowModal(false)}>
              <BsX size={24} />
            </button>
          </div>
          <form onSubmit={handleSubmit}>
            <div className="modal-mobile-body">
              <div className="mb-3">
                <label className="form-label">İzin Türü *</label>
                <select
                  className="form-control"
                  value={formData.leave_type_id}
                  onChange={(e) => setFormData({ ...formData, leave_type_id: e.target.value })}
                  required
                >
                  <option value="">Seçiniz</option>
                  {leaveTypes.map((type) => (
                    <option key={type.id} value={type.id}>
                      {type.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="mb-3">
                <label className="form-label">Başlangıç Tarihi *</label>
                <input
                  type="date"
                  className="form-control"
                  value={formData.start_date}
                  onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Bitiş Tarihi *</label>
                <input
                  type="date"
                  className="form-control"
                  value={formData.end_date}
                  onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Açıklama</label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={formData.reason}
                  onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                  placeholder="İzin sebebinizi yazın (opsiyonel)"
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

export default LeavesPage;

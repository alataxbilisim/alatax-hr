import React, { useEffect, useState } from 'react';
import { leavesApi } from '../../services/api';
import { BsCalendarCheck, BsPlus, BsCheck, BsX, BsEye, BsClock, BsCalendar3 } from 'react-icons/bs';
import toast from 'react-hot-toast';

interface LeaveType {
  id: number;
  name: string;
  code: string;
  is_paid: boolean;
  default_days: number;
}

interface LeaveRequest {
  id: number;
  user: { id: number; name: string };
  leave_type: LeaveType;
  start_date: string;
  end_date: string;
  total_days: number;
  reason: string;
  status: 'pending' | 'approved' | 'rejected' | 'cancelled';
  created_at: string;
}

interface LeaveBalance {
  id: number;
  leave_type: LeaveType;
  total_days: number;
  used_days: number;
  pending_days: number;
  remaining_days: number;
}

const LeavesPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'my-leaves' | 'pending' | 'calendar'>('my-leaves');
  const [myRequests, setMyRequests] = useState<LeaveRequest[]>([]);
  const [pendingRequests, setPendingRequests] = useState<LeaveRequest[]>([]);
  const [balances, setBalances] = useState<LeaveBalance[]>([]);
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
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
    setLoading(true);
    try {
      const [typesRes, balanceRes, myReqRes, pendingRes] = await Promise.all([
        leavesApi.types.list(),
        leavesApi.balance.myBalance(),
        leavesApi.requests.myRequests(),
        leavesApi.requests.pendingApprovals(),
      ]);
      setLeaveTypes(typesRes.data.data?.data || []);
      setBalances(balanceRes.data.data || []);
      setMyRequests(myReqRes.data.data?.data || []);
      setPendingRequests(pendingRes.data.data?.data || []);
    } catch (error) {
      console.error('Veriler yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const data = new FormData();
      data.append('leave_type_id', formData.leave_type_id);
      data.append('start_date', formData.start_date);
      data.append('end_date', formData.end_date);
      if (formData.reason) data.append('reason', formData.reason);

      await leavesApi.requests.create(data);
      toast.success('İzin talebi oluşturuldu');
      setShowModal(false);
      setFormData({ leave_type_id: '', start_date: '', end_date: '', reason: '' });
      loadData();
    } catch (error) {
      console.error('İzin talebi oluşturulamadı:', error);
    }
  };

  const handleApprove = async (id: number) => {
    try {
      await leavesApi.requests.approve(id);
      toast.success('İzin onaylandı');
      loadData();
    } catch (error) {
      console.error('İzin onaylanamadı:', error);
    }
  };

  const handleReject = async (id: number) => {
    const reason = prompt('Ret sebebini girin:');
    if (!reason) return;
    try {
      await leavesApi.requests.reject(id, reason);
      toast.success('İzin reddedildi');
      loadData();
    } catch (error) {
      console.error('İzin reddedilemedi:', error);
    }
  };

  const getStatusBadge = (status: string) => {
    const styles: Record<string, string> = {
      pending: 'badge-warning',
      approved: 'badge-success',
      rejected: 'badge-danger',
      cancelled: 'badge-secondary',
    };
    const labels: Record<string, string> = {
      pending: 'Bekliyor',
      approved: 'Onaylandı',
      rejected: 'Reddedildi',
      cancelled: 'İptal',
    };
    return <span className={`badge ${styles[status]}`}>{labels[status]}</span>;
  };

  if (loading) {
    return <div className="animate-fade-in p-4"><p>Yükleniyor...</p></div>;
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">İzin Yönetimi</h1>
          <p className="page-subtitle">İzin taleplerinizi yönetin</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => setShowModal(true)}>
            <BsPlus /> İzin Talebi Oluştur
          </button>
        </div>
      </div>

      {/* Bakiye Kartları */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {balances.slice(0, 4).map((balance) => (
          <div key={balance.id} className="stat-card">
            <div className="stat-card-icon" style={{ background: 'var(--primary-soft)', color: 'var(--primary)' }}>
              <BsCalendarCheck />
            </div>
            <div className="stat-card-value">{balance.remaining_days || (balance.total_days - balance.used_days - balance.pending_days)}</div>
            <div className="stat-card-label">{balance.leave_type?.name} (Kalan)</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div className="tabs mb-4">
        <button className={`tab ${activeTab === 'my-leaves' ? 'active' : ''}`} onClick={() => setActiveTab('my-leaves')}>
          <BsClock /> İzinlerim
        </button>
        <button className={`tab ${activeTab === 'pending' ? 'active' : ''}`} onClick={() => setActiveTab('pending')}>
          <BsEye /> Onay Bekleyenler {pendingRequests.length > 0 && <span className="badge badge-danger ml-2">{pendingRequests.length}</span>}
        </button>
        <button className={`tab ${activeTab === 'calendar' ? 'active' : ''}`} onClick={() => setActiveTab('calendar')}>
          <BsCalendar3 /> Takvim
        </button>
      </div>

      {/* Content */}
      <div className="card">
        <div className="card-body">
          {activeTab === 'my-leaves' && (
            <>
              {myRequests.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>İzin Türü</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Gün</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                      </tr>
                    </thead>
                    <tbody>
                      {myRequests.map((req) => (
                        <tr key={req.id}>
                          <td>{req.leave_type?.name}</td>
                          <td>{new Date(req.start_date).toLocaleDateString('tr-TR')}</td>
                          <td>{new Date(req.end_date).toLocaleDateString('tr-TR')}</td>
                          <td>{req.total_days}</td>
                          <td>{getStatusBadge(req.status)}</td>
                          <td>{new Date(req.created_at).toLocaleDateString('tr-TR')}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="empty-state">
                  <div className="empty-state-icon"><BsCalendarCheck /></div>
                  <h3 className="empty-state-title">Henüz izin talebiniz yok</h3>
                  <p className="empty-state-text">Yeni bir izin talebi oluşturarak başlayın.</p>
                </div>
              )}
            </>
          )}

          {activeTab === 'pending' && (
            <>
              {pendingRequests.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Çalışan</th>
                        <th>İzin Türü</th>
                        <th>Tarih</th>
                        <th>Gün</th>
                        <th>Sebep</th>
                        <th>İşlemler</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pendingRequests.map((req) => (
                        <tr key={req.id}>
                          <td>{req.user?.name}</td>
                          <td>{req.leave_type?.name}</td>
                          <td>{new Date(req.start_date).toLocaleDateString('tr-TR')} - {new Date(req.end_date).toLocaleDateString('tr-TR')}</td>
                          <td>{req.total_days}</td>
                          <td>{req.reason || '-'}</td>
                          <td>
                            <div className="btn-group">
                              <button className="btn btn-sm btn-success" onClick={() => handleApprove(req.id)}><BsCheck /> Onayla</button>
                              <button className="btn btn-sm btn-danger" onClick={() => handleReject(req.id)}><BsX /> Reddet</button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="empty-state">
                  <div className="empty-state-icon"><BsCheck /></div>
                  <h3 className="empty-state-title">Onay bekleyen talep yok</h3>
                </div>
              )}
            </>
          )}

          {activeTab === 'calendar' && (
            <div className="empty-state">
              <div className="empty-state-icon"><BsCalendar3 /></div>
              <h3 className="empty-state-title">Takvim Görünümü</h3>
              <p className="empty-state-text">Takvim entegrasyonu yakında eklenecek...</p>
            </div>
          )}
        </div>
      </div>

      {/* Modal */}
      {showModal && (
        <div className="modal show" style={{ display: 'block' }} tabIndex={-1}>
          <div className="modal-dialog">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Yeni İzin Talebi</h5>
                <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
              </div>
              <form onSubmit={handleSubmit}>
                <div className="modal-body">
                  <div className="mb-3">
                    <label className="form-label">İzin Türü*</label>
                    <select
                      className="form-select"
                      value={formData.leave_type_id}
                      onChange={(e) => setFormData({ ...formData, leave_type_id: e.target.value })}
                      required
                    >
                      <option value="">Seçiniz</option>
                      {leaveTypes.map((type) => (
                        <option key={type.id} value={type.id}>{type.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="row g-3 mb-3">
                    <div className="col-6">
                      <label className="form-label">Başlangıç Tarihi*</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.start_date}
                        onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                        required
                      />
                    </div>
                    <div className="col-6">
                      <label className="form-label">Bitiş Tarihi*</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.end_date}
                        onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Açıklama</label>
                    <textarea
                      className="form-control"
                      value={formData.reason}
                      onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                      rows={3}
                    ></textarea>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>İptal</button>
                  <button type="submit" className="btn btn-primary">Gönder</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default LeavesPage;


import React, { useEffect, useState } from 'react';
import { onboardingApi, usersApi } from '../../services/api';
import { BsPersonCheck, BsPlus, BsPencil, BsTrash, BsEye, BsCheck2Circle, BsClipboardCheck, BsListTask } from 'react-icons/bs';
import toast from 'react-hot-toast';

interface OnboardingTemplate {
  id: number;
  name: string;
  description: string;
  tasks: Array<{ title: string; type: string; is_required: boolean }>;
  estimated_days: number;
  is_active: boolean;
  is_default: boolean;
  processes_count: number;
}

interface OnboardingProcess {
  id: number;
  user: { id: number; name: string; email: string };
  template: OnboardingTemplate | null;
  title: string;
  start_date: string;
  target_end_date: string | null;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  progress: number;
  tasks_count: number;
  assigned_to: { id: number; name: string } | null;
}

const OnboardingPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'processes' | 'templates'>('processes');
  const [processes, setProcesses] = useState<OnboardingProcess[]>([]);
  const [templates, setTemplates] = useState<OnboardingTemplate[]>([]);
  const [users, setUsers] = useState<Array<{ id: number; name: string; email: string }>>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [modalType, setModalType] = useState<'process' | 'template'>('process');
  const [formData, setFormData] = useState({
    user_id: '',
    template_id: '',
    title: '',
    start_date: new Date().toISOString().split('T')[0],
    target_end_date: '',
    assigned_to: '',
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [processesRes, templatesRes, usersRes] = await Promise.all([
        onboardingApi.processes.list(),
        onboardingApi.templates.list(),
        usersApi.list({ per_page: 100 }),
      ]);
      setProcesses(processesRes.data.data?.data || []);
      setTemplates(templatesRes.data.data?.data || []);
      setUsers(usersRes.data.data?.data || []);
    } catch (error) {
      console.error('Veriler yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateProcess = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await onboardingApi.processes.create({
        user_id: parseInt(formData.user_id),
        template_id: formData.template_id ? parseInt(formData.template_id) : null,
        title: formData.title,
        start_date: formData.start_date,
        target_end_date: formData.target_end_date || null,
        assigned_to: formData.assigned_to ? parseInt(formData.assigned_to) : null,
      });
      toast.success('Onboarding süreci oluşturuldu');
      setShowModal(false);
      loadData();
    } catch (error) {
      console.error('Onboarding süreci oluşturulamadı:', error);
    }
  };

  const handleDeleteProcess = async (id: number) => {
    if (!confirm('Bu süreci silmek istediğinizden emin misiniz?')) return;
    try {
      await onboardingApi.processes.delete(id);
      toast.success('Süreç silindi');
      loadData();
    } catch (error) {
      console.error('Süreç silinemedi:', error);
    }
  };

  const getStatusBadge = (status: string) => {
    const styles: Record<string, string> = {
      pending: 'badge-warning',
      in_progress: 'badge-primary',
      completed: 'badge-success',
      cancelled: 'badge-secondary',
    };
    const labels: Record<string, string> = {
      pending: 'Bekliyor',
      in_progress: 'Devam Ediyor',
      completed: 'Tamamlandı',
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
          <h1 className="page-title">Onboarding</h1>
          <p className="page-subtitle">İşe alım süreçlerini yönetin</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => { setModalType('process'); setShowModal(true); }}>
            <BsPlus /> Yeni Süreç Başlat
          </button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--warning-soft)', color: 'var(--warning)' }}>
            <BsClipboardCheck />
          </div>
          <div className="stat-card-value">{processes.filter(p => p.status === 'pending').length}</div>
          <div className="stat-card-label">Bekleyen</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--primary-soft)', color: 'var(--primary)' }}>
            <BsListTask />
          </div>
          <div className="stat-card-value">{processes.filter(p => p.status === 'in_progress').length}</div>
          <div className="stat-card-label">Devam Eden</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--success-soft)', color: 'var(--success)' }}>
            <BsCheck2Circle />
          </div>
          <div className="stat-card-value">{processes.filter(p => p.status === 'completed').length}</div>
          <div className="stat-card-label">Tamamlanan</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--info-soft)', color: 'var(--info)' }}>
            <BsPersonCheck />
          </div>
          <div className="stat-card-value">{templates.length}</div>
          <div className="stat-card-label">Şablon</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="tabs mb-4">
        <button className={`tab ${activeTab === 'processes' ? 'active' : ''}`} onClick={() => setActiveTab('processes')}>
          <BsListTask /> Aktif Süreçler
        </button>
        <button className={`tab ${activeTab === 'templates' ? 'active' : ''}`} onClick={() => setActiveTab('templates')}>
          <BsClipboardCheck /> Şablonlar
        </button>
      </div>

      {/* Content */}
      <div className="card">
        <div className="card-body">
          {activeTab === 'processes' && (
            <>
              {processes.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Çalışan</th>
                        <th>Süreç</th>
                        <th>İlerleme</th>
                        <th>Başlangıç</th>
                        <th>Durum</th>
                        <th>Sorumlu</th>
                        <th>İşlemler</th>
                      </tr>
                    </thead>
                    <tbody>
                      {processes.map((proc) => (
                        <tr key={proc.id}>
                          <td>
                            <div>{proc.user?.name}</div>
                            <div className="text-sm text-secondary">{proc.user?.email}</div>
                          </td>
                          <td>{proc.title}</td>
                          <td>
                            <div className="progress" style={{ width: '100px', height: '8px' }}>
                              <div 
                                className="progress-bar" 
                                style={{ width: `${proc.progress}%`, backgroundColor: proc.progress === 100 ? 'var(--success)' : 'var(--primary)' }}
                              ></div>
                            </div>
                            <small>{proc.progress}%</small>
                          </td>
                          <td>{new Date(proc.start_date).toLocaleDateString('tr-TR')}</td>
                          <td>{getStatusBadge(proc.status)}</td>
                          <td>{proc.assigned_to?.name || '-'}</td>
                          <td>
                            <div className="btn-group">
                              <button className="btn btn-sm btn-info" title="Görüntüle"><BsEye /></button>
                              <button className="btn btn-sm btn-secondary" title="Düzenle"><BsPencil /></button>
                              <button className="btn btn-sm btn-danger" title="Sil" onClick={() => handleDeleteProcess(proc.id)}><BsTrash /></button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="empty-state">
                  <div className="empty-state-icon"><BsPersonCheck /></div>
                  <h3 className="empty-state-title">Aktif onboarding süreci yok</h3>
                  <p className="empty-state-text">Yeni bir onboarding süreci başlatarak çalışanlarınızı karşılayın.</p>
                </div>
              )}
            </>
          )}

          {activeTab === 'templates' && (
            <>
              {templates.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {templates.map((template) => (
                    <div key={template.id} className="card">
                      <div className="card-body">
                        <div className="d-flex justify-content-between align-items-start mb-3">
                          <h4 className="card-title mb-0">{template.name}</h4>
                          {template.is_default && <span className="badge badge-primary">Varsayılan</span>}
                        </div>
                        <p className="text-secondary">{template.description || 'Açıklama yok'}</p>
                        <div className="d-flex gap-3 mt-3">
                          <span><strong>{template.tasks?.length || 0}</strong> Görev</span>
                          <span><strong>{template.estimated_days}</strong> Gün</span>
                          <span><strong>{template.processes_count || 0}</strong> Kullanım</span>
                        </div>
                        <div className="mt-3 pt-3 border-top">
                          <div className="btn-group">
                            <button className="btn btn-sm btn-info"><BsEye /> Görüntüle</button>
                            <button className="btn btn-sm btn-secondary"><BsPencil /> Düzenle</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="empty-state">
                  <div className="empty-state-icon"><BsClipboardCheck /></div>
                  <h3 className="empty-state-title">Şablon bulunamadı</h3>
                  <p className="empty-state-text">Onboarding şablonları oluşturarak süreçleri standartlaştırın.</p>
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {/* Modal */}
      {showModal && modalType === 'process' && (
        <div className="modal show" style={{ display: 'block' }} tabIndex={-1}>
          <div className="modal-dialog">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Yeni Onboarding Süreci</h5>
                <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
              </div>
              <form onSubmit={handleCreateProcess}>
                <div className="modal-body">
                  <div className="mb-3">
                    <label className="form-label">Çalışan*</label>
                    <select
                      className="form-select"
                      value={formData.user_id}
                      onChange={(e) => setFormData({ ...formData, user_id: e.target.value })}
                      required
                    >
                      <option value="">Seçiniz</option>
                      {users.map((user) => (
                        <option key={user.id} value={user.id}>{user.name} ({user.email})</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Şablon</label>
                    <select
                      className="form-select"
                      value={formData.template_id}
                      onChange={(e) => setFormData({ ...formData, template_id: e.target.value })}
                    >
                      <option value="">Şablon Kullanma</option>
                      {templates.filter(t => t.is_active).map((template) => (
                        <option key={template.id} value={template.id}>{template.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Süreç Başlığı*</label>
                    <input
                      type="text"
                      className="form-control"
                      value={formData.title}
                      onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                      placeholder="Örn: Yazılımcı Onboarding"
                      required
                    />
                  </div>
                  <div className="row g-3 mb-3">
                    <div className="col-6">
                      <label className="form-label">Başlangıç*</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.start_date}
                        onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                        required
                      />
                    </div>
                    <div className="col-6">
                      <label className="form-label">Hedef Bitiş</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.target_end_date}
                        onChange={(e) => setFormData({ ...formData, target_end_date: e.target.value })}
                      />
                    </div>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Sorumlu İK</label>
                    <select
                      className="form-select"
                      value={formData.assigned_to}
                      onChange={(e) => setFormData({ ...formData, assigned_to: e.target.value })}
                    >
                      <option value="">Seçiniz</option>
                      {users.map((user) => (
                        <option key={user.id} value={user.id}>{user.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>İptal</button>
                  <button type="submit" className="btn btn-primary">Başlat</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default OnboardingPage;


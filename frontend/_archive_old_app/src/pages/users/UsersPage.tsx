import React, { useState, useEffect, useCallback } from 'react';
import { usersApi, rolesApi } from '../../services/api';
import toast from 'react-hot-toast';

interface Role {
  id: number;
  name: string;
  permissions: string[];
}

interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  title: string | null;
  department: string | null;
  type: 'company_admin' | 'user';
  is_active: boolean;
  roles: { id: number; name: string }[];
  last_login_at: string | null;
  created_at: string;
}

interface UserFormData {
  name: string;
  email: string;
  password: string;
  phone: string;
  title: string;
  department: string;
  type: 'company_admin' | 'user';
  roles: string[];
  is_active: boolean;
}

const initialFormData: UserFormData = {
  name: '',
  email: '',
  password: '',
  phone: '',
  title: '',
  department: '',
  type: 'user',
  roles: [],
  is_active: true,
};

const UsersPage: React.FC = () => {
  const [users, setUsers] = useState<User[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [formData, setFormData] = useState<UserFormData>(initialFormData);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

  // Kullanıcıları yükle
  const loadUsers = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (searchQuery) params.search = searchQuery;
      if (statusFilter !== '') params.is_active = statusFilter === 'active';
      
      const response = await usersApi.list(params);
      setUsers(response.data.data || []);
      if (response.data.meta) {
        setMeta(response.data.meta);
      }
    } catch (error) {
      console.error('Kullanıcılar yüklenemedi:', error);
      toast.error('Kullanıcılar yüklenirken bir hata oluştu');
    } finally {
      setLoading(false);
    }
  }, [searchQuery, statusFilter]);

  // Rolleri yükle
  const loadRoles = async () => {
    try {
      const response = await rolesApi.list();
      setRoles(response.data.data || []);
    } catch (error) {
      console.error('Roller yüklenemedi:', error);
    }
  };

  useEffect(() => {
    loadUsers();
    loadRoles();
  }, [loadUsers]);

  // Modal aç - Yeni kullanıcı
  const handleAddClick = () => {
    setEditingUser(null);
    setFormData(initialFormData);
    setShowModal(true);
  };

  // Modal aç - Düzenle
  const handleEditClick = (user: User) => {
    setEditingUser(user);
    setFormData({
      name: user.name || '',
      email: user.email || '',
      password: '',
      phone: user.phone || '',
      title: user.title || '',
      department: user.department || '',
      type: user.type || 'user',
      roles: user.roles?.map(r => r.name) || [],
      is_active: user.is_active,
    });
    setShowModal(true);
  };

  // Form gönder
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    try {
      const data = { ...formData };
      if (!data.password) {
        delete (data as Record<string, unknown>).password;
      }

      if (editingUser) {
        await usersApi.update(editingUser.id, data);
        toast.success('Kullanıcı güncellendi');
      } else {
        await usersApi.create(data);
        toast.success('Kullanıcı oluşturuldu');
      }
      setShowModal(false);
      loadUsers();
    } catch (error) {
      console.error('Kullanıcı kaydedilemedi:', error);
    } finally {
      setSubmitting(false);
    }
  };

  // Durum değiştir
  const handleToggleStatus = async (user: User) => {
    try {
      await usersApi.toggleStatus(user.id);
      toast.success(`Kullanıcı ${user.is_active ? 'pasifleştirildi' : 'aktifleştirildi'}`);
      loadUsers();
    } catch (error) {
      console.error('Durum değiştirilemedi:', error);
    }
  };

  // Kullanıcı sil
  const handleDelete = async (user: User) => {
    if (!confirm(`"${user.name}" kullanıcısını silmek istediğinize emin misiniz?`)) return;
    
    try {
      await usersApi.delete(user.id);
      toast.success('Kullanıcı silindi');
      loadUsers();
    } catch (error) {
      console.error('Kullanıcı silinemedi:', error);
    }
  };

  // Rol checkbox toggle
  const handleRoleToggle = (roleName: string) => {
    setFormData(prev => ({
      ...prev,
      roles: prev.roles.includes(roleName)
        ? prev.roles.filter(r => r !== roleName)
        : [...prev.roles, roleName]
    }));
  };

  // Tarih formatla
  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('tr-TR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Kullanıcılar</h1>
          <p className="page-subtitle">
            {meta.total} kullanıcı kayıtlı
          </p>
        </div>
        <button className="btn btn-primary" onClick={handleAddClick}>
          <i className="bi bi-plus-lg me-2"></i>
          Kullanıcı Ekle
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
                  placeholder="İsim, email veya departman ile ara..."
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
                <option value="active">Aktif</option>
                <option value="inactive">Pasif</option>
              </select>
            </div>
            <div className="col-md-3">
              <button 
                className="btn btn-outline-secondary w-100"
                onClick={loadUsers}
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
          ) : users.length === 0 ? (
            <div className="empty-state py-5">
              <div className="empty-state-icon">👥</div>
              <h3 className="empty-state-title">Henüz kullanıcı yok</h3>
              <p className="empty-state-text">İlk kullanıcıyı eklemek için butona tıklayın</p>
              <button className="btn btn-primary" onClick={handleAddClick}>
                <i className="bi bi-plus-lg me-2"></i>
                Kullanıcı Ekle
              </button>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Kullanıcı</th>
                    <th>Departman</th>
                    <th>Roller</th>
                    <th>Durum</th>
                    <th>Son Giriş</th>
                    <th className="text-end">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((user) => (
                    <tr key={user.id}>
                      <td>
                        <div className="d-flex align-items-center gap-3">
                          <div className="avatar avatar-md" style={{ 
                            background: user.type === 'company_admin' ? 'var(--primary)' : 'var(--surface-tertiary)', 
                            color: user.type === 'company_admin' ? 'white' : 'var(--text-primary)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            width: '40px',
                            height: '40px',
                            borderRadius: '50%',
                            fontSize: '14px',
                            fontWeight: '600'
                          }}>
                            {user.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()}
                          </div>
                          <div>
                            <div className="fw-semibold">{user.name}</div>
                            <small className="text-muted">{user.email}</small>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div>{user.title || '-'}</div>
                        <small className="text-muted">{user.department || '-'}</small>
                      </td>
                      <td>
                        <div className="d-flex gap-1 flex-wrap">
                          {user.roles?.map(role => (
                            <span key={role.id} className="badge badge-secondary">
                              {role.name}
                            </span>
                          ))}
                          {(!user.roles || user.roles.length === 0) && (
                            <span className="text-muted">-</span>
                          )}
                        </div>
                      </td>
                      <td>
                        <span className={`badge ${user.is_active ? 'badge-success' : 'badge-danger'}`}>
                          {user.is_active ? 'Aktif' : 'Pasif'}
                        </span>
                      </td>
                      <td className="text-muted">
                        {formatDate(user.last_login_at)}
                      </td>
                      <td>
                        <div className="d-flex gap-1 justify-content-end">
                          <button
                            className="btn btn-sm btn-outline-secondary"
                            title="Düzenle"
                            onClick={() => handleEditClick(user)}
                          >
                            <i className="bi bi-pencil"></i>
                          </button>
                          <button
                            className={`btn btn-sm ${user.is_active ? 'btn-outline-warning' : 'btn-outline-success'}`}
                            title={user.is_active ? 'Pasifleştir' : 'Aktifleştir'}
                            onClick={() => handleToggleStatus(user)}
                          >
                            <i className={`bi ${user.is_active ? 'bi-pause-circle' : 'bi-play-circle'}`}></i>
                          </button>
                          <button
                            className="btn btn-sm btn-outline-danger"
                            title="Sil"
                            onClick={() => handleDelete(user)}
                          >
                            <i className="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* User Modal */}
      {showModal && (
        <div className="modal-backdrop show" onClick={() => setShowModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">
                    {editingUser ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı'}
                  </h5>
                  <button
                    type="button"
                    className="btn-close"
                    onClick={() => setShowModal(false)}
                  ></button>
                </div>
                <form onSubmit={handleSubmit}>
                  <div className="modal-body">
                    <div className="row g-3">
                      <div className="col-md-6">
                        <label className="form-label">Ad Soyad *</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.name}
                          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                          required
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">E-posta *</label>
                        <input
                          type="email"
                          className="form-control"
                          value={formData.email}
                          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                          required
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">
                          Şifre {editingUser ? '(boş bırakılırsa değişmez)' : '*'}
                        </label>
                        <input
                          type="password"
                          className="form-control"
                          value={formData.password}
                          onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                          required={!editingUser}
                          placeholder={editingUser ? '••••••••' : ''}
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Telefon</label>
                        <input
                          type="tel"
                          className="form-control"
                          value={formData.phone}
                          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Ünvan</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.title}
                          onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                          placeholder="ör: İK Uzmanı"
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Departman</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.department}
                          onChange={(e) => setFormData({ ...formData, department: e.target.value })}
                          placeholder="ör: İnsan Kaynakları"
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Kullanıcı Tipi *</label>
                        <select
                          className="form-select"
                          value={formData.type}
                          onChange={(e) => setFormData({ ...formData, type: e.target.value as 'company_admin' | 'user' })}
                        >
                          <option value="user">Kullanıcı</option>
                          <option value="company_admin">Firma Admin</option>
                        </select>
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Durum</label>
                        <div className="form-check form-switch mt-2">
                          <input
                            className="form-check-input"
                            type="checkbox"
                            checked={formData.is_active}
                            onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                          />
                          <label className="form-check-label">
                            {formData.is_active ? 'Aktif' : 'Pasif'}
                          </label>
                        </div>
                      </div>
                      <div className="col-12">
                        <label className="form-label">Roller</label>
                        <div className="d-flex flex-wrap gap-2">
                          {roles.map((role) => (
                            <div key={role.id} className="form-check">
                              <input
                                className="form-check-input"
                                type="checkbox"
                                id={`role-${role.id}`}
                                checked={formData.roles.includes(role.name)}
                                onChange={() => handleRoleToggle(role.name)}
                              />
                              <label className="form-check-label" htmlFor={`role-${role.id}`}>
                                {role.name}
                              </label>
                            </div>
                          ))}
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
        .modal-body {
          padding: 1.5rem;
          overflow-y: auto;
        }
        .modal-footer {
          border-top: 1px solid var(--border-primary);
          padding: 1rem 1.5rem;
        }
      `}</style>
    </div>
  );
};

export default UsersPage;


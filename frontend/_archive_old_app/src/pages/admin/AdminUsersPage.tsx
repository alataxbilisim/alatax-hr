import React, { useState, useEffect, useCallback } from 'react';
import { adminApi } from '../../services/api';
import toast from 'react-hot-toast';

interface User {
  id: number;
  name: string;
  email: string;
  type: 'super_admin' | 'company_admin' | 'user';
  is_active: boolean;
  company?: {
    id: number;
    name: string;
  };
  last_login_at: string | null;
  created_at: string;
}

const AdminUsersPage: React.FC = () => {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

  const loadUsers = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (searchQuery) params.search = searchQuery;
      if (typeFilter) params.type = typeFilter;
      
      const response = await adminApi.users.list(params);
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
  }, [searchQuery, typeFilter]);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  const getTypeBadge = (type: string) => {
    const badges: Record<string, { class: string; text: string }> = {
      super_admin: { class: 'badge-danger', text: 'Super Admin' },
      company_admin: { class: 'badge-primary', text: 'Firma Admin' },
      user: { class: 'badge-secondary', text: 'Kullanıcı' },
    };
    return badges[type] || { class: 'badge-secondary', text: type };
  };

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
          <h1 className="page-title">Tüm Kullanıcılar</h1>
          <p className="page-subtitle">
            Platform geneli {meta.total} kullanıcı
          </p>
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
                  placeholder="İsim veya email ile ara..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-3">
              <select
                className="form-select"
                value={typeFilter}
                onChange={(e) => setTypeFilter(e.target.value)}
              >
                <option value="">Tüm Tipler</option>
                <option value="super_admin">Super Admin</option>
                <option value="company_admin">Firma Admin</option>
                <option value="user">Kullanıcı</option>
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
              <h3 className="empty-state-title">Kullanıcı bulunamadı</h3>
              <p className="empty-state-text">Arama kriterlerinize uygun kullanıcı yok</p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Kullanıcı</th>
                    <th>Tip</th>
                    <th>Firma</th>
                    <th>Durum</th>
                    <th>Son Giriş</th>
                    <th>Kayıt Tarihi</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((user) => {
                    const typeBadge = getTypeBadge(user.type);
                    
                    return (
                      <tr key={user.id}>
                        <td>
                          <div className="d-flex align-items-center gap-3">
                            <div className="avatar avatar-md" style={{ 
                              background: user.type === 'super_admin' ? 'var(--danger)' : 'var(--primary)', 
                              color: 'white',
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
                          <span className={`badge ${typeBadge.class}`}>
                            {typeBadge.text}
                          </span>
                        </td>
                        <td>
                          {user.company ? (
                            <span>{user.company.name}</span>
                          ) : (
                            <span className="text-muted">-</span>
                          )}
                        </td>
                        <td>
                          <span className={`badge ${user.is_active ? 'badge-success' : 'badge-danger'}`}>
                            {user.is_active ? 'Aktif' : 'Pasif'}
                          </span>
                        </td>
                        <td className="text-muted">
                          {formatDate(user.last_login_at)}
                        </td>
                        <td className="text-muted">
                          {formatDate(user.created_at)}
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
    </div>
  );
};

export default AdminUsersPage;


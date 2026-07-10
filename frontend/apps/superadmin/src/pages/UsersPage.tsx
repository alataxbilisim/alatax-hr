import React, { useEffect, useState, useCallback } from 'react';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsSearch, BsEye, BsPersonCircle } from 'react-icons/bs';

interface User {
  id: number;
  name: string;
  email: string;
  type: string;
  is_active: boolean;
  company?: {
    id: number;
    name: string;
  };
  created_at: string;
  last_login_at: string | null;
}

const UsersPage: React.FC = () => {
  const [users, setUsers] = useState<{ data: User[]; total: number; last_page: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  const loadUsers = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { page, per_page: 20 };
      if (search) params.search = search;
      
      const response = await adminApi.users.list(params);
      setUsers(response.data.data);
    } catch {
      toast.error('Kullanıcılar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    loadUsers();
  };

  const getUserType = (type: string) => {
    const types: Record<string, { label: string; class: string }> = {
      super_admin: { label: 'Super Admin', class: 'active' },
      company_admin: { label: 'Firma Admin', class: 'trial' },
      user: { label: 'Kullanıcı', class: 'suspended' },
    };
    return types[type] || { label: type, class: '' };
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Kullanıcılar</h1>
          <p className="page-subtitle">Tüm sistem kullanıcılarını görüntüleyin</p>
        </div>
      </div>

      {/* Search */}
      <div className="card mb-4">
        <div className="card-body">
          <form onSubmit={handleSearch} className="d-flex gap-3">
            <div className="input-group" style={{ maxWidth: '400px' }}>
              <span className="input-icon">
                <BsSearch />
              </span>
              <input
                type="text"
                className="form-control"
                placeholder="İsim veya e-posta ile ara..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <button type="submit" className="btn btn-secondary">
              Ara
            </button>
          </form>
        </div>
      </div>

      {/* Users Table */}
      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : users?.data && users.data.length > 0 ? (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Kullanıcı</th>
                    <th>Firma</th>
                    <th>Tip</th>
                    <th>Durum</th>
                    <th>Son Giriş</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {users.data.map((user) => (
                    <tr key={user.id}>
                      <td>
                        <div className="d-flex align-items-center gap-2">
                          <div className="avatar avatar-sm">
                            {user.name.charAt(0)}
                          </div>
                          <div>
                            <div className="fw-semibold">{user.name}</div>
                            <div className="text-muted small">{user.email}</div>
                          </div>
                        </div>
                      </td>
                      <td>{user.company?.name || '-'}</td>
                      <td>
                        <span className={`status-badge ${getUserType(user.type).class}`}>
                          {getUserType(user.type).label}
                        </span>
                      </td>
                      <td>
                        {user.is_active ? (
                          <span className="status-badge active">Aktif</span>
                        ) : (
                          <span className="status-badge inactive">Pasif</span>
                        )}
                      </td>
                      <td>
                        {user.last_login_at
                          ? new Date(user.last_login_at).toLocaleString('tr-TR')
                          : '-'}
                      </td>
                      <td>
                        {new Date(user.created_at).toLocaleDateString('tr-TR')}
                      </td>
                      <td>
                        <button className="btn btn-sm btn-ghost" title="Görüntüle">
                          <BsEye />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="empty-state">
              <BsPersonCircle size={48} />
              <h3>Kullanıcı bulunamadı</h3>
            </div>
          )}
        </div>

        {/* Pagination */}
        {users && users.last_page > 1 && (
          <div className="card-footer d-flex justify-content-between align-items-center">
            <span className="text-muted">
              Toplam {users.total} kullanıcı
            </span>
            <div className="btn-group">
              <button
                className="btn btn-sm btn-secondary"
                disabled={page === 1}
                onClick={() => setPage(page - 1)}
              >
                Önceki
              </button>
              <span className="btn btn-sm btn-ghost disabled">
                {page} / {users.last_page}
              </span>
              <button
                className="btn btn-sm btn-secondary"
                disabled={page === users.last_page}
                onClick={() => setPage(page + 1)}
              >
                Sonraki
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default UsersPage;


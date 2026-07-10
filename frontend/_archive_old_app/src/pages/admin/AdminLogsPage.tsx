import React, { useState, useEffect, useCallback } from 'react';
import { adminApi } from '../../services/api';
import toast from 'react-hot-toast';

interface Log {
  id: number;
  action: string;
  description: string;
  user?: {
    id: number;
    name: string;
  };
  company?: {
    id: number;
    name: string;
  };
  loggable_type: string | null;
  loggable_id: number | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

const AdminLogsPage: React.FC = () => {
  const [logs, setLogs] = useState<Log[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [actionFilter, setActionFilter] = useState('');
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

  const loadLogs = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (searchQuery) params.search = searchQuery;
      if (actionFilter) params.action = actionFilter;
      
      const response = await adminApi.logs(params);
      setLogs(response.data.data || []);
      if (response.data.meta) {
        setMeta(response.data.meta);
      }
    } catch (error) {
      console.error('Loglar yüklenemedi:', error);
      toast.error('Loglar yüklenirken bir hata oluştu');
    } finally {
      setLoading(false);
    }
  }, [searchQuery, actionFilter]);

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  const getActionBadge = (action: string) => {
    const badges: Record<string, { class: string; icon: string }> = {
      login: { class: 'badge-success', icon: 'box-arrow-in-right' },
      logout: { class: 'badge-secondary', icon: 'box-arrow-right' },
      create: { class: 'badge-primary', icon: 'plus-circle' },
      update: { class: 'badge-warning', icon: 'pencil' },
      delete: { class: 'badge-danger', icon: 'trash' },
    };
    return badges[action] || { class: 'badge-info', icon: 'info-circle' };
  };

  const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleString('tr-TR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Sistem Logları</h1>
          <p className="page-subtitle">
            Tüm işlem kayıtları ({meta.total} kayıt)
          </p>
        </div>
        <button className="btn btn-outline-secondary" onClick={loadLogs}>
          <i className="bi bi-arrow-clockwise me-2"></i>
          Yenile
        </button>
      </div>

      {/* Filters */}
      <div className="card mb-4">
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-8">
              <div className="input-icon">
                <i className="bi bi-search"></i>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Açıklama ile ara..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-4">
              <select
                className="form-select"
                value={actionFilter}
                onChange={(e) => setActionFilter(e.target.value)}
              >
                <option value="">Tüm İşlemler</option>
                <option value="login">Giriş</option>
                <option value="logout">Çıkış</option>
                <option value="create">Oluşturma</option>
                <option value="update">Güncelleme</option>
                <option value="delete">Silme</option>
              </select>
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
          ) : logs.length === 0 ? (
            <div className="empty-state py-5">
              <div className="empty-state-icon">📝</div>
              <h3 className="empty-state-title">Log kaydı bulunamadı</h3>
              <p className="empty-state-text">Arama kriterlerinize uygun kayıt yok</p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style={{ width: '160px' }}>Tarih</th>
                    <th style={{ width: '100px' }}>İşlem</th>
                    <th>Kullanıcı</th>
                    <th>Firma</th>
                    <th>Açıklama</th>
                    <th style={{ width: '130px' }}>IP Adresi</th>
                  </tr>
                </thead>
                <tbody>
                  {logs.map((log) => {
                    const actionBadge = getActionBadge(log.action);
                    
                    return (
                      <tr key={log.id}>
                        <td className="text-muted small">
                          {formatDate(log.created_at)}
                        </td>
                        <td>
                          <span className={`badge ${actionBadge.class}`}>
                            <i className={`bi bi-${actionBadge.icon} me-1`}></i>
                            {log.action}
                          </span>
                        </td>
                        <td>
                          {log.user ? (
                            <div className="d-flex align-items-center gap-2">
                              <div className="avatar avatar-sm" style={{ 
                                background: 'var(--primary)', 
                                color: 'white',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                width: '28px',
                                height: '28px',
                                borderRadius: '50%',
                                fontSize: '11px',
                                fontWeight: '600'
                              }}>
                                {log.user.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()}
                              </div>
                              <span>{log.user.name}</span>
                            </div>
                          ) : (
                            <span className="text-muted">Sistem</span>
                          )}
                        </td>
                        <td>
                          {log.company ? (
                            <span className="small">{log.company.name}</span>
                          ) : (
                            <span className="text-muted">-</span>
                          )}
                        </td>
                        <td>
                          <span className="text-truncate" style={{ maxWidth: '300px', display: 'inline-block' }}>
                            {log.description}
                          </span>
                        </td>
                        <td className="text-muted small font-monospace">
                          {log.ip_address || '-'}
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

export default AdminLogsPage;


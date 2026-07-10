import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { adminApi } from '../../services/api';
import { AdminDashboardData } from '../../types';
import {
  BsBuilding,
  BsPeople,
  BsBox,
  BsGraphUp,
  BsExclamationTriangle,
  BsClockHistory,
  BsArrowRight,
} from 'react-icons/bs';

const AdminDashboard: React.FC = () => {
  const navigate = useNavigate();
  const [data, setData] = useState<AdminDashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    try {
      const response = await adminApi.dashboard();
      setData(response.data.data);
    } catch (error) {
      console.error('Dashboard yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div style={{ padding: '2rem' }}>
        <div className="skeleton skeleton-title" style={{ width: 300, marginBottom: '2rem' }} />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '1rem' }}>
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="skeleton" style={{ height: 120 }} />
          ))}
        </div>
      </div>
    );
  }

  const stats = data?.stats;

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">SuperAdmin Dashboard</h1>
          <p className="page-subtitle">Platform geneli istatistikler ve yönetim</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => navigate('/admin/companies/create')}>
            <BsBuilding />
            Firma Ekle
          </button>
        </div>
      </div>

      {/* Stats Grid */}
      <div style={{ 
        display: 'grid', 
        gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', 
        gap: '1rem',
        marginBottom: '2rem',
      }}>
        <div className="stat-card" onClick={() => navigate('/admin/companies')} style={{ cursor: 'pointer' }}>
          <div className="stat-card-icon" style={{ background: 'var(--primary-soft)', color: 'var(--primary)' }}>
            <BsBuilding />
          </div>
          <div className="stat-card-value">{stats?.total_companies || 0}</div>
          <div className="stat-card-label">Toplam Firma</div>
        </div>

        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--success-soft)', color: 'var(--success)' }}>
            <BsGraphUp />
          </div>
          <div className="stat-card-value">{stats?.active_companies || 0}</div>
          <div className="stat-card-label">Aktif Firma</div>
        </div>

        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--warning-soft)', color: 'var(--warning)' }}>
            <BsClockHistory />
          </div>
          <div className="stat-card-value">{stats?.trial_companies || 0}</div>
          <div className="stat-card-label">Deneme Sürecinde</div>
        </div>

        <div className="stat-card" onClick={() => navigate('/admin/users')} style={{ cursor: 'pointer' }}>
          <div className="stat-card-icon" style={{ background: 'var(--info-soft)', color: 'var(--info)' }}>
            <BsPeople />
          </div>
          <div className="stat-card-value">{stats?.total_users || 0}</div>
          <div className="stat-card-label">Toplam Kullanıcı</div>
        </div>
      </div>

      {/* Two Column Layout */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' }}>
        {/* Expiring Trials */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <BsExclamationTriangle style={{ color: 'var(--warning)' }} />
              Süresi Dolmak Üzere
            </h3>
          </div>
          <div className="card-body">
            {data?.expiring_trials && data.expiring_trials.length > 0 ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                {data.expiring_trials.map((company) => (
                  <div 
                    key={company.id}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      padding: '0.75rem',
                      background: 'var(--surface-glass)',
                      borderRadius: 'var(--radius-md)',
                    }}
                  >
                    <span style={{ fontWeight: 500 }}>{company.name}</span>
                    <span className="badge badge-warning">
                      {company.trial_ends_at}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p style={{ color: 'var(--text-tertiary)', textAlign: 'center', padding: '1rem' }}>
                Süresi dolmak üzere firma yok
              </p>
            )}
          </div>
        </div>

        {/* Recent Companies */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Son Kayıtlar</h3>
            <button 
              className="btn btn-ghost btn-sm"
              onClick={() => navigate('/admin/companies')}
            >
              Tümünü Gör
              <BsArrowRight />
            </button>
          </div>
          <div className="card-body">
            {data?.recent_companies && data.recent_companies.length > 0 ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                {data.recent_companies.map((company) => (
                  <div 
                    key={company.id}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      padding: '0.75rem',
                      background: 'var(--surface-glass)',
                      borderRadius: 'var(--radius-md)',
                      cursor: 'pointer',
                    }}
                    onClick={() => navigate(`/admin/companies/${company.id}`)}
                  >
                    <div>
                      <div style={{ fontWeight: 500 }}>{company.name}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                        {company.package_type}
                      </div>
                    </div>
                    <span className={`badge badge-${company.status === 'active' ? 'success' : company.status === 'trial' ? 'warning' : 'secondary'}`}>
                      {company.status === 'active' ? 'Aktif' : company.status === 'trial' ? 'Deneme' : company.status}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p style={{ color: 'var(--text-tertiary)', textAlign: 'center', padding: '1rem' }}>
                Henüz firma kaydı yok
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Recent Activities */}
      <div className="card" style={{ marginTop: '1.5rem' }}>
        <div className="card-header">
          <h3 className="card-title">Son İşlemler</h3>
          <button 
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/admin/logs')}
          >
            Tüm Loglar
            <BsArrowRight />
          </button>
        </div>
        <div className="card-body" style={{ padding: 0 }}>
          <div className="table-container" style={{ border: 'none' }}>
            <table className="table">
              <thead>
                <tr>
                  <th>Kullanıcı</th>
                  <th>İşlem</th>
                  <th>Açıklama</th>
                  <th>Tarih</th>
                </tr>
              </thead>
              <tbody>
                {data?.recent_activities && data.recent_activities.length > 0 ? (
                  data.recent_activities.map((activity) => (
                    <tr key={activity.id}>
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <div className="avatar avatar-sm">
                            {activity.user_name?.charAt(0) || '?'}
                          </div>
                          <span>{activity.user_name || 'Sistem'}</span>
                        </div>
                      </td>
                      <td>
                        <span className="badge badge-secondary">{activity.action}</span>
                      </td>
                      <td style={{ maxWidth: 300 }} className="truncate">
                        {activity.description || '-'}
                      </td>
                      <td style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>
                        {new Date(activity.created_at).toLocaleString('tr-TR')}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={4} style={{ textAlign: 'center', color: 'var(--text-tertiary)' }}>
                      Henüz işlem kaydı yok
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;

